<?php

declare(strict_types=1);

namespace App\Service;

final class PhoneNormalizer
{
    public function normalize(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
            return '+7' . substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '+7' . $digits;
        }

        return null;
    }
}
