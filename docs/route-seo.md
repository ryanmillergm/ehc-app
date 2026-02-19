# Route SEO CMS

This project uses DB-managed SEO metadata for selected indexable marketing routes.

## DB-managed route keys

- `donations.show` -> `/give`
- `pages.index` -> `/pages`
- `emails.subscribe` -> `/emails/subscribe`

Data is stored in `route_seos` and managed via Filament resource:

- `Route SEO`

In-panel end-to-end guide:

- `SEO Documentation` (Filament helper page)

Fields:

- `route_key`
- `language_id`
- `seo_title`
- `seo_description`
- `seo_og_image`
- `canonical_path` (optional override path)
- `is_active`

## Fallback behavior

For a given route key, resolver order is:

1. Active row for current language (`session('language_id')` / locale)
2. Active row for default language (`Language::first()`)
3. Safe hardcoded defaults in `RouteSeoResolver`

## Routes intentionally code-controlled

These remain hardcoded to enforce `noindex,nofollow` safety:

- `/donations/thank-you`
- `/donations/thank-you-subscription`
- `/unsubscribe/{token}`
- `/email-preferences/{token}`

## Seed baseline values

Seeder:

- `Database\\Seeders\\RouteSeoSeeder`

Included in `DatabaseSeeder`.
