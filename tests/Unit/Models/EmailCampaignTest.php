<?php

namespace Tests\Unit\Models;

use App\Models\EmailCampaign;
use App\Models\EmailList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailCampaignTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_rejects_non_marketing_lists_on_save(): void
    {
        $list = EmailList::factory()->create([
            'purpose' => 'transactional',
        ]);

        $campaign = EmailCampaign::factory()->make([
            'email_list_id' => $list->id,
        ]);

        $this->expectException(ValidationException::class);

        $campaign->save();
    }
}
