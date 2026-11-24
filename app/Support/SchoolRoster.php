<?php

namespace App\Support;

use App\Models\SchoolMember;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Checks a school ID + last name against the school list.
 *
 *  - claimable(): used when a customer registers an account.
 *  - verify():    used at checkout when a student or teacher taps their ID. The last name is needed too, so
 *                 typing someone else's ID number is not enough, and wrong guesses are locked out for a while.
 */
class SchoolRoster
{
    /** Wrong tries allowed per ID number, and per connection, within the lock-out time. */
    private const MAX_WRONG_PER_ID = 5;

    private const MAX_WRONG_PER_IP = 20;

    private const LOCK_SECONDS = 600;

    /**
     * @return SchoolMember|string the matching, free list entry, or a message for the person
     */
    public static function claimable(string $idNumber, string $lastName): SchoolMember|string
    {
        $member = SchoolMember::findByIdNumber($idNumber);

        // One message for "no such ID" and "wrong last name", so the form cannot be used to guess IDs.
        if (! $member || ! $member->nameMatches($lastName)) {
            return 'We could not match that ID number and last name to the school list. Please check them, or ask the school office.';
        }

        if ($member->is_blocked) {
            return 'This ID cannot be used to order right now. Please contact the school office.';
        }

        if ($member->isRegistered()) {
            return 'This ID already has an account. Please log in instead.';
        }

        return $member;
    }

    /**
     * @return SchoolMember|string the person on the list, or a message to show
     */
    public static function verify(string $idNumber, string $lastName, ?string $ip = null): SchoolMember|string
    {
        $id = SchoolMember::keyFor($idNumber);
        $limits = ['school-id:ip:'.($ip ?: 'unknown') => self::MAX_WRONG_PER_IP] + ($id !== '' ? ['school-id:id:'.$id => self::MAX_WRONG_PER_ID] : []);

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return 'Too many tries. Please wait a few minutes, or ask the cashier for help.';
            }
        }

        $member = SchoolMember::findByIdNumber($idNumber);

        // One message for "no such ID" and "wrong last name", so nobody can use the form to find valid IDs.
        if (! $member || ! $member->nameMatches($lastName)) {
            foreach (array_keys($limits) as $key) {
                RateLimiter::hit($key, self::LOCK_SECONDS);
            }

            return 'We could not match that ID number and last name. Please check them and try again.';
        }

        if ($member->is_blocked) {
            return 'This ID cannot be used to order right now. Please talk to the canteen staff.';
        }

        RateLimiter::clear('school-id:id:'.$id);

        return $member;
    }
}
