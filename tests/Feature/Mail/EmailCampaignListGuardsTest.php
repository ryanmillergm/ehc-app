<?php

namespace Tests\Feature\Mail;

use App\Models\EmailCampaign;
use App\Models\EmailList;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailCampaignListGuardsTest extends TestCase
{
    #[Test]
    public function it_cannot_create_a_campaign_on_a_transactional_list(): void
    {
        $list = EmailList::factory()->transactional()->create();

        $this->expectException(ValidationException::class);

        EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
        ]);
    }

    #[Test]
    public function it_cannot_change_list_purpose_if_campaigns_exist(): void
    {
        $list = EmailList::factory()->marketing()->create();

        EmailCampaign::factory()->create([
            'email_list_id' => $list->id,
        ]);

        $this->expectException(ValidationException::class);

        $list->update(['purpose' => 'transactional']);
    }
}
