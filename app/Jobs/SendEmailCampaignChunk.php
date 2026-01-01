<?php

namespace App\Jobs;

use App\Mail\EmailCampaignMail;
use App\Models\EmailCampaign;
use App\Models\EmailSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailCampaignChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int,int>  $subscriberIds
     */
    public function __construct(
        public int $campaignId,
        public array $subscriberIds,
    ) {}

    public function handle(): void
    {
        $campaign = EmailCampaign::with('list')->findOrFail($this->campaignId);

        // Safety: never send non-marketing via campaign pipeline
        if ($campaign->list->purpose !== 'marketing') {
            $campaign->update([
                'status' => EmailCampaign::STATUS_FAILED,
                'last_error' => 'Campaign can only send to marketing lists.',
            ]);
            return;
        }

        $listId = (int) $campaign->email_list_id;

        $subs = EmailSubscriber::query()
            ->marketingOptedIn()
            ->whereIn('id', $this->subscriberIds)
            ->subscribedToListId($listId)
            ->get();

        $sentThisChunk = 0;

        foreach ($subs as $subscriber) {
            Mail::to($subscriber->email)->send(
                new EmailCampaignMail(
                    subscriber: $subscriber,
                    list: $campaign->list,
                    subjectLine: $campaign->subject,
                    bodyHtml: $campaign->body_html,
                )
            );

            $sentThisChunk++;
        }

        EmailCampaign::whereKey($campaign->id)->increment('sent_count', $sentThisChunk);
        EmailCampaign::whereKey($campaign->id)->decrement('pending_chunks');

        EmailCampaign::whereKey($campaign->id)
            ->where('pending_chunks', 0)
            ->whereNull('sent_at')
            ->update([
                'status' => EmailCampaign::STATUS_SENT,
                'sent_at' => now(),
            ]);
    }

    public function failed(\Throwable $e): void
    {
        EmailCampaign::whereKey($this->campaignId)->update([
            'status' => EmailCampaign::STATUS_FAILED,
            'last_error' => $e->getMessage(),
        ]);
    }
}
