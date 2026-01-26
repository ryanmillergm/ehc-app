<?php

namespace App\Jobs;

use App\Models\QueueProbe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class QueueMailProbe implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;

    public function __construct(
        public string $toEmail,
        public ?string $toName = null,
        public ?int $probeId = null,
    ) {}

    public static function dispatchProbe(string $toEmail, ?string $toName = null): QueueProbe
    {
        $probe = QueueProbe::create([
            'kind'            => 'mail',
            'status'          => 'queued',
            'to_email'        => $toEmail,
            'mailer_default'  => (string) config('mail.default'),
            'queue_connection'=> (string) config('queue.default'),
            'queue_name'      => 'default',
            'meta'            => json_encode([
                'app_env' => config('app.env'),
                'app_url' => config('app.url'),
            ], JSON_UNESCAPED_SLASHES),
            'queued_at'       => now(),
        ]);

        self::dispatch($toEmail, $toName, $probe->id)->onConnection('database')->onQueue('default');

        return $probe;
    }

    public function handle(): void
    {
        $probe = $this->probeId ? QueueProbe::find($this->probeId) : null;

        $ctx = [
            'probe_id'         => $probe?->id,
            'mailer_default'   => config('mail.default'),
            'queue_default'    => config('queue.default'),
            'connection_name'  => $this->connection,
            'queue_name'       => $this->queue,
            'attempts'         => method_exists($this, 'attempts') ? $this->attempts() : null,
            'to'               => $this->toEmail,
        ];

        Log::info('QUEUE MAIL PROBE: started', $ctx);

        if ($probe) {
            $probe->update([
                'status'          => 'running',
                'attempt'         => (int) ($this->attempts() ?? 1),
                'started_at'      => now(),
                'mailer_default'  => (string) config('mail.default'),
                'queue_connection'=> (string) config('queue.default'),
                'queue_name'      => (string) ($this->queue ?? 'default'),
            ]);
        }

        try {
            // Send using the *configured* mailer (mailtrap_api in your case)
            Mail::raw('QueueMailProbe via Laravel queue worker', function ($m) {
                $m->to($this->toEmail, $this->toName ?: null)
                  ->subject('QueueMailProbe (queued) - '.now()->toDateTimeString());
            });

            if ($probe) {
                $probe->update([
                    'status'      => 'sent',
                    'finished_at' => now(),
                ]);
            }

            Log::info('QUEUE MAIL PROBE: sent', $ctx);
        } catch (Throwable $e) {
            if ($probe) {
                $probe->update([
                    'status'      => 'failed',
                    'error'       => $e->getMessage(),
                    'finished_at' => now(),
                ]);
            }

            Log::error('QUEUE MAIL PROBE: failed', $ctx + [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            throw $e; // important so the job is retried/marked failed
        }
    }

    public function failed(Throwable $e): void
    {
        if ($this->probeId) {
            QueueProbe::whereKey($this->probeId)->update([
                'status'      => 'failed',
                'error'       => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }

        Log::error('QUEUE MAIL PROBE: failed() callback', [
            'probe_id' => $this->probeId,
            'error'    => $e->getMessage(),
        ]);
    }
}
