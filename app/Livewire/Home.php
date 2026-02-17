<?php

namespace App\Livewire;

use Livewire\Component;

class Home extends Component
{
    public function render()
    {
        $canonical = url('/');
        $ogImage = asset('images/sm/the-mayor.jpg');
        $seoTitle = 'Homeless Ministry in Sacramento, CA | Bread of Grace Ministries';
        $seoDescription = 'Bread of Grace Ministries serves Sacramento through homeless outreach, hot meals, housing pathways, discipleship, and practical support. Give to help feed the hungry and support the needy.';

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
                        'mainEntity' => [
                            [
                                '@type' => 'Question',
                                'name' => 'How are donations used?',
                                'acceptedAnswer' => [
                                    '@type' => 'Answer',
                                    'text' => 'Donations support outreach essentials including meals, survival supplies, discipleship, and practical housing and employment support.',
                                ],
                            ],
                            [
                                '@type' => 'Question',
                                'name' => 'Where does outreach happen?',
                                'acceptedAnswer' => [
                                    '@type' => 'Answer',
                                    'text' => 'Outreach gatherings are held in Sacramento at Township 9 Park every Thursday and Sunday at 11:00am.',
                                ],
                            ],
                            [
                                '@type' => 'Question',
                                'name' => 'Can I volunteer if I am new?',
                                'acceptedAnswer' => [
                                    '@type' => 'Answer',
                                    'text' => 'Yes. New volunteers are welcome and can help with food service, outreach support, prayer, and follow-up care.',
                                ],
                            ],
                            [
                                '@type' => 'Question',
                                'name' => 'Can I give monthly to support long-term impact?',
                                'acceptedAnswer' => [
                                    '@type' => 'Answer',
                                    'text' => 'Yes. Monthly giving helps sustain consistent ministry work in housing support, food outreach, and mentorship.',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
