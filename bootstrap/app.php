<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // The `channels:` shorthand on withRouting() always registers
    // /broadcasting/auth under the `web` middleware group (session + CSRF),
    // which never authenticates a Bearer token — every private/presence
    // channel subscription would silently fail to authorize. withBroadcasting()
    // lets us pin it to `auth:sanctum` instead (see ADR-0011).
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // This is a token-only API backend with no Blade/session-based web
        // UI (the frontend is a separate repo) — /broadcasting/auth is the
        // one built-in route living outside the api/* prefix, and without
        // this it also needs JSON error rendering: an unauthenticated
        // request otherwise hits Laravel's default "redirect to the `login`
        // route" handling, which doesn't exist here, turning a plain 401
        // into a 500 (see ADR-0011).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('broadcasting/*'),
        );
    })->create();
