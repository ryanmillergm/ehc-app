<?php

namespace App\Jobs;

use App\Mail\EmailCampaignMail;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignDelivery;
use App\Support\Email\EmailBodyCompiler;
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

    public function handle(EmailBodyCompiler $compiler): void
    {
        $campaign = EmailCampaign::with('list')->findOrFail($this->campaignId);

        // Safety net: ensure body_html exists
        if (
            $campaign->editor === 'grapesjs'
            && blank($campaign->body_html)
            && filled($campaign->design_html)
        ) {
            $compiled = $compiler->compile(
                (string) $campaign->design_html,
                (string) ($campaign->design_css ?? '')
            );

            $campaign->forceFill([
                'body_html' => $compiled['html'],
                'body_text' => $compiled['text'],
            ])->save();
        }

        $deliveries = EmailCampaignDelivery::query()
            ->with('subscriber')
            ->where('email_campaign_id', $campaign->id)
            ->whereIn('id', $this->deliveryIds)
            ->orderBy('id')
            ->get();

        $sentThisChunk = 0;

        foreach ($deliveries as $delivery) {
            if ($delivery->status === EmailCampaignDelivery::STATUS_SENT) {
                continue;
            }

            $subscriber = $delivery->subscriber;

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
                    subjectLine: (string) $campaign->subject,
                    bodyHtml: (string) $campaign->body_html,
                );

                // Make recipient deterministic BEFORE render()
                $mailable->to($delivery->to_email, $delivery->to_name);

                // Delivery-level sender override (safe for envelope())
                if ($delivery->from_email) {
                    $mailable->usingFrom($delivery->from_email, $delivery->from_name);
                }

                // Snapshot for Filament viewing
                $renderedHtml = $mailable->render();

                // Send the already-addressed mailable
                Mail::send($mailable);

                $delivery->update([
                    'subject' => (string) $campaign->subject,
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
            }
        }

        if ($sentThisChunk > 0) {
            EmailCampaign::whereKey($campaign->id)->increment('sent_count', $sentThisChunk);
        }

        // Always decrement one chunk completion per job run
        EmailCampaign::whereKey($campaign->id)
            ->where('pending_chunks', '>', 0)
            ->decrement('pending_chunks');

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
