<?php

namespace App\Support\Stripe;

use Stripe\Charge;

/**
 * Shared helpers for Stripe object id extraction + metadata merging/enrichment.
 *
 * Centralizing these avoids "fix it in three places" drift.
 */
trait InteractsWithStripeMetadata
{
    /**
     * Extract a Stripe ID from a variety of shapes:
     * - string id
     * - StripeObject / array with ['id' => ...]
     */
    protected function extractId(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_array($value)) {
            $id = $value['id'] ?? null;
            return is_string($id) && $id !== '' ? $id : null;
        }

        if (is_object($value) && isset($value->id) && is_string($value->id) && $value->id !== '') {
            return $value->id;
        }

        return null;
    }

    /**
     * Merge metadata stored as array or JSON into an array and overlay $extra.
     */
    protected function mergeMetadata(mixed $existing, array $extra): array
    {
        $base = [];

        if (is_array($existing)) {
            $base = $existing;
        } elseif (is_string($existing) && $existing !== '') {
            $decoded = json_decode($existing, true);
            if (is_array($decoded)) {
                $base = $decoded;
            }
        }

        return array_merge($base, $extra);
    }

    /**
     * Extract card-related metadata fields from a Charge (when present).
     */
    protected function cardMetaFromCharge(Charge $charge): array
    {
        $pmd = $charge->payment_method_details ?? null;
        $card = is_object($pmd) ? ($pmd->card ?? null) : (is_array($pmd) ? ($pmd['card'] ?? null) : null);

        if (! $card) {
            return [];
        }

        // Stripe objects can behave like arrays *or* objects depending on SDK/context
        $get = function ($key) use ($card) {
            if (is_object($card) && isset($card->{$key})) return $card->{$key};
            if (is_array($card)) return $card[$key] ?? null;
            return null;
        };

        return array_filter([
            'card_brand'     => $get('brand'),
            'card_last4'     => $get('last4'),
            'card_country'   => $get('country'),
            'card_funding'   => $get('funding'),
            'card_exp_month' => $get('exp_month'),
            'card_exp_year'  => $get('exp_year'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Prefer hosted invoice URL when it's already on the Charge.
     */
    protected function receiptUrlFromCharge(Charge $charge): ?string
    {
        $url = $charge->receipt_url ?? null;
        return is_string($url) && $url !== '' ? $url : null;
    }
}
