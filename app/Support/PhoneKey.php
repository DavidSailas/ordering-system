<?php

namespace App\Support;

/** Turns a typed phone number into a comparable key, so "0917 123 4567" and "+63 917-123-4567" match. */
class PhoneKey
{
    public static function from(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // Philippine mobile numbers: the last 10 digits identify the line (917 123 4567).
        return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
    }
}
