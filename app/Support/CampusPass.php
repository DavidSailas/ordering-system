<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * The "campus pass": a QR code (or short code) posted inside the school. Scanning it lets that phone
 * place orders for a while. Visitors without it can still look at the menu but cannot check out.
 * Regenerating the code cancels every pass handed out so far.
 */
class CampusPass
{
    public const COOKIE = 'campus_pass';

    /** Letters and digits that cannot be confused when typed from a poster. */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function enabled(): bool
    {
        return SiteSetting::get('campus_pass.enabled', '0') === '1';
    }

    public static function setEnabled(bool $on): void
    {
        static::secret();   // make sure a secret exists before the pass is switched on
        SiteSetting::put('campus_pass.enabled', $on ? '1' : '0');
    }

    /** The secret part of the QR link. Created the first time it is needed. */
    public static function secret(): string
    {
        $secret = SiteSetting::get('campus_pass.secret');

        if (! $secret) {
            static::regenerate();
            $secret = (string) SiteSetting::get('campus_pass.secret');
        }

        return $secret;
    }

    /** The short code people can type when they cannot scan. */
    public static function code(): string
    {
        static::secret();

        return (string) SiteSetting::get('campus_pass.code');
    }

    public static function version(): int
    {
        return (int) SiteSetting::get('campus_pass.version', '1');
    }

    public static function link(): string
    {
        return url('/pass/'.static::secret());
    }

    public static function days(): int
    {
        return max(1, (int) config('restaurant.campus_pass.days', 30));
    }

    /** New QR link + short code. Every pass given out before this moment stops working. */
    public static function regenerate(): void
    {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= static::CODE_ALPHABET[random_int(0, strlen(static::CODE_ALPHABET) - 1)];
        }

        SiteSetting::put('campus_pass.secret', Str::random(24));
        SiteSetting::put('campus_pass.code', $code);
        SiteSetting::put('campus_pass.version', (string) (static::version() + 1));
    }

    public static function matchesSecret(string $given): bool
    {
        return hash_equals(static::secret(), $given);
    }

    public static function matchesCode(string $given): bool
    {
        $given = Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', $given));

        return $given !== '' && hash_equals(static::code(), $given);
    }

    /** Hand this browser a pass (an encrypted cookie that remembers which version of the code it came from). */
    public static function grant(Request $request): void
    {
        $days = static::days();
        $value = static::version().'.'.now()->addDays($days)->timestamp;

        Cookie::queue(Cookie::make(static::COOKIE, $value, $days * 1440, '/', null, $request->isSecure(), true, false, 'lax'));
    }

    public static function has(Request $request): bool
    {
        $parts = explode('.', (string) $request->cookie(static::COOKIE), 2);

        return count($parts) === 2
            && (int) $parts[0] === static::version()
            && (int) $parts[1] > now()->timestamp;
    }

    /** May this request check out? Always yes when the pass is off, and for signed-in staff. */
    public static function allows(Request $request): bool
    {
        if (! static::enabled()) {
            return true;
        }

        $user = $request->user();

        if ($user && $user->role !== UserRole::Customer) {
            return true;
        }

        return static::has($request);
    }
}
