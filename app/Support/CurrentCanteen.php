<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Canteen;

/**
 * Answers "which canteen is this request about?".
 *
 *  - Staff area (admin / owner / cashier / runner pages): the staff member's own canteen. The
 *    overall administrator (an admin with no canteen) works in the canteen
 *    chosen with the switcher.
 *  - Customer shop (menu, cart, checkout): the canteen the customer picked.
 *  - Everything else (login, "my orders", console, seeders): null = no filter.
 *
 * id() returns 0 when a request belongs to a canteen area but none is chosen,
 * so queries return nothing instead of leaking another canteen's data.
 */
class CurrentCanteen
{
    public const STAFF_ROUTES = ['admin.*', 'dashboard', 'orders.*', 'cashier.*', 'runner.*'];

    public const SHOP_ROUTES = ['home', 'shop.menu', 'shop.cart', 'shop.cart.update', 'shop.checkout', 'shop.purchase'];

    public static function id(): ?int
    {
        $request = app()->bound('request') ? app('request') : null;

        if (! $request || ! $request->route()) {
            return null;
        }

        if ($request->attributes->has('canteen.id')) {
            return $request->attributes->get('canteen.id');
        }

        $id = static::resolve($request);
        $request->attributes->set('canteen.id', $id);

        return $id;
    }

    /** The canteen to show in headers/branding (also on pages that are not filtered, like "my orders"). */
    public static function brand(): ?Canteen
    {
        $request = app()->bound('request') ? app('request') : null;

        if (! $request || ! $request->route()) {
            return null;
        }

        $id = static::id();

        if ($id === null && $request->hasSession()) {
            $id = (int) $request->session()->get('shop.canteen_id', 0);
        }

        return $id ? Canteen::query()->find($id) : null;
    }

    private static function resolve($request): ?int
    {
        if ($request->routeIs(...self::STAFF_ROUTES)) {
            $user = $request->user();

            if (! $user) {
                return null;
            }

            if (! in_array($user->role, [UserRole::Admin, UserRole::Owner, UserRole::Cashier, UserRole::Runner], true)) {
                return 0;
            }

            if ($user->canteen_id) {
                return (int) $user->canteen_id;
            }

            // owners, cashiers and runners must always belong to a canteen
            if ($user->role !== UserRole::Admin) {
                return 0;
            }

            // overall administrator: the canteen picked with the switcher, else the first one
            $picked = $request->hasSession() ? (int) $request->session()->get('staff.canteen_id', 0) : 0;

            if ($picked && Canteen::query()->whereKey($picked)->exists()) {
                return $picked;
            }

            return (int) Canteen::query()->orderBy('sort_order')->orderBy('id')->value('id');
        }

        if ($request->routeIs(...self::SHOP_ROUTES)) {
            return $request->hasSession() ? (int) $request->session()->get('shop.canteen_id', 0) : 0;
        }

        return null;
    }
}
