<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * OAuth 2 flow with Google via Socialite.
 *
 * The OAuth identity is mapped 1:1 onto the existing Subscriber model by
 * email — we never create a parallel User table. A Subscriber is created on
 * first login (if the email doesn't exist yet) and re-used thereafter, so
 * re-logging with Google never duplicates the row.
 *
 * Production and Subscription ownership keep working unchanged: they both
 * still read `session('subscriber_id')`.
 */
class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Google returns an empty message for redirect_uri_mismatch (400).
            // $e->getResponse()?->getBody() carries the actual error code in
            // that case — log everything we can so a wrong URI surfaces.
            $body = null;
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $body = (string) $e->getResponse()->getBody();
            }
            Log::warning('Google OAuth callback failed', [
                'exception' => $e->getMessage(),
                'body' => $body,
                'code' => method_exists($e, 'getCode') ? $e->getCode() : null,
            ]);
            return redirect()->route('home')
                ->with('auth-flash', 'No pudimos completar el inicio de sesión con Google. Inténtalo de nuevo.');
        }

        $email = $googleUser->getEmail();
        $name = $googleUser->getName() ?: 'Usuario';

        if (!$email) {
            return redirect()->route('home')
                ->with('auth-flash', 'Tu cuenta de Google no tiene un email público. Revisa los permisos.');
        }

        // firstOrCreate keyed by email: re-login = same Subscriber, never duplicates.
        // notifications_enabled defaults to true so the user gets value out-of-the-box.
        $subscriber = Subscriber::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => null,
                'notifications_enabled' => true,
            ],
        );

        session(['subscriber_id' => $subscriber->id]);

        return redirect()->route('dashboard')
            ->with('auth-flash', 'Bienvenido, '.$subscriber->name.'.');
    }

    public function logout(): RedirectResponse
    {
        session()->forget('subscriber_id');
        session()->regenerate();

        // Forget the remember cookie if it's there. Cookie::queue attaches
        // a set-cookie header to the next response that expires the cookie.
        return redirect()->route('home')
            ->with('auth-flash', 'Sesión cerrada.')
            ->withCookie(cookie()->forget('remember_subscriber'));
    }
}
