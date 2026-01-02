<?php

namespace App\Jobs;

use App\Mail\EmailCampaignMail;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendEmailCampaignChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<int,int> $deliveryIds
     */
    public function __construct(
        public int $campaignId,
        public array $deliveryIds,
    ) {}

    public function handle(): void
    {
        $campaign = EmailCampaign::with('list')->findOrFail($this->campaignId);

        $deliveries = EmailCampaignDelivery::query()
            ->with('subscriber')
            ->where('email_campaign_id', $campaign->id)
            ->whereIn('id', $this->deliveryIds)
            ->orderBy('id')
            ->get();

        $sentThisChunk = 0;
        $failedThisChunk = 0;

        foreach ($deliveries as $delivery) {
            // Don’t re-send already completed deliveries
            if (in_array($delivery->status, [EmailCampaignDelivery::STATUS_SENT], true)) {
                continue;
            }

            $subscriber = $delivery->subscriber;

            // If subscriber got unsubscribed between queueing and sending:
            if (! $subscriber || ! $subscriber->canReceiveMarketingList($campaign->list->key)) {
                $delivery->update([
                    'status' => EmailCampaignDelivery::STATUS_SKIPPED,
                    'last_error' => null,
                ]);
                continue;
            }

            $delivery->increment('attempts');

            try {
                $mailable = new EmailCampaignMail(
                    subscriber: $subscriber,
                    list: $campaign->list,
                    subjectLine: $campaign->subject,
                    bodyHtml: $campaign->body_html,
                );

                if ($delivery->from_email) {
                    $mailable->from($delivery->from_email, $delivery->from_name);
                }

                // Render final HTML snapshot for Filament viewing
                $renderedHtml = $mailable->render();

                Mail::to($delivery->to_email, $delivery->to_name)->send($mailable);

                $delivery->update([
                    'subject' => $campaign->subject,
                    'body_html' => $renderedHtml,
                    'status' => EmailCampaignDelivery::STATUS_SENT,
                    'sent_at' => now(),
                    'failed_at' => null,
                    'last_error' => null,
                ]);

                $sentThisChunk++;
            } catch (Throwable $e) {
                $delivery->update([
                    'status' => EmailCampaignDelivery::STATUS_FAILED,
                    'failed_at' => now(),
                    'last_error' => $e->getMessage(),
                ]);

                $failedThisChunk++;
            }
        }

        if ($sentThisChunk > 0) {
            EmailCampaign::whereKey($campaign->id)->increment('sent_count', $sentThisChunk);
        }

        EmailCampaign::whereKey($campaign->id)->decrement('pending_chunks');

        // If campaign finished, decide final status
        $fresh = EmailCampaign::find($campaign->id);

        if ($fresh && (int) $fresh->pending_chunks === 0 && is_null($fresh->sent_at)) {
            $hasFailures = EmailCampaignDelivery::query()
                ->where('email_campaign_id', $fresh->id)
                ->where('status', EmailCampaignDelivery::STATUS_FAILED)
                ->exists();

            $fresh->update([
                'status' => $hasFailures ? EmailCampaign::STATUS_FAILED : EmailCampaign::STATUS_SENT,
                'sent_at' => now(),
            ]);
        }
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
