<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiters for public endpoints that mutate state.
     */
    protected function configureRateLimiting(): void
    {
        // Subscribe endpoint: cap registrations/subscriptions per IP.
        // Prevents script-based abuse that creates thousands of Subscriber rows.
        RateLimiter::for('subscribe', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Subscriber-form registration via Livewire shares the same surface.
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // API read endpoints: modest cap to avoid enumeration / DoS via LIKE.
        RateLimiter::for('api-read', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Productions: CRUD for user-registered short films. Higher cap than
        // subscribe because creating/editing is a continuous flow.
        RateLimiter::for('productions', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // OAuth callback: very tight cap because each call creates a session
        // and a (possibly new) Subscriber. A legit user clicks this once.
        RateLimiter::for('oauth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // Logout: same surface as register, share the limiter.
        RateLimiter::for('logout', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Password reset: tighter cap because each request sends a real email
        // via Resend. A tight cap also limits enumeration probes against
        // /forgot-password.
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // FestivalAPI: outbound cap to avoid burning paid credits via a
        // script that hammers the search form. Applied internally by
        // FestivalSearchService (not as a route middleware).
        RateLimiter::for('festivalapi', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}