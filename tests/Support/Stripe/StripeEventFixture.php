<?php

namespace Tests\Support\Stripe;

class StripeEventFixture
{
    public static function load(string $name): object
    {
        $path = base_path('tests/Feature/Stripe/Fixtures/' . $name);

        if (! file_exists($path)) {
            throw new \RuntimeException("Stripe fixture not found: {$path}");
        }

        $json = file_get_contents($path);
        $data = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    public static function loadMany(array $names): array
    {
        return array_map(fn ($n) => self::load($n), $names);
    }
}
