<?php

use App\Http\Middleware\CheckRememberCookie;
use App\Http\Middleware\EnsureSubscriberSession;
use App\Http\Middleware\SecurityHeaders;
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
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway's Hikari edge proxy terminates TLS before reaching the PHP
        // container, so the request that artisan serve sees is plain http://.
        // Without trusting the proxy, Laravel reports scheme=http to helpers
        // like asset() / @vite(), and every asset gets a mixed-content http://
        // URL inside our HTTPS page — browsers silently block the CSS/JS/fonts.
        // Trusting Railway's proxy lets Laravel detect scheme=https from
        // X-Forwarded-Proto so asset URLs come out correctly.
        $middleware->trustProxies(at: '*');
        $middleware->append(SecurityHeaders::class);
        // Empty form fields arrive as "" — convert them to null so the DB
        // sees a clean "no value" instead of a meaningless empty string.
        // Skip password fields so bcrypt hashing still runs on the literal.
        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request) => $request->is('*/password*'),
        ]);
        $middleware->alias([
            'auth.subscriber' => EnsureSubscriberSession::class,
            'remember.subscriber' => CheckRememberCookie::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
