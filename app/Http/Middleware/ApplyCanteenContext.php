<?php

namespace App\Http\Middleware;

use App\Models\Canteen;
use App\Support\CurrentCanteen;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every web request:
 *  1. In the customer shop, makes sure a canteen has been picked (one canteen = automatic,
 *     several = send the customer to the "Choose a canteen" page).
 *  2. Applies the current canteen's name, logo, colour, payment accounts and open/closed
 *     state to config() and shares it with the views.
 */
class ApplyCanteenContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $request->routeIs(...CurrentCanteen::SHOP_ROUTES)) {
            $picked = (int) $request->session()->get('shop.canteen_id', 0);

            if (! $picked || ! Canteen::query()->whereKey($picked)->exists()) {
                $request->session()->forget('shop.canteen_id');
                $count = Canteen::query()->count();

                if ($count === 1) {
                    $request->session()->put('shop.canteen_id', (int) Canteen::query()->value('id'));
                    $request->attributes->remove('canteen.id');
                } elseif ($count > 1) {
                    return redirect()->route('shop.canteens');
                }
            }
        }

        $canteen = CurrentCanteen::brand();

        if ($canteen) {
            $canteen->applyToConfig();
        }

        view()->share('currentCanteen', $canteen);
        view()->share('canteenAccentCss', $canteen?->accentCss());
        view()->share('canteenCount', Canteen::query()->count());

        return $next($request);
    }
}
