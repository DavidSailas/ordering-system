<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An automatic pause: a phone number that missed too many orders cannot order until ends_at.
 * It lifts by itself, and the administrator can lift it earlier.
 *
 * @property string $phone_key
 * @property string $phone
 * @property string $reason
 * @property int $strikes
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property \Illuminate\Support\Carbon|null $lifted_at
 */
class PhoneSuspension extends Model
{
    /** @var list<string> */
    protected $fillable = ['phone_key', 'phone', 'school_member_id', 'reason', 'strikes', 'starts_at', 'ends_at', 'lifted_at', 'lifted_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'lifted_at' => 'datetime', 'strikes' => 'integer'];
    }

    /** @param Builder<PhoneSuspension> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('lifted_at')->where('ends_at', '>', now());
    }

    /** @return BelongsTo<SchoolMember, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(SchoolMember::class, 'school_member_id');
    }

    /** What the pause is on: the person's name and school ID, or the phone number. */
    public function label(): string
    {
        return $this->member ? $this->member->name.' ('.$this->member->id_number.')' : $this->phone;
    }

    /** @return BelongsTo<User, $this> */
    public function lifter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }
}
