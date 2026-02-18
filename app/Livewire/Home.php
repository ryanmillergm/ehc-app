<?php

namespace App\Livewire;

use App\Services\Content\HomeContentService;
use Livewire\Component;

class Home extends Component
{
    public function render()
    {
        $homeContent = app(HomeContentService::class)->build();
        $canonical = url('/');
        $ogImage = $homeContent['images']['seo_og'];
        $seoTitle = $homeContent['seoTitle'];
        $seoDescription = $homeContent['seoDescription'];

        $faqSchema = $homeContent['faqItems']->map(function ($faq) {
            return [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq->answer,
                ],
            ];
        })->values()->all();

        return view('livewire.home')
            ->layout('components.layouts.app', [
                'title' => 'Bread of Grace Ministries',
                'metaTitle' => $seoTitle,
                'metaDescription' => $seoDescription,
                'canonicalUrl' => $canonical,
                'ogType' => 'website',
                'ogTitle' => $seoTitle,
                'ogDescription' => $seoDescription,
                'ogImage' => $ogImage,
                'twitterCard' => 'summary_large_image',
                'twitterTitle' => $seoTitle,
                'twitterDescription' => $seoDescription,
                'twitterImage' => $ogImage,
                'seoJsonLd' => [
                    [
                        '@context' => 'https://schema.org',
                        '@type' => 'Organization',
                        'name' => 'Bread of Grace Ministries',
                        'url' => $canonical,
                        'logo' => asset('images/favicons/android-icon-192x192.png'),
                        'sameAs' => [
                            'https://www.facebook.com/breadofgraceministry1',
                            'https://www.instagram.com/breadofgraceministry/',
                        ],
                        'address' => [
                            '@type' => 'PostalAddress',
                            'streetAddress' => 'Township 9 Park',
                            'addressLocality' => 'Sacramento',
                            'addressRegion' => 'CA',
                            'postalCode' => '95811',
                            'addressCountry' => 'US',
                        ],
                    ],
                    [
                        '@context' => 'https://schema.org',
                        '@type' => 'WebSite',
                        'name' => 'Bread of Grace Ministries',
                        'url' => $canonical,
                    ],
                    [
                        '@context' => 'https://schema.org',
                        '@type' => 'FAQPage',
                        'mainEntity' => $faqSchema,
                    ],
                ],
            ])
            ->with([
                'faqItems' => $homeContent['faqItems'],
                'homeImages' => $homeContent['images'],
                'heroIntro' => $homeContent['heroIntro'],
                'meetingSchedule' => $homeContent['meetingSchedule'],
                'meetingLocation' => $homeContent['meetingLocation'],
                'sections' => $homeContent['sections'],
            ]);
    }
}
