<?php

namespace Database\Seeders;

use App\Models\EmailList;
use Illuminate\Database\Seeder;

class EmailListSeeder extends Seeder
{
    public function run(): void
    {
        $lists = [
            ['key' => 'newsletter', 'label' => 'Newsletter', 'purpose' => 'marketing', 'is_default' => true],
            // ['key' => 'events', 'label' => 'Events', 'purpose' => 'marketing', 'is_default' => true],
            // ['key' => 'blog', 'label' => 'Blog', 'purpose' => 'marketing', 'is_default' => false],
            // ['key' => 'updates', 'label' => 'Updates', 'purpose' => 'marketing', 'is_default' => true],

            // transactional example
            // ['key' => 'serve_request_received', 'label' => 'Serve Request Received', 'purpose' => 'transactional', 'is_default' => false, 'is_opt_outable' => false],
        ];

        foreach ($lists as $l) {
            EmailList::query()->updateOrCreate(['key' => $l['key']], $l);
        }
    }
}
