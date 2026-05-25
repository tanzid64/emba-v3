<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/applicant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Cloudflare's IP ranges + the tunnel so X-Forwarded-Host /
        // X-Forwarded-Proto from cloudflared and Cloudflare are honoured.
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(fn ($request) => $request->is('applicant*')
            ? route('applicant.login')
            : route('login')
        );

        $middleware->redirectUsersTo(fn ($request) => $request->is('applicant*')
            ? route('applicant.dashboard')
            : '/'
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
