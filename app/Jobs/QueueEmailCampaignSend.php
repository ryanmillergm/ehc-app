<?php

namespace App\Jobs;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignDelivery;
use App\Models\EmailSubscriber;
use Throwable;
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

        if (! $campaign->isSendable()) {
            return;
        }

        // Safety: only marketing lists through this pipeline
        if (($campaign->list->purpose ?? null) !== 'marketing') {
            $campaign->update([
                'status' => EmailCampaign::STATUS_FAILED,
                'last_error' => 'Campaign can only send to marketing lists.',
            ]);
            return;
        }

        $listId = (int) $campaign->email_list_id;

        $baseQuery = EmailSubscriber::query()
            ->marketingOptedIn()
            ->subscribedToListId($listId)
            ->select(['email_subscribers.id', 'email_subscribers.email', 'email_subscribers.first_name', 'email_subscribers.last_name'])
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

        $fromEmail = config('mail.from.address');
        $fromName  = config('mail.from.name');

        $baseQuery->chunkById($chunkSize, function ($subs) use ($campaign, $fromEmail, $fromName) {
            $now = now();

            $rows = $subs->map(function (EmailSubscriber $sub) use ($campaign, $fromEmail, $fromName, $now) {
                return [
                    'email_campaign_id' => $campaign->id,
                    'email_subscriber_id' => $sub->id,

                    'to_email' => $sub->email,
                    'to_name' => $sub->name,

                    'from_email' => $fromEmail,
                    'from_name' => $fromName,

                    'subject' => $campaign->subject,
                    'body_html' => null,

                    'status' => EmailCampaignDelivery::STATUS_QUEUED,
                    'attempts' => 0,

                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all();

            // Idempotent insert
            EmailCampaignDelivery::upsert(
                $rows,
                ['email_campaign_id', 'email_subscriber_id'],
                ['to_email', 'to_name', 'from_email', 'from_name', 'subject', 'status', 'updated_at']
            );

            $deliveryIds = EmailCampaignDelivery::query()
                ->where('email_campaign_id', $campaign->id)
                ->whereIn('email_subscriber_id', $subs->pluck('id'))
                ->pluck('id')
                ->all();

            SendEmailCampaignChunk::dispatch($campaign->id, $deliveryIds);
        });
    }

    public function failed(Throwable $e): void
    {
        EmailCampaign::whereKey($this->campaignId)->update([
            'status' => EmailCampaign::STATUS_FAILED,
            'last_error' => $e->getMessage(),
            'pending_chunks' => 0,
        ]);
    }
}
