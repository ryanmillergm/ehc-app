# SEO Monitoring Runbook (Google Search Console + GA4)

This runbook defines the production setup and operating checks for SEO monitoring.

## 1) Production config

Set these values in production `.env`:

```env
SEO_GOOGLE_SITE_VERIFICATION=your-google-verification-token
SEO_GA4_MEASUREMENT_ID=G-XXXXXXXXXX
```

Then clear config cache:

```bash
php artisan config:clear
```

## 2) Google Search Console setup

1. Create a URL-prefix property for the production canonical domain.
2. Use **HTML meta tag** verification.
3. Copy the token into `SEO_GOOGLE_SITE_VERIFICATION`.
4. Confirm `google-site-verification` meta exists in page source.
5. In Search Console, submit:
   - `https://<your-domain>/sitemap.xml`

## 3) GA4 setup

1. Create/select your GA4 property.
2. Copy the measurement ID (`G-...`) into `SEO_GA4_MEASUREMENT_ID`.
3. Confirm `gtag/js` loads in page source.
4. Validate live traffic in GA4 Realtime.

## 4) Weekly checks

1. Search Console > Indexing > Pages:
   - Monitor new errors and excluded URLs.
2. Search Console > Sitemaps:
   - Verify sitemap status remains successful.
3. Search Console > Performance:
   - Track clicks, impressions, CTR, position trend.
4. GA4 > Traffic acquisition:
   - Track organic sessions and landing pages.

## 5) Monthly checks

1. Identify pages with high impressions + low CTR.
2. Identify pages with declining clicks.
3. Review excluded URLs and confirm expected `noindex` pages only.
4. Review top-performing queries and refresh content/meta accordingly.

## 6) Incident playbook

Use this when index coverage drops or traffic declines unexpectedly.

1. Validate app-level SEO routes:
   - `/robots.txt`
   - `/sitemap.xml`
2. Inspect affected URL in Search Console URL Inspection.
3. Verify rendered head includes:
   - `meta robots`
   - canonical
   - expected status code (200 for indexable pages)
4. Confirm page is not accidentally marked `noindex`.
5. If config changed recently, verify environment values and run:
   - `php artisan config:clear`
6. Re-test and request reindexing in Search Console.

## 7) Ownership and cadence

- Owner: engineering + content owner
- Cadence: weekly checks, monthly review, incident-driven triage
