<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only the overall administrator (an admin who does not belong to one canteen). */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->isSuperAdmin(), 403, 'Only the overall administrator can do this.');

        return $next($request);
    }
}
