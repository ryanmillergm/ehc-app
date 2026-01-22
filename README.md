# EHC App

A Laravel + Filament application for managing an organization’s public website and admin operations, including:

- CMS pages with translations
- Events and organizational resources (multi-tenant)
- Email marketing (lists, subscribers, campaigns, queued “live” sends)
- Donations (Stripe) including one-time payments, pledges/recurring flows, and refunds (as supported by your implementation)

This repo is built to be **admin-friendly** in Filament, with access controlled by **Spatie Roles & Permissions**.

---

## Tech stack

- **Laravel**: v11.x
- **PHP**: 8.2.x
- **Database**: MySQL 8.x (recommended)
- **Filament**: v3.x
- **Livewire**: v3.x
- **Jetstream**: v5.x
- **Testing**: PHPUnit 11.x (plus Laravel testing helpers)
- **Node**: use an LTS version compatible with your Vite toolchain

> Tip: run `composer show` to see the exact package versions installed in this repo.

---

## Panels & routes

You have (at least) two Filament panels:

- **Admin panel** (full access): `/admin`
- **Org panel** (tenant-scoped): `/org/{team_name}` (or similar, depending on your tenancy configuration)

Access is gated by Spatie permissions:

- `admin.panel` → can access admin panel
- `org.panel` → can access org panel

Resource-level access is then controlled by policies / additional permissions.

---

## Roles & permissions

Spatie Roles & Permissions is used to control access.

### Seeders

You have seeders similar to:

- `Database\Seeders\PermissionSeeder` — creates permissions like `admin.panel`, `org.panel`, `email.*`, etc.
- `Database\Seeders\RoleSeeder` — creates roles like `Super Admin`, `Admin`, `Director`, `Editor`, and assigns permissions (e.g., Super Admin gets all permissions).

### Policies

Model access is enforced via policies (example mentioned: `app/Policies/ChildPolicy.php`).

---

## Multi-tenancy (Filament)

This app uses Filament multi-tenancy to “separate out manageable resources into departments.”

General rules of thumb:

- The **admin panel** typically has global visibility.
- The **org panel** is typically scoped to the active tenant.
- “I can’t see it” problems are usually one of:
  - wrong panel
  - wrong tenant selected
  - missing permission
  - policy denying access

---

## Email system

The email system is designed for **marketing lists** and supports:

- Email Lists (`EmailList`) — segment/audience with a stable `key` like `newsletter`
- Email Subscribers (`EmailSubscriber`) — recipient records with opt-in/out state
- Email Campaigns (`EmailCampaign`) — subject + HTML body + list + status
- Deliveries (`EmailCampaignDelivery`) — per-recipient delivery records for live sends

### Test vs Live sends

- **Test send**: sends a single email immediately (recommended for verifying rendering and configuration).
- **Live send**: queues a batch send to the campaign list, chunked into jobs.

### Queue pipeline (high level)

1. “Send (LIVE)” action validates and ensures the campaign is compiled/saved.
2. `QueueEmailCampaignSend` creates `EmailCampaignDelivery` rows in chunks and dispatches `SendEmailCampaignChunk` jobs.
3. `SendEmailCampaignChunk` sends each delivery via `EmailCampaignMail` and snapshots rendered HTML for Filament viewing.

### MailTrap (local/dev)

For local/dev you indicated you are using **MailTrap**.

Common setup approach:

- Configure `.env` mail settings to point at MailTrap SMTP.
- Keep `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` set.

Example:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls

MAIL_FROM_ADDRESS="info@breadofgraceministries.com"
MAIL_FROM_NAME="${APP_NAME}"
```

> In testing, you’re using `MAIL_MAILER=array` in `phpunit.xml`, which is great: it prevents real network mail sends.

### Queue worker

Live sends require a queue worker. Locally you can use:

```bash
php artisan queue:work
```

In local testing/dev, you can also temporarily force synchronous queue execution (do **not** leave this on in production):

```env
QUEUE_CONNECTION=sync
```

---

## Donations & Stripe

Stripe powers donations / transactions. Typical components you’ll encounter:

- **Transactions**: a local record representing a Stripe PaymentIntent/Charge lifecycle.
- **Pledges / recurring**: if implemented, local records representing recurring commitments.
- **Refunds**: Stripe refunds linked back to local transactions.

### Stripe CLI (local webhook testing)

Authenticate and forward webhook events locally:

```bash
stripe login
stripe listen --forward-to http://127.0.0.1:8000/stripe/webhook
```

If you use a custom local domain:

```bash
stripe listen --forward-to http://bread-of-grace-ministries.test/stripe/webhook
```

### Webhook keys

Make sure your `.env` includes:

- Stripe secret key (server-side)
- Stripe publishable key (front-end)
- Stripe webhook signing secret (for verification)

Example:

```env
STRIPE_SECRET=sk_test_...
STRIPE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### Troubleshooting Stripe

- Verify the event arrived: Stripe Dashboard → Developers → Events
- Verify your endpoint was called: your app logs + Stripe CLI output
- Verify signature: webhook secret matches and middleware/handler verifies it
- Verify your DB records: transaction status, amounts, refund state


---

## Stripe donation lifecycle (important)

This application follows a **deliberate, hardened Stripe architecture** designed to survive retries, out-of-order webhook delivery, and partial client failures.

**Core rule**

> **Controllers create intent + placeholders. Webhooks finalize reality.**

This rule is enforced by tests and is critical to preventing duplicate transactions.

---

### Key data model concepts

**Transactions**
Local representation of Stripe payment attempts.

Important fields:
- `payment_intent_id` — canonical attempt identifier
- `charge_id` — actual card charge (may arrive later)
- `stripe_invoice_id` — invoices (mainly for subscriptions)
- `subscription_id` — recurring subscriptions
- `status` — `pending`, `succeeded`, etc.

Idempotency expectations:
- One transaction per `payment_intent_id`
- One transaction per `charge_id`
- One transaction per invoice

---

### One-time donation flow

1. **`POST /donations/start`**
   - Creates a pending `transactions` row
   - Creates a Stripe `PaymentIntent`
   - Saves `payment_intent_id`
   - Status is **not** `succeeded`

2. **Client confirms payment with Stripe**
   - Handles SCA / 3DS / card confirmation

3. **`POST /donations/complete`**
   - Updates the existing transaction with:
     - `payment_intent_id`
     - `charge_id` (if available)
     - `payment_method_id`
     - `receipt_url`
     - payer metadata
   - Marks transaction `succeeded` for UI purposes

4. **Webhooks (`payment_intent.succeeded`, `charge.succeeded`)**
   - May arrive before or after `complete`
   - Enrich the same transaction
   - Must never create duplicates

> Note: One-time donations **may not have invoices**. `stripe_invoice_id` is optional.

---

### Monthly (subscription) donation flow

1. **`POST /donations/start`**
   - Creates a `pledges` row (`status = incomplete`)
   - Generates an `attempt_id`

2. **Client collects payment method**
   - Uses a Stripe `SetupIntent`

3. **`POST /donations/complete` (`mode=subscription`)**
   - Calls `StripeService::createSubscriptionForPledge`
   - Updates pledge donor info
   - Creates or enriches a **`subscription_initial` transaction**
   - Captures best-effort identifiers:
     - `subscription_id`
     - latest `stripe_invoice_id`
     - latest `payment_intent_id`
     - latest `charge_id`
   - **Leaves transaction `status = pending`**
   - Does **not** set `paid_at`

4. **Webhooks (`invoice.paid`)**
   - Are the **source of truth**
   - For `billing_reason=subscription_create`:
     - Marks `subscription_initial` as `succeeded`
   - For `billing_reason=subscription_cycle`:
     - Creates/updates a `subscription_recurring` transaction
   - Updates pledge billing periods and timestamps

Webhook behavior must be:
- Idempotent
- Order-independent
- Safe to retry

---

### Why this matters

Stripe webhooks:
- Retry automatically
- Can arrive out of order
- Can arrive before controller actions complete

By **never finalizing subscription payments in controllers**, the system avoids:
- Duplicate transactions
- Incorrect `paid_at` timestamps
- “Phantom succeeded” rows

This behavior is protected by end-to-end tests simulating:
- Out-of-order events
- Duplicate webhook delivery
- Partial Stripe responses

---


---

## Local setup

```bash
git clone https://github.com/ryanmillergm/ehc-app.git
cd ehc-app
composer install
npm install
npm run dev
```

Create a local database, copy your env file, and run migrations/seeders:

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

> If you maintain a dedicated `.env.testing`, ensure it uses an isolated database and safe mail/queue drivers.

### Run the app

```bash
php artisan serve
```

App will be available at `http://127.0.0.1:8000`.

### “All-in-one” dev command

If you have a `composer dev` script set up (Vite + server + Stripe listen), use:

```bash
composer dev
```

---

## Testing

Run the full suite:

```bash
php artisan test
```

Run a single test file:

```bash
vendor/bin/phpunit tests/Feature/Mail/SendEmailCampaignChunkExtraTest.php
```

### Notes about your `phpunit.xml`

Your `phpunit.xml` includes:

- `MAIL_MAILER=array` (prevents real emails)
- `QUEUE_CONNECTION=sync` (jobs run immediately during tests)
- `SESSION_DRIVER=array`, `CACHE_STORE=array` (fast + isolated)

These are good defaults for deterministic tests.

---

## Filament documentation pages

This repo includes in-panel documentation pages for admins, for example:

- `resources/views/filament/pages/email-system-help.blade.php`
- `resources/views/filament/pages/admin-documentation.blade.php`

These are intended to keep operational knowledge **inside the admin panel** so staff can self-serve answers.

---

## Common commands

```bash
# Clear caches
php artisan optimize:clear

# Rebuild autoload
composer dump-autoload

# Queue worker
php artisan queue:work

# Run migrations fresh (local only)
php artisan migrate:fresh --seed
```

---

## Contributors

- Ryan Miller — [@ryanmillergm](https://github.com/ryanmillergm)

---

## License

This project is private/internal unless otherwise stated.
