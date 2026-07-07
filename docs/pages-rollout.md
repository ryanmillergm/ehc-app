# Pages Authoring Rollout Runbook

Use this runbook when deploying page authoring architecture changes (`template`, `blocks`, `custom`) to shared environments.

## 1) Pre-Deploy Checklist

- Confirm branch includes:
  - render mode fields
  - sanitizer profile updates
  - updated seeders
  - tests for render/security behavior
- Confirm no unreviewed migration edits remain.

## 2) Deploy Steps

1. Deploy code.
2. Run migrations.
3. Run required seeders.
4. Clear config/cache.

Commands:

```bash
php artisan migrate
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=HomelessMinistrySacramentoPageSeeder
php artisan config:clear
php artisan optimize:clear
```

## 3) Schema Validation

Confirm `page_translations` includes:

- `render_mode`
- `content_blocks`
- `custom_html`
- `custom_html_is_trusted`

Confirm old `layout_data` flow is not used by runtime authoring path.

## 4) Permission Validation

Confirm `permissions` table has:

- `pages.render_unsafe_html`

Assign this only to trusted admin/editor roles that require advanced custom markup.

## 5) Seeder Validation

Run and verify:

- `HomelessMinistrySacramentoPageSeeder`

Expected:

- translation exists
- `render_mode=template`
- `content_blocks` seeded

## 6) Functional Smoke Tests

Validate one page per mode:

- template mode page
- blocks mode page
- custom mode page

Checks:

- page loads
- content renders
- no runtime errors in logs
- SEO tags still present

## 7) Security Validation

Custom HTML:

- script tags are removed
- event handlers are removed
- `javascript:` URLs are removed

Permission gate:

- user without `pages.render_unsafe_html` cannot persist trusted mode

## 8) SEO Validation

For each mode, verify:

- `<title>`
- `<meta name="description">`
- canonical URL
- OG image behavior

Run full SEO checklist:

- `docs/seo-production-checklist.md`

## 9) Test Validation (CI/Manual)

Recommended suites:

```bash
php artisan test tests/Unit/PageTranslationsTest.php
php artisan test tests/Feature/Livewire/Pages/ShowPageTest.php
php artisan test tests/Feature/Filament/PageTranslationResourceTest.php
php artisan test tests/Feature/Database/HomelessMinistrySacramentoPageSeederTest.php
php artisan test tests/Feature/Database/PermissionSeederTest.php
```

## 10) Rollback Strategy

If rollout fails:

1. Revert deployment.
2. Restore database backup if migration introduced incompatible schema.
3. Re-run cache clear commands.
4. Re-validate critical URLs and logs.

## 11) Post-Deploy Monitoring

- Check error logs for `ShowPage` rendering failures.
- Check admin editing flow for each mode.
- Review Search Console and GA4 baselines after release window.

## Related Docs

- `docs/pages-authoring.md`
- `docs/pages-block-catalog.md`
- `docs/seo-monitoring.md`

