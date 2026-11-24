<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderStrike;
use App\Models\PhoneSuspension;
use App\Models\SchoolMember;

/**
 * Automatic strikes, with no logins and no work for the staff.
 *
 *  - An order that nobody paid for in time is a missed order (a "strike").
 *  - Strikes are counted for the phone number AND, when a school ID was tapped, for that ID. The ID is the
 *    stronger one: a new SIM or a friend's phone does not start the count again.
 *  - After STRIKES_MAX missed orders (default 3) the phone number / ID is paused for STRIKES_PAUSE_DAYS (default 3).
 *  - The pause lifts by itself. After it, the count starts again from zero.
 *  - Strikes also fade: only the last STRIKES_WINDOW_DAYS (default 30) count.
 *  - An order the customer really tried to pay (a GCash / Maya reference was sent) is never a strike.
 *
 * Set ORDER_STRIKES_MAX=0 to switch strikes off.
 */
class Strikes
{
    public static function max(): int
    {
        return max(0, (int) config('restaurant.strikes.max', 3));
    }

    public static function enabled(): bool
    {
        return static::max() > 0;
    }

    public static function windowDays(): int
    {
        return max(1, (int) config('restaurant.strikes.window_days', 30));
    }

    public static function pauseDays(): int
    {
        return max(1, (int) config('restaurant.strikes.pause_days', 3));
    }

    /** The pause in force for this phone number, if any. */
    public static function suspension(?string $phone): ?PhoneSuspension
    {
        $key = PhoneKey::from((string) $phone);

        if ($key === '') {
            return null;
        }

        return PhoneSuspension::query()->active()->whereNull('school_member_id')->where('phone_key', $key)->orderByDesc('ends_at')->first();
    }

    /** The pause in force for this school ID, if any. */
    public static function memberSuspension(?SchoolMember $member): ?PhoneSuspension
    {
        return $member ? PhoneSuspension::query()->active()->where('school_member_id', $member->id)->orderByDesc('ends_at')->first() : null;
    }

    /** The pause that stops this customer from ordering: the school ID's or the phone number's (the longer one). */
    public static function blockingPause(?string $phone, ?SchoolMember $member = null): ?PhoneSuspension
    {
        return collect([static::memberSuspension($member), static::suspension($phone)])->filter()->sortByDesc('ends_at')->first();
    }

    /** Missed orders that still count for a phone number. */
    public static function current(string $phoneKey): int
    {
        return $phoneKey === '' ? 0 : static::count('phone_key', $phoneKey);
    }

    /** Missed orders that still count for a school ID. */
    public static function currentForMember(int $memberId): int
    {
        return $memberId === 0 ? 0 : static::count('school_member_id', $memberId);
    }

    /** Counts a missed order and, when it is one too many, pauses the phone number and / or the school ID. */
    public static function record(Order $order, string $reason): void
    {
        $key = (string) $order->phone_key;
        $memberId = $order->school_member_id ? (int) $order->school_member_id : null;

        if (! static::enabled() || ($key === '' && ! $memberId)) {
            return;
        }

        OrderStrike::create([
            'phone_key' => $key,
            'phone' => mb_substr((string) $order->phone, 0, 40),
            'order_id' => $order->id,
            'school_member_id' => $memberId,
            'reason' => mb_substr($reason, 0, 160),
        ]);

        if ($key !== '' && ($count = static::current($key)) >= static::max() && ! static::suspension($order->phone)) {
            PhoneSuspension::create([
                'phone_key' => $key,
                'phone' => mb_substr((string) $order->phone, 0, 40),
                'reason' => $count.' orders were not paid in time',
                'strikes' => $count,
                'starts_at' => now(),
                'ends_at' => now()->addDays(static::pauseDays()),
            ]);
        }

        $member = $memberId ? SchoolMember::query()->find($memberId) : null;

        if ($member && ($count = static::currentForMember($member->id)) >= static::max() && ! static::memberSuspension($member)) {
            PhoneSuspension::create([
                'phone_key' => $key,
                'phone' => mb_substr($member->id_number, 0, 40),
                'school_member_id' => $member->id,
                'reason' => $count.' orders were not paid in time',
                'strikes' => $count,
                'starts_at' => now(),
                'ends_at' => now()->addDays(static::pauseDays()),
            ]);
        }
    }

    /** Recent strikes made after the last pause began, for one phone number or one school ID. */
    private static function count(string $column, string|int $value): int
    {
        $since = now()->subDays(static::windowDays());

        $lastPause = PhoneSuspension::query()
            ->where($column, $value)
            ->when($column === 'phone_key', fn ($query) => $query->whereNull('school_member_id'))
            ->orderByDesc('starts_at')
            ->first();

        if ($lastPause && $lastPause->starts_at->gt($since)) {
            $since = $lastPause->starts_at;
        }

        return OrderStrike::query()->where($column, $value)->where('created_at', '>', $since)->count();
    }
}
