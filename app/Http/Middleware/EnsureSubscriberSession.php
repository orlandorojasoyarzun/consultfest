<?php

namespace App\Http\Middleware;

use App\Models\Subscriber;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the routes that require an authenticated subscriber.
 *
 * The model is "session with subscriber_id, no password" — so this middleware
 * checks both that the session key exists AND that the Subscriber row it
 * points to still exists (migrate:fresh / deletions leave dangling ids).
 */
class EnsureSubscriberSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $subscriberId = session('subscriber_id');

        if (!$subscriberId || !Subscriber::whereKey($subscriberId)->exists()) {
            // Clear stale state so the next request doesn't loop.
            session()->forget('subscriber_id');
            session()->forget('subscriber_email');
            session()->flash('auth-flash', 'Inicia sesión para acceder a tu panel.');

            return redirect()->route('home');
        }

        return $next($request);
    }
}
