# SEO Production Validation Checklist

Use this checklist immediately after deploying SEO-related changes to production.

## 1) Confirm production environment values

- Ensure `SEO_GOOGLE_SITE_VERIFICATION` is set.
- Ensure `SEO_GA4_MEASUREMENT_ID` is set (format: `G-XXXXXXXXXX`).
- Run:

```bash
php artisan config:clear
```

## 2) Validate homepage source (`/`)

- `<title>` is correct and current.
- `<meta name="description">` is present.
- `<link rel="canonical">` points to the correct production URL.
- Google verification meta tag is present.
- GA4 script/tag is present with the correct measurement ID.

## 3) Validate index page source (`/pages`)

- Title, description, canonical are correct.
- `robots` is `index,follow` unless intentionally overridden.

## 4) Validate priority page translation source (`/pages/{slug}`)

- Canonical points to that exact slug URL.
- Title/description match expected canonical SEO row.
- `robots` is `index,follow`.
- No accidental canonical to a different URL.

## 4.1) Validate one page per render mode

Check all three render modes still output correct SEO tags:

- template page
- blocks page
- custom page

For each:

- `<title>` matches expected SEO source
- `<meta name="description">` is present and correct
- canonical points to exact expected URL
- `robots` remains expected value

## 5) Validate noindex safety pages

Confirm these remain non-indexable:

- `/donations/thank-you`
- `/donations/thank-you-subscription`
- tokenized email routes (unsubscribe/preferences)

Expected robots value: `noindex,nofollow`.

## 6) Verify GA4 data reception

- Open GA4 Realtime.
- Visit production pages from an incognito window.
- Confirm `page_view` events appear within about 30-90 seconds.

## 7) Verify Search Console status

- Confirm property remains verified.
- Use URL Inspection on key pages.

## 8) Request indexing for key pages

In Search Console URL Inspection:

- inspect `/`
- inspect `/pages`
- inspect top priority translation pages
- request indexing where needed

## 9) Canonical sanity checks

- No canonical tag points to localhost/staging.
- Canonical uses production domain and protocol (`https`).
- Canonical tags are present on indexable pages.

## 10) Record post-deploy baseline

- Note deploy date/time.
- Capture initial Search Console metrics:
  - impressions
  - clicks
  - indexing/exclusions
- Re-check after 3-7 days and iterate titles/descriptions from query data.

## Related docs

- `docs/pages-authoring.md`
- `docs/pages-rollout.md`
