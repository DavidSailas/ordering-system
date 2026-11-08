<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCanteen;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use BelongsToCanteen, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'canteen_id',
        'category_id',
        'name',
        'description',
        'price',
        'image_path',
        'is_available',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @param  Builder<MenuItem>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_available', true);
    }

    /**
     * Public URL of the dish photo, or null when none has been uploaded.
     *
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->image_path ? asset('storage/'.$this->image_path) : null,
        );
    }
}
