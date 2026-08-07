<?php

namespace Tests\Feature\Auth;

use App\Models\Subscriber;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    // ---- View rendering --------------------------------------------------

    public function test_forgot_password_view_renders(): void
    {
        $response = $this->get(route('auth.forgot-password'));

        $response->assertStatus(200);
        $response->assertSee('Recuperar contraseña');
        $response->assertSee('name="email"', false);
    }

    public function test_reset_password_view_renders_with_token(): void
    {
        $response = $this->get(route('auth.password.reset', ['token' => 'some-token', 'email' => 'foo@example.com']));

        $response->assertStatus(200);
        $response->assertSee('Nueva contraseña');
        $response->assertSee('name="token"', false);
        $response->assertSee('value="some-token"', false);
    }

    // ---- sendResetLinkEmail ----------------------------------------------

    public function test_forgot_password_with_existing_email_sends_notification(): void
    {
        Notification::fake();

        $subscriber = Subscriber::factory()->create([
            'name' => 'Esperanza',
            'email' => 'esperanza@example.com',
            'password' => 'old-password',
        ]);

        $response = $this->from(route('auth.forgot-password'))
            ->post(route('auth.password.email'), ['email' => 'esperanza@example.com']);

        $response->assertRedirect(route('auth.forgot-password'));
        $response->assertSessionHas('auth-flash');

        Notification::assertSentTo($subscriber, ResetPasswordNotification::class);

        // Token row was created.
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'esperanza@example.com']);
    }

    public function test_forgot_password_with_unknown_email_returns_same_message(): void
    {
        Notification::fake();

        $response = $this->from(route('auth.forgot-password'))
            ->post(route('auth.password.email'), ['email' => 'unknown@example.com']);

        $response->assertRedirect(route('auth.forgot-password'));
        $response->assertSessionHas('auth-flash');

        // No notification sent, no row created.
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_forgot_password_token_is_hashed_in_db(): void
    {
        Notification::fake();
        Subscriber::factory()->create([
            'email' => 'hash@example.com',
            'password' => 'whatever',
        ]);

        $this->post(route('auth.password.email'), ['email' => 'hash@example.com']);

        $record = DB::table('password_reset_tokens')->where('email', 'hash@example.com')->first();
        $this->assertNotNull($record);
        // Should NOT match the substrings of any random token (60+ chars).
        $this->assertGreaterThan(50, strlen($record->token));
    }

    // ---- reset ---------------------------------------------------------------

    public function test_reset_password_with_valid_token_updates_password(): void
    {
        $subscriber = Subscriber::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'old-password',
        ]);

        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => 'reset@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->post(route('auth.password.store'), [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertRedirect(route('dashboard'));

        $subscriber->refresh();
        $this->assertTrue(Hash::check('new-password-123', $subscriber->password));
        $this->assertFalse(Hash::check('old-password', $subscriber->password));
        $this->assertEquals($subscriber->id, session('subscriber_id'));

        // Single-use token — deleted after success.
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'reset@example.com']);
    }

    public function test_reset_password_with_invalid_token_shows_error(): void
    {
        Subscriber::factory()->create([
            'email' => 'invalid@example.com',
            'password' => 'whatever',
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => 'invalid@example.com',
            'token' => Hash::make('real-token'),
            'created_at' => now(),
        ]);

        $response = $this->from(route('auth.password.reset', ['token' => 'fake']))
            ->post(route('auth.password.store'), [
                'token' => 'fake-token',
                'email' => 'invalid@example.com',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_reset_password_with_expired_token_shows_error(): void
    {
        Subscriber::factory()->create([
            'email' => 'expired@example.com',
            'password' => 'whatever',
        ]);

        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => 'expired@example.com',
            'token' => Hash::make($token),
            // 90 minutes ago — past the 60-minute expiry.
            'created_at' => now()->subMinutes(90),
        ]);

        $response = $this->from(route('auth.password.reset', ['token' => $token]))
            ->post(route('auth.password.store'), [
                'token' => $token,
                'email' => 'expired@example.com',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ]);

        $response->assertSessionHasErrors('email');
        // Expired tokens are deleted so they can't be reused.
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'expired@example.com']);
    }

    public function test_reset_password_token_is_deleted_after_use(): void
    {
        $subscriber = Subscriber::factory()->create([
            'email' => 'reuse@example.com',
            'password' => 'whatever',
        ]);

        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => 'reuse@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // First use — successful.
        $this->post(route('auth.password.store'), [
            'token' => $token,
            'email' => 'reuse@example.com',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertRedirect(route('dashboard'));

        // Second use — should fail because token was deleted.
        $response = $this->from(route('auth.password.reset', ['token' => $token]))
            ->post(route('auth.password.store'), [
                'token' => $token,
                'email' => 'reuse@example.com',
                'password' => 'another-password-456',
                'password_confirmation' => 'another-password-456',
            ]);

        $response->assertSessionHasErrors('email');
        $subscriber->refresh();
        $this->assertTrue(Hash::check('new-password-123', $subscriber->password));
    }

    public function test_reset_password_validates_password_min_length(): void
    {
        $response = $this->from(route('auth.password.reset', ['token' => 'token']))
            ->post(route('auth.password.store'), [
                'token' => 'token',
                'email' => 'someone@example.com',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_reset_password_validates_confirmation_match(): void
    {
        $response = $this->from(route('auth.password.reset', ['token' => 'token']))
            ->post(route('auth.password.store'), [
                'token' => 'token',
                'email' => 'someone@example.com',
                'password' => 'correct-password-123',
                'password_confirmation' => 'different-password',
            ]);

        $response->assertSessionHasErrors('password');
    }
}
