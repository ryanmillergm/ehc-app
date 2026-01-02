<?php

namespace Database\Factories;

use App\Models\EmailCampaign;
use App\Models\EmailList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\EmailCampaign>
 */
class EmailCampaignFactory extends Factory
{
    protected $model = EmailCampaign::class;

    public function definition(): array
    {
        return [
            'email_list_id' => EmailList::factory(),

            'subject' => $this->faker->sentence(6),
            'body_html' => '<p>' . e($this->faker->paragraph()) . '</p>',

            'status' => EmailCampaign::STATUS_DRAFT,

            'sent_count' => 0,
            'pending_chunks' => 0,

            'queued_at' => null,
            'sent_at' => null,
            'last_error' => null,
        ];
    }

    public function sending(): static
    {
        return $this->state(fn () => [
            'status' => EmailCampaign::STATUS_SENDING,
            'queued_at' => now(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => EmailCampaign::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function failed(string $error = 'Test failure'): static
    {
        return $this->state(fn () => [
            'status' => EmailCampaign::STATUS_FAILED,
            'last_error' => $error,
        ]);
    }
}
