<?php

namespace App\Support;

class WhatsApp
{
    /**
     * A wa.me link that opens a chat with $text prefilled, or null without a
     * usable phone. A 10-digit Colombian mobile (3xx…) gets the 57 country code.
     */
    public static function url(?string $phone, string $text): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            $digits = '57'.$digits;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }
}
