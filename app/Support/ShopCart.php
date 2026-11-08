<?php

namespace App\Support;

use App\Models\CartItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * The customer's cart, saved in the `cart_items` table.
 *
 *  - Signed in: the cart belongs to the account.
 *  - Guest: the cart belongs to a random token kept in the `shop_cart` cookie (30 days).
 *
 * Only dishes of the canteen the customer is currently ordering from are counted
 * (the menu item's canteen scope applies), so a cart can never mix two canteens.
 */
class ShopCart
{
    public const COOKIE = 'shop_cart';

    private const COOKIE_MINUTES = 60 * 24 * 30;

    private ?string $newToken = null;

    public function __construct(private Request $request) {}

    public static function for(Request $request): static
    {
        return new static($request);
    }

    /** @return array<int, int> menu_item_id => quantity */
    public function quantities(): array
    {
        $query = $this->query();

        if ($query === null) {
            return [];
        }

        return $query->whereHas('menuItem')->pluck('quantity', 'menu_item_id')
            ->map(fn ($quantity) => (int) $quantity)->all();
    }

    public function count(): int
    {
        return array_sum($this->quantities());
    }

    /** Set a dish's quantity; 0 removes it. */
    public function set(int $menuItemId, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->query()?->where('menu_item_id', $menuItemId)->delete();

            return;
        }

        CartItem::query()->updateOrCreate(
            $this->owner(create: true) + ['menu_item_id' => $menuItemId],
            ['quantity' => $quantity],
        );
    }

    public function clear(): void
    {
        $this->query()?->delete();
    }

    /** Drop saved dishes that belong to another canteen (a cart holds one canteen's dishes only). */
    public function removeOtherCanteens(int $canteenId): void
    {
        $this->query()?->whereDoesntHave('menuItem', fn ($menuItem) => $menuItem
            ->withoutGlobalScopes()->where('menu_items.canteen_id', $canteenId))->delete();
    }

    /** When a guest signs in or registers, their guest cart joins the account's cart. */
    public static function mergeGuestCartInto(Request $request, User $user): void
    {
        $token = $request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            return;
        }

        CartItem::query()->where('guest_token', $token)->get()->each(function (CartItem $guestLine) use ($user): void {
            $mine = CartItem::query()->where('user_id', $user->id)->where('menu_item_id', $guestLine->menu_item_id)->first();

            if ($mine) {
                $mine->update(['quantity' => min(99, $mine->quantity + $guestLine->quantity)]);
                $guestLine->delete();
            } else {
                $guestLine->update(['user_id' => $user->id, 'guest_token' => null]);
            }
        });

        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /** @return Builder<CartItem>|null null = this visitor has no cart yet */
    private function query(): ?Builder
    {
        $owner = $this->owner(create: false);

        return $owner === null ? null : CartItem::query()->where($owner);
    }

    /** @return array<string, mixed>|null */
    private function owner(bool $create): ?array
    {
        if ($user = $this->request->user()) {
            return ['user_id' => $user->id];
        }

        $token = $this->newToken ?? $this->request->cookie(self::COOKIE);

        if (! is_string($token) || $token === '') {
            if (! $create) {
                return null;
            }

            $token = $this->newToken = Str::random(40);
        }

        if ($create) {
            Cookie::queue(self::COOKIE, $token, self::COOKIE_MINUTES);
        }

        return ['guest_token' => $token];
    }
}
