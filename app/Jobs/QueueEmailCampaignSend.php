<?php

namespace App\Jobs;

use Throwable;
use App\Models\EmailCampaign;
use App\Models\EmailSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QueueEmailCampaignSend implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $campaignId) {}

    public function handle(): void
    {
        $campaign = EmailCampaign::with('list')->findOrFail($this->campaignId);

        // Safety: campaigns should only send to marketing lists
        if ($campaign->list->purpose !== 'marketing') {
            $campaign->update([
                'status' => EmailCampaign::STATUS_FAILED,
                'last_error' => 'Campaign can only send to marketing lists.',
            ]);
            return;
        }

        if (! $campaign->isSendable()) {
            return;
        }

        $listId = (int) $campaign->email_list_id;

        $baseQuery = EmailSubscriber::query()
            ->marketingOptedIn()
            ->subscribedToListId($listId)
            ->select('email_subscribers.id')
            ->orderBy('email_subscribers.id');

        $total = (clone $baseQuery)->count();

        $chunkSize = 500;
        $chunks = (int) ceil($total / $chunkSize);

        $campaign->update([
            'status' => EmailCampaign::STATUS_SENDING,
            'queued_at' => now(),
            'sent_at' => null,
            'sent_count' => 0,
            'pending_chunks' => $chunks,
            'last_error' => null,
        ]);

        if ($chunks === 0) {
            $campaign->update([
                'status' => EmailCampaign::STATUS_SENT,
                'sent_at' => now(),
            ]);
            return;
        }

        $baseQuery->chunkById($chunkSize, function ($rows) use ($campaign) {
            SendEmailCampaignChunk::dispatch(
                campaignId: $campaign->id,
                subscriberIds: $rows->pluck('id')->all(),
            );
        });
    }

    public function failed(Throwable $e): void
    {
        EmailCampaign::whereKey($this->campaignId)->update([
            'status' => EmailCampaign::STATUS_FAILED,
            'last_error' => $e->getMessage(),
        ]);
    }
}
