<?php

namespace App\Http\Middleware;

use App\Models\Subscriber;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-establishes a session from the long-lived remember_me cookie before
 * route-level middleware (eg EnsureSubscriberSession) checks for it.
 *
 * The cookie carries an HMAC of the subscriber id, NOT the id itself. We
 * iterate the subscribers table once and compare each id's HMAC against
 * the cookie value. The match-set is a single column lookup, capped by
 * Subscriber::count() — for a small user base this is fine. For a large
 * one, swap the iteration for a single SELECT with HMAC computed in PHP.
 *
 * On hit, we set session('subscriber_id') and let the rest of the pipeline
 * run as usual. On miss, we silently ignore the cookie — a stolen cookie
 * with the wrong HMAC yields no info to the attacker.
 */
class CheckRememberCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('subscriber_id')) {
            // Already logged in — remember cookie is irrelevant.
            return $next($request);
        }

        $cookie = $request->cookie('remember_subscriber');
        if (!$cookie) {
            return $next($request);
        }

        $appKey = config('app.key');
        if (!$appKey) {
            return $next($request);
        }

        // Subscriber table is small in this MVP. A single roundtrip via
        // pluck('id') is enough — we never load full rows.
        $subscriberIds = Subscriber::pluck('id');

        foreach ($subscriberIds as $id) {
            $expected = hash_hmac('sha256', (string) $id, $appKey);
            if (hash_equals($expected, (string) $cookie)) {
                // Fetch the email here so the subscribe modal can read it
                // from the session on the next request — without this, a
                // user restored from the remember cookie would see
                // "Necesitás tener una cuenta" even though they're logged in.
                $subscriber = Subscriber::find($id);
                session([
                    'subscriber_id' => (int) $id,
                    'subscriber_email' => $subscriber?->email,
                ]);
                break;
            }
        }

        return $next($request);
    }
}
