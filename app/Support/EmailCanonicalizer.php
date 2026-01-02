<?php

namespace App\Support;

final class EmailCanonicalizer
{
    public static function canonicalize(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $email = trim($email);

        if ($email === '') {
            return null;
        }

        $email = mb_strtolower($email);

        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return $email;
        }

        [$local, $domain] = $parts;

        // Strip +tag for ALL domains (your requirement)
        if (($plusPos = strpos($local, '+')) !== false) {
            $local = substr($local, 0, $plusPos);
        }

        // Gmail dot-ignoring + googlemail normalization
        if ($domain === 'gmail.com' || $domain === 'googlemail.com') {
            $domain = 'gmail.com';
            $local = str_replace('.', '', $local);
        }

        return $local . '@' . $domain;
    }
}
