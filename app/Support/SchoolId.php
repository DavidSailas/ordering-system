<?php

namespace App\Support;

use App\Models\SiteSetting;

/** Whether customers must tap their school ID to order. Off by default; the administrator switches it on. */
class SchoolId
{
    public static function required(): bool
    {
        $stored = SiteSetting::get('school_id.required');

        return $stored === null ? (bool) config('restaurant.require_school_id', false) : $stored === '1';
    }

    public static function setRequired(bool $on): void
    {
        SiteSetting::put('school_id.required', $on ? '1' : '0');
    }
}
