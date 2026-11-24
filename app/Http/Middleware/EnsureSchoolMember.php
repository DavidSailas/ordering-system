<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only verified school people may place and follow orders: visitors have to sign in, and a customer
 * account has to be tied to an ID on the school list. (Staff accounts are created by the admin.)
 */
class EnsureSchoolMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'))
                ->with('status', 'Please sign in with your school account to order. New here? Create an account with your school ID.');
        }

        if ($user->role === UserRole::Customer && ! $user->schoolMember()->exists()) {
            return redirect()->guest(route('school-id.show'))
                ->with('status', 'Please confirm your school ID to place orders.');
        }

        return $next($request);
    }
}
