<?php

namespace App\Models;

use Database\Factories\SchoolMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One line of the school's student / employee list.
 *
 * @property int $id
 * @property string $id_number
 * @property string $id_key
 * @property string $name
 * @property string $kind
 * @property int|null $user_id
 * @property bool $is_blocked
 */
class SchoolMember extends Model
{
    /** @use HasFactory<SchoolMemberFactory> */
    use HasFactory;

    public const KINDS = ['student', 'employee'];

    protected $fillable = ['id_number', 'id_key', 'name', 'kind', 'user_id', 'is_blocked', 'blocked_reason'];

    protected function casts(): array
    {
        return ['is_blocked' => 'boolean'];
    }

    protected static function booted(): void
    {
        // The comparison key is always derived from the ID the school wrote.
        static::saving(function (self $member): void {
            $member->id_number = trim($member->id_number);
            $member->id_key = static::keyFor($member->id_number);
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "2024-0001", "2024 0001" and "20240001" are the same ID. */
    public static function keyFor(string $idNumber): string
    {
        return Str::upper((string) preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($idNumber)));
    }

    public static function findByIdNumber(string $idNumber): ?self
    {
        $key = static::keyFor($idNumber);

        return $key === '' ? null : static::query()->where('id_key', $key)->first();
    }

    /**
     * Does the last name the person typed appear in the name on the school list?
     * Whole words only, ignoring case, accents and punctuation ("dela cruz" matches "Juan Dela Cruz").
     */
    public function nameMatches(string $typed): bool
    {
        $wanted = static::words($typed);

        if ($wanted === [] || Str::length(implode('', $wanted)) < 2) {
            return false;
        }

        $listed = static::words($this->name);
        $count = count($wanted);

        for ($i = 0; $i + $count <= count($listed); $i++) {
            if (array_slice($listed, $i, $count) === $wanted) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function words(string $value): array
    {
        $value = preg_replace('/[^a-z\s]/', ' ', Str::lower(Str::ascii($value))) ?? '';

        return array_values(array_filter(preg_split('/\s+/', $value) ?: []));
    }

    public function isRegistered(): bool
    {
        return $this->user_id !== null;
    }

    /** @param Builder<SchoolMember> $query */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'like', '%'.$term.'%')
                ->orWhere('id_number', 'like', '%'.$term.'%')
                ->orWhere('id_key', 'like', '%'.static::keyFor($term).'%');
        });
    }
}
