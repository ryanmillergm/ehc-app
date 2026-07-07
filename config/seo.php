<?php

return [
    'site_name' => env('SEO_SITE_NAME', config('app.name', 'Bread of Grace Ministries')),
    'default_title' => env('SEO_DEFAULT_TITLE', 'Bread of Grace Ministries'),
    'default_description' => env(
        'SEO_DEFAULT_DESCRIPTION',
        'Bread of Grace Ministries in Sacramento, California serves people experiencing homelessness through meals, outreach, housing pathways, and Christ-centered support.'
    ),
    'default_og_image' => env('SEO_DEFAULT_OG_IMAGE', '/images/sm/the-mayor.jpg'),
    'default_twitter_card' => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
    'google_site_verification' => env('SEO_GOOGLE_SITE_VERIFICATION'),
    'ga4_measurement_id' => env('SEO_GA4_MEASUREMENT_ID'),
    'robots' => [
        'indexable' => env('SEO_ROBOTS_INDEXABLE', 'index,follow'),
        'noindex' => env('SEO_ROBOTS_NOINDEX', 'noindex,nofollow'),
    ],
    'robots_disallow_non_production' => env('SEO_ROBOTS_DISALLOW_NON_PRODUCTION', true),
];
