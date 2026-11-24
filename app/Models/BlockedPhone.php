<?php

namespace App\Models;

use App\Support\PhoneKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone number that may not place orders any more (prank or no-show orders).
 *
 * @property int $id
 * @property string $phone_key
 * @property string $phone
 * @property string|null $reason
 */
class BlockedPhone extends Model
{
    protected $fillable = ['phone_key', 'phone', 'reason', 'blocked_by'];

    /** @return BelongsTo<User, $this> */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public static function isBlocked(?string $phone): bool
    {
        $key = PhoneKey::from((string) $phone);

        return $key !== '' && static::query()->where('phone_key', $key)->exists();
    }

    public static function block(string $phone, ?string $reason = null, ?User $by = null): ?self
    {
        $key = PhoneKey::from($phone);

        if ($key === '') {
            return null;
        }

        return static::query()->firstOrCreate(
            ['phone_key' => $key],
            ['phone' => mb_substr(trim($phone), 0, 40), 'reason' => $reason ? mb_substr($reason, 0, 250) : null, 'blocked_by' => $by?->id],
        );
    }
}
