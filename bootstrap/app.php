<?php

use App\Http\Middleware\ApplyCanteenContext;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\RequireCampusPass;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway terminates HTTPS at its proxy; trust it so Laravel builds https:// URLs.
        // TRUSTED_PROXIES can list the hosting provider's proxy addresses (comma separated). Left empty it trusts
        // every proxy, which is fine on Railway; the limits that matter most (strikes, ID lock-out) do not use the IP anyway.
        $middleware->trustProxies(at: ($proxies = env('TRUSTED_PROXIES', '*')) === '*' ? '*' : array_map('trim', explode(',', (string) $proxies)));

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'super_admin' => EnsureSuperAdmin::class,
            'campus_pass' => RequireCampusPass::class,
        ]);

        $middleware->web(append: [
            EnsureUserIsActive::class,
            ApplyCanteenContext::class,
        ]);

        $middleware->redirectUsersTo(
            fn (Request $request): string => route($request->user()->role->homeRoute()),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
