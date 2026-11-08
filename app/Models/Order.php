<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\OrderType;
use Carbon\CarbonInterface;
use App\Models\Concerns\BelongsToCanteen;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use BelongsToCanteen, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'dining_table_id',
        'canteen_id',
        'canteen_number',
        'customer_name',
        'phone',
        'building',
        'room',
        'type',
        'status',
        'notes',
        'total',
        'paid_at',
        'paid_by',
        'cash_received',
        'payment_method',
        'payment_reference',
        'discount',
        'amount_paid',
        'refunded_at',
        'refunded_by',
        'refund_reason',
        'canceled_at',
        'payment_choice',
        'customer_payment_ref',
        'payment_submitted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'total' => 'decimal:2',
            'paid_at' => 'datetime',
            'cash_received' => 'decimal:2',
            'discount' => 'decimal:2', 'amount_paid' => 'decimal:2', 'refunded_at' => 'datetime', 'canceled_at' => 'datetime', 'payment_submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The staff member who recorded the payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function isRefunded(): bool
    {
        return $this->refunded_at !== null;
    }

    /** The payment method as an enum; orders paid before methods were recorded count as cash. */
    public function paymentMethodOrCash(): PaymentMethod
    {
        return PaymentMethod::tryFrom((string) $this->payment_method) ?? PaymentMethod::Cash;
    }

    /** What the customer was (or will be) charged after any discount. */
    public function amountCharged(): float
    {
        if ($this->amount_paid !== null) {
            return (float) $this->amount_paid;
        }

        return max(0.0, (float) $this->total - (float) $this->discount);
    }

    public function changeDue(): float
    {
        return max(0.0, (float) $this->cash_received - $this->amountCharged());
    }

    protected static function booted(): void
    {
        // Each canteen numbers its own orders: #0001, #0002, ...
        static::creating(function (Order $order): void {
            if ($order->canteen_id && ! $order->canteen_number) {
                $order->canteen_number = ((int) static::withoutGlobalScopes()->where('canteen_id', $order->canteen_id)->max('canteen_number')) + 1;
            }
        });
    }

    /**
     * Where the customer wants the order brought, e.g. "Main Building · Room 204".
     *
     * @return Attribute<string|null, never>
     */
    protected function location(): Attribute
    {
        return Attribute::get(function (): ?string {
            $parts = array_filter([
                $this->building,
                $this->room ? 'Room '.$this->room : null,
            ]);

            return $parts ? implode(' · ', $parts) : null;
        });
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Orders that count towards revenue: everything except canceled orders.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeCountedAsSales(Builder $query): void
    {
        $query->where('status', '!=', OrderStatus::Canceled->value);
    }

    /**
     * @param  Builder<Order>  $query
     */
    public function scopePlacedBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Zero-padded order reference shown on the dashboard, e.g. 000042.
     *
     * @return Attribute<string, never>
     */
    protected function number(): Attribute
    {
        return Attribute::get(fn (): string => str_pad((string) $this->id, 6, '0', STR_PAD_LEFT));
    }
}
