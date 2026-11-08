<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'password', 'role', 'canteen_id', 'is_active'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** The canteen this staff member works for (null for customers and the overall administrator). */
    public function canteen(): BelongsTo
    {
        return $this->belongsTo(Canteen::class);
    }

    /** Overall administrator: an admin who is not tied to one canteen. */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin() && $this->canteen_id === null;
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function isCashier(): bool
    {
        return $this->role === UserRole::Cashier;
    }

    public function isRunner(): bool
    {
        return $this->role === UserRole::Runner;
    }

    /** Admin or owner: may manage a canteen's menu, staff, reports and refunds. */
    public function isManager(): bool
    {
        return $this->isAdmin() || $this->isOwner();
    }

    /** Each canteen has one active owner at a time. */
    public static function canteenHasActiveOwner(int $canteenId, ?User $except = null): bool
    {
        return static::query()
            ->where('role', UserRole::Owner->value)
            ->where('canteen_id', $canteenId)
            ->where('is_active', true)
            ->when($except?->exists, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
