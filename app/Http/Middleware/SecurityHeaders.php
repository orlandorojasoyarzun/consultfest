<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds defensive response headers to every request.
 *
 * CSP is permissive enough for Vite/Livewire but blocks framing and
 * inline scripts that are not explicitly allowed. 'unsafe-inline' for
 * script-src is allowed in BOTH dev and prod because the app uses
 * inline `<script>` blocks for things that aren't worth a separate
 * asset pipeline entry: the theme-detection IIFE in <head>, the
 * `livewireFire` partial that bridges inline `onclick` handlers to
 * Livewire v4 sibling components (no `Livewire.dispatch()` global in
 * v4 — see livewire-sibling-dispatch-pattern memory), and the
 * `livewire:init` hooks inside each native-<dialog> modal
 * (`unsubscribeFestivalModal`, `deleteProductionModal`,
 * `festivalSubscribeModal`). The proper fix is per-script nonces, but
 * the surface is small and there's no user-controlled content reaching
 * any of these blocks, so we accept the XSS-risk tradeoff for an MVP.
 *
 * `'unsafe-eval'` stays dev-only: Vite's HMR client evaluates dynamic
 * code; the production bundle is static.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $isProduction = app()->environment('production');

        $csp = [
            "default-src 'self'",
            "img-src 'self' data: https:",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com",
            "font-src 'self' https://fonts.bunny.net https://fonts.gstatic.com data:",
            $isProduction
                ? "script-src 'self' 'unsafe-inline'"
                : "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        if ($isProduction) {
            // Only meaningful over HTTPS, but harmless over HTTP.
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}