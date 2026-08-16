<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Email-driven password reset for Subscribers.
 *
 * We don't use Laravel's built-in broker because the project doesn't use
 * the User model — Subscriber is the identity model. The contract is
 * identical: token in URL, hash in DB, 60 minute expiry.
 *
 * Anti-enumeration: `sendResetLinkEmail` always returns the same success
 * message whether the email exists or not, so attackers can't probe the
 * subscriber table.
 */
class PasswordResetController extends Controller
{
    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $subscriber = Subscriber::where('email', $validated['email'])->first();

        if ($subscriber) {
            $token = Str::random(64);

            // Hash before storing so a DB leak doesn't expose usable tokens.
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $validated['email']],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            $resetUrl = route('auth.password.reset', ['token' => $token, 'email' => $validated['email']]);

            $subscriber->notify(new ResetPasswordNotification($resetUrl, $subscriber->name));
        }

        // Same response either way — never leak which emails are registered.
        return back()->with('auth-flash', 'Si el email está registrado, te enviamos un link para restablecer tu contraseña.');
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$record || !Hash::check($validated['token'], $record->token)) {
            return back()->withErrors(['email' => 'El link de recuperación es inválido. Solicita uno nuevo.']);
        }

        // 60-minute expiry, matches config/auth.php.
        $createdAt = $record->created_at
            ? \Carbon\Carbon::parse($record->created_at)
            : null;

        if (!$createdAt || $createdAt->lt(now()->subMinutes(60))) {
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

            return back()->withErrors(['email' => 'El link ha expirado. Solicita uno nuevo.']);
        }

        $subscriber = Subscriber::where('email', $validated['email'])->first();
        if (!$subscriber) {
            return back()->withErrors(['email' => 'No encontramos esa cuenta.']);
        }

        // Cast `hashed` on the model handles bcrypt here.
        $subscriber->password = $validated['password'];
        $subscriber->save();

        // Single-use token — delete so it can't be reused.
        DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        session()->regenerate();
        session([
            'subscriber_id' => $subscriber->id,
            // See AuthController::login() — same reason.
            'subscriber_email' => $subscriber->email,
        ]);

        return redirect()->route('dashboard')
            ->with('auth-flash', 'Contraseña actualizada. Bienvenido, '.$subscriber->name.'.');
    }
}
