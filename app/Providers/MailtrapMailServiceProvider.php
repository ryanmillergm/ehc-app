<?php

declare(strict_types=1);

namespace App\Providers;

use App\Mail\Transport\MailtrapApiTransport;
use App\Services\MailtrapApiMailer;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;

class MailtrapMailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /** @var MailManager $manager */
        $manager = $this->app->make('mail.manager');

        $manager->extend('mailtrap_api', function (array $config) {
            return new MailtrapApiTransport(
                $this->app->make(MailtrapApiMailer::class),
            );
        });
    }
}
