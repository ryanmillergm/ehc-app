<?php

namespace Database\Seeders;

use App\Models\FaqItem;
use App\Models\Language;
use Illuminate\Database\Seeder;

class FaqItemSeeder extends Seeder
{
    public function run(): void
    {
        $english = Language::query()->firstOrCreate(
            ['iso_code' => 'en'],
            [
                'title' => 'English',
                'iso_code' => 'en',
                'locale' => 'en',
                'right_to_left' => false,
            ]
        );

        $rows = [
            [
                'question' => 'How are donations used?',
                'answer' => 'Donations support outreach essentials including hot meals, hygiene and survival supplies, bibles, discipleship, and practical housing and employment support.',
                'sort_order' => 10,
            ],
            [
                'question' => 'Where does outreach happen?',
                'answer' => 'Outreach gatherings are held in Sacramento at Township 9 Park every Thursday and Sunday at 11:00am.',
                'sort_order' => 20,
            ],
            [
                'question' => 'Can I volunteer if I am new?',
                'answer' => 'Yes. New volunteers are welcome and can help with food service, outreach support, prayer, and follow-up care.',
                'sort_order' => 30,
            ],
            [
                'question' => 'Can I give monthly to support long-term impact?',
                'answer' => 'Yes. Monthly giving helps sustain consistent ministry work with food outreach, mentorship, and future housing support.',
                'sort_order' => 40,
            ],
        ];

        foreach ($rows as $row) {
            FaqItem::query()->updateOrCreate(
                [
                    'context' => 'home',
                    'language_id' => $english->id,
                    'question' => $row['question'],
                ],
                [
                    'answer' => $row['answer'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
