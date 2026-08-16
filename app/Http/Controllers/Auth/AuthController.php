<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Notifications\WelcomeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Email-based login and registration.
 *
 * Passwords are stored as bcrypt via the model's `hashed` cast. The OAuth
 * callback in GoogleController maps a Google identity 1:1 onto Subscriber
 * by email and leaves `password` null.
 *
 *  - login(): looks the email up. If found AND has a password, verifies it
 *    and starts the session. If found but no password (Google-only account),
 *    surfaces a helpful error pointing the user back to Google. If not
 *    found at all, redirects to /register with the email pre-filled.
 *  - register(): validates (incl. unique email and password confirmation)
 *    and creates a Subscriber. A duplicate email surfaces as a validation
 *    error in the form.
 *
 * When "remember" is checked, a long-lived HMAC cookie is queued that the
 * CheckRememberCookie middleware can read on the next visit to re-establish
 * a session without the user having to log in again.
 */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ]);

        $subscriber = Subscriber::where('email', $validated['email'])->first();

        if (!$subscriber) {
            return redirect()->route('auth.register')
                ->withInput(['email' => $validated['email']])
                ->with('auth-flash', 'No encontramos una cuenta con ese email. Crea una cuenta para continuar.');
        }

        if (!$subscriber->password) {
            // OAuth-only account — explains the situation without forcing
            // the user to do anything new.
            return back()->withInput(['email' => $validated['email']])
                ->withErrors(['password' => 'Esta cuenta fue creada con Google. Continúa con Google o usa "Olvidé mi password" para crear uno.']);
        }

        if (!Hash::check($validated['password'], $subscriber->password)) {
            return back()->withInput(['email' => $validated['email']])
                ->withErrors(['password' => 'Email o contraseña incorrectos.']);
        }

        session()->regenerate(); // prevent session fixation
        session([
            'subscriber_id' => $subscriber->id,
            // The subscribe modal reads `subscriber_email` from the session
            // to pre-fill the "we'll notify you at…" block AND to flip the
            // $subscribeSubscriberLoggedIn flag. Without this key, an
            // authenticated user still sees "Necesitás tener una cuenta"
            // because the modal's `if loggedIn && email` guard fails on
            // null. Setting both keeps the Livewire component in sync.
            'subscriber_email' => $subscriber->email,
        ]);

        $response = redirect()->route('dashboard')
            ->with('auth-flash', 'Bienvenido, '.$subscriber->name.'.');

        if ($validated['remember'] ?? false) {
            // HMAC the subscriber_id so an attacker can't tamper with the
            // cookie to log in as another user. The cookie carries the hash
            // only — the id is never on the client side.
            $cookie = Cookie::make(
                'remember_subscriber',
                hash_hmac('sha256', (string) $subscriber->id, config('app.key')),
                60 * 24 * 30, // 30 days
                '/',
                null,
                false, // not secure in dev
                true,  // httpOnly
                false,
                'lax',
            );
            $response->cookie($cookie);
        }

        return $response;
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'production_company' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:subscribers,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $subscriber = Subscriber::create([
            'name' => $validated['name'],
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'phone' => null,
            'production_company' => $validated['production_company'] ?? null,
            // 'hashed' cast on the model handles bcrypt here.
            'password' => $validated['password'],
            'notifications_enabled' => true,
        ]);

        // Welcome email — queued so it doesn't block the response. The user
        // gets redirected to /dashboard immediately; the email goes out
        // async via the queue worker.
        $subscriber->notify(new WelcomeNotification($subscriber));

        session()->regenerate();
        session([
            'subscriber_id' => $subscriber->id,
            // See login() — same reason: the modal needs subscriber_email
            // to flip its "logged in" branch.
            'subscriber_email' => $subscriber->email,
        ]);

        return redirect()->route('dashboard')
            ->with('auth-flash', 'Cuenta creada. Bienvenido, '.$subscriber->name.'.');
    }
}
