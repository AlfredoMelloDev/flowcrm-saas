<?php

use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // SetTenantContext is applied per-route (routes/api.php), right after
        // auth:sanctum. That alone isn't enough: Laravel sorts middleware by
        // a fixed priority list, and SubstituteBindings (which resolves any
        // {model} route parameter, e.g. {lead}) is priority-ranked ahead of
        // any middleware not in that list — regardless of array order. Without
        // this, route-model-binding for a tenant-scoped model would run
        // before the tenant is set for the request. This pins SetTenantContext
        // to run before SubstituteBindings in the actual execution order.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetTenantContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
