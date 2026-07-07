<?php

namespace App\Support\Seo;

class RouteSeoTarget
{
    public const DONATIONS_SHOW = 'donations.show';
    public const PAGES_INDEX = 'pages.index';
    public const EMAILS_SUBSCRIBE = 'emails.subscribe';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::DONATIONS_SHOW => 'Give Page (/give)',
            self::PAGES_INDEX => 'Pages Index (/pages)',
            self::EMAILS_SUBSCRIBE => 'Email Subscribe (/emails/subscribe)',
        ];
    }

    public static function isSupported(string $routeKey): bool
    {
        return array_key_exists($routeKey, self::options());
    }
}
