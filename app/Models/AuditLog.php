<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCanteen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Who changed what, and when" for sensitive settings. Rows are only ever added, never edited.
 *
 * @property string $action
 * @property string $summary
 * @property array<string, mixed>|null $old
 * @property array<string, mixed>|null $new
 */
class AuditLog extends Model
{
    use BelongsToCanteen;

    /** @var list<string> */
    protected $fillable = ['canteen_id', 'user_id', 'action', 'summary', 'old', 'new', 'ip_address'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['old' => 'array', 'new' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public static function record(string $action, string $summary, array $old = [], array $new = []): self
    {
        $request = app()->bound('request') ? app('request') : null;

        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'summary' => mb_substr($summary, 0, 255),
            'old' => $old ?: null,
            'new' => $new ?: null,
            'ip_address' => $request?->ip(),
        ]);
    }
}
