<?php

namespace App\Http\Middleware;

use App\Support\CampusPass;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Checking out needs the campus pass (when the administrator has switched it on). */
class RequireCampusPass
{
    public function handle(Request $request, Closure $next): Response
    {
        if (CampusPass::allows($request)) {
            return $next($request);
        }

        // Bring them back to where they were going once they have scanned the code.
        $request->session()->put('url.intended', $request->isMethod('GET') ? $request->fullUrl() : route('shop.checkout'));

        return redirect()->route('pass.show');
    }
}
