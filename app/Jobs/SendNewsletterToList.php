<?php

namespace App\Jobs;

use App\Mail\NewsletterMail;
use App\Models\EmailList;
use App\Models\EmailSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNewsletterToList implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $listKey,
        public string $subjectLine,
        public string $bodyHtml,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $list = EmailList::where('key', $this->listKey)->firstOrFail();

        EmailSubscriber::query()
            ->marketingOptedIn()
            ->subscribedToListKey($this->listKey)
            ->orderBy('id')
            ->chunkById(500, function ($subs) use ($list) {
                foreach ($subs as $subscriber) {
                    Mail::to($subscriber->email)->queue(
                        new NewsletterMail($subscriber, $list, $this->subjectLine, $this->bodyHtml)
                    );
                }
            });
    }
}
