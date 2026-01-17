<?php

namespace Tests\Feature\Models;

use App\Models\EmailCampaign;
use App\Models\EmailList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EmailCampaignDesignHtmlMutatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_strips_body_wrappers_and_stores_only_fragment_in_design_html(): void
    {
        $list = EmailList::factory()->marketing()->create();

        $campaign = EmailCampaign::create([
            'email_list_id' => $list->id,
            'subject' => 'Test',
            'editor' => 'grapes',
            'status' => EmailCampaign::STATUS_DRAFT,
            'design_html' => '<!doctype html><html><head></head><body><div>Hi</div></body></html>',
        ]);

        $this->assertSame('<div>Hi</div>', $campaign->fresh()->design_html);
    }
}
