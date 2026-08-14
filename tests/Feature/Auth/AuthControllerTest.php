<?php

namespace Tests\Feature\Auth;

use App\Models\Subscriber;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- View rendering --------------------------------------------------

    public function test_login_view_renders(): void
    {
        $response = $this->get(route('auth.login'));

        $response->assertStatus(200);
        $response->assertSee('Inicia sesión');
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="remember"', false);
        $response->assertSee('Continuar con Google');
    }

    public function test_register_view_renders(): void
    {
        $response = $this->get(route('auth.register'));

        $response->assertStatus(200);
        $response->assertSee('Crea tu cuenta');
        $response->assertSee('name="name"', false);
        $response->assertSee('name="last_name"', false);
        $response->assertSee('name="production_company"', false);
        $response->assertSee('name="email"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
    }

    // ---- Login -----------------------------------------------------------

    public function test_login_with_correct_password_starts_session(): void
    {
        $subscriber = Subscriber::factory()->create([
            'name' => 'Sofia',
            'email' => 'sofia@example.com',
            'password' => 'correct-password-123',
        ]);

        $response = $this->post(route('auth.login.process'), [
            'email' => 'sofia@example.com',
            'password' => 'correct-password-123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals($subscriber->id, session('subscriber_id'));
        $response->assertSessionHas('auth-flash');
    }

    public function test_login_with_wrong_password_returns_error(): void
    {
        Subscriber::factory()->create([
            'email' => 'sofia@example.com',
            'password' => 'correct-password-123',
        ]);

        $response = $this->from(route('auth.login'))->post(route('auth.login.process'), [
            'email' => 'sofia@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('auth.login'));
        $response->assertSessionHasErrors('password');
        $this->assertNull(session('subscriber_id'));
    }

    public function test_login_with_google_only_account_returns_helpful_error(): void
    {
        // Subscriber created via Google — no password set.
        Subscriber::factory()->create([
            'email' => 'google@example.com',
            'password' => null,
        ]);

        $response = $this->from(route('auth.login'))->post(route('auth.login.process'), [
            'email' => 'google@example.com',
            'password' => 'anything',
        ]);

        $response->assertRedirect(route('auth.login'));
        $response->assertSessionHasErrors('password');
        // The message should mention Google.
        $this->assertStringContainsString('Google', session('errors')->first('password'));
    }

    public function test_login_with_unknown_email_redirects_to_register_with_email_prefilled(): void
    {
        $response = $this->from(route('auth.login'))->post(route('auth.login.process'), [
            'email' => 'nuevo@example.com',
            'password' => 'something',
        ]);

        $response->assertRedirect(route('auth.register'));
        $response->assertSessionHas('_old_input.email', 'nuevo@example.com');
        $response->assertSessionHas('auth-flash');
        $this->assertNull(session('subscriber_id'));
        $this->assertDatabaseCount('subscribers', 0);
    }

    public function test_login_with_remember_creates_long_lived_cookie(): void
    {
        $subscriber = Subscriber::factory()->create([
            'email' => 'remembered@example.com',
            'password' => 'hunter2hunter',
        ]);

        $response = $this->post(route('auth.login.process'), [
            'email' => 'remembered@example.com',
            'password' => 'hunter2hunter',
            'remember' => 1,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertCookie('remember_subscriber');
    }

    public function test_login_without_remember_does_not_set_cookie(): void
    {
        Subscriber::factory()->create([
            'email' => 'no-remember@example.com',
            'password' => 'hunter2hunter',
        ]);

        $response = $this->post(route('auth.login.process'), [
            'email' => 'no-remember@example.com',
            'password' => 'hunter2hunter',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertCookieMissing('remember_subscriber');
    }

    public function test_login_validates_email_format(): void
    {
        $response = $this->from(route('auth.login'))->post(route('auth.login.process'), [
            'email' => 'not-an-email',
            'password' => 'whatever',
        ]);

        $response->assertRedirect(route('auth.login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_login_requires_password(): void
    {
        Subscriber::factory()->create([
            'email' => 'nopass@example.com',
            'password' => 'whatever',
        ]);

        $response = $this->from(route('auth.login'))->post(route('auth.login.process'), [
            'email' => 'nopass@example.com',
        ]);

        $response->assertRedirect(route('auth.login'));
        $response->assertSessionHasErrors('password');
    }

    // ---- Register --------------------------------------------------------

    public function test_register_with_new_email_creates_subscriber_and_starts_session(): void
    {
        $response = $this->post(route('auth.register.process'), [
            'name' => 'Lara',
            'last_name' => 'López',
            'production_company' => 'Lara Films',
            'email' => 'lara@example.com',
            'password' => 'correctpassword',
            'password_confirmation' => 'correctpassword',
        ]);

        $response->assertRedirect(route('dashboard'));

        $subscriber = Subscriber::where('email', 'lara@example.com')->first();
        $this->assertNotNull($subscriber);
        $this->assertEquals('Lara', $subscriber->name);
        $this->assertEquals('López', $subscriber->last_name);
        $this->assertEquals('Lara Films', $subscriber->production_company);
        $this->assertTrue($subscriber->notifications_enabled);
        $this->assertEquals($subscriber->id, session('subscriber_id'));
        $response->assertSessionHas('auth-flash');
    }

    public function test_register_hashes_password_with_bcrypt(): void
    {
        $this->post(route('auth.register.process'), [
            'name' => 'Lara',
            'email' => 'lara@example.com',
            'password' => 'plain-text-not-stored',
            'password_confirmation' => 'plain-text-not-stored',
        ]);

        $subscriber = Subscriber::where('email', 'lara@example.com')->first();
        $this->assertNotEquals('plain-text-not-stored', $subscriber->password);
        $this->assertTrue(Hash::check('plain-text-not-stored', $subscriber->password));
    }

    public function test_register_rejects_password_shorter_than_8(): void
    {
        $response = $this->from(route('auth.register'))->post(route('auth.register.process'), [
            'name' => 'Tiny',
            'email' => 'tiny@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertRedirect(route('auth.register'));
        $response->assertSessionHasErrors('password');
        $this->assertDatabaseCount('subscribers', 0);
    }

    public function test_register_requires_password_confirmation_match(): void
    {
        $response = $this->from(route('auth.register'))->post(route('auth.register.process'), [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'correctpassword',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertRedirect(route('auth.register'));
        $response->assertSessionHasErrors('password');
        $this->assertDatabaseCount('subscribers', 0);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        Subscriber::factory()->create(['email' => 'taken@example.com']);

        $response = $this->from(route('auth.register'))->post(route('auth.register.process'), [
            'name' => 'Impostora',
            'email' => 'taken@example.com',
            'password' => 'correctpassword',
            'password_confirmation' => 'correctpassword',
        ]);

        $response->assertRedirect(route('auth.register'));
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('subscribers', 1);
    }

    public function test_register_validates_required_fields(): void
    {
        $response = $this->from(route('auth.register'))->post(route('auth.register.process'), [
            'email' => 'someone@example.com',
            // name + password missing
        ]);

        $response->assertRedirect(route('auth.register'));
        $response->assertSessionHasErrors(['name', 'password']);
        $this->assertDatabaseCount('subscribers', 0);
    }

    public function test_register_sends_welcome_notification_to_new_subscriber(): void
    {
        Notification::fake();

        $response = $this->post(route('auth.register.process'), [
            'name' => 'Lara',
            'last_name' => 'López',
            'production_company' => 'Lara Films',
            'email' => 'lara-welcome@example.com',
            'password' => 'correctpassword',
            'password_confirmation' => 'correctpassword',
        ]);

        $response->assertRedirect(route('dashboard'));

        $subscriber = Subscriber::where('email', 'lara-welcome@example.com')->first();
        $this->assertNotNull($subscriber);

        Notification::assertSentTo($subscriber, WelcomeNotification::class);
    }

    public function test_register_does_not_send_welcome_on_validation_failure(): void
    {
        Notification::fake();

        // Missing password → validation fails → no subscriber created, no email sent.
        $response = $this->from(route('auth.register'))->post(route('auth.register.process'), [
            'name' => 'Incompleto',
            'email' => 'incompleto@example.com',
        ]);

        $response->assertSessionHasErrors(['password']);

        Notification::assertNothingSent();
    }

    // ---- Integration sanity check ---------------------------------------

    public function test_after_login_dashboard_renders(): void
    {
        $subscriber = Subscriber::factory()->create([
            'email' => 'integration@example.com',
            'password' => 'correctpassword',
        ]);

        $this->post(route('auth.login.process'), [
            'email' => 'integration@example.com',
            'password' => 'correctpassword',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertStatus(200);
    }

    // ---- Welcome email content ------------------------------------------

    /**
     * The welcome notification's mail payload must always carry the right
     * subject, greeting (with the user's name), and CTA — regardless of
     * which mailer (log in dev, Resend in prod) actually delivers it.
     * This is the CI-level guard so a regression in toMail() can't ship
     * unnoticed while Resend is rejecting sends (every send goes to
     * failed_jobs and the failure is easy to miss when you're focused
     * on the queue worker).
     */
    public function test_welcome_notification_builds_the_expected_mail_payload(): void
    {
        $subscriber = Subscriber::factory()->create([
            'name' => 'Mariana',
            'email' => 'mariana@example.com',
        ]);

        $notification = new WelcomeNotification($subscriber);
        $mailMessage = $notification->toMail($subscriber);

        // MailMessage extends SimpleMessage, which exposes all the rendered
        // fields as public props. Inspecting them directly is the supported
        // way to assert what will land in the recipient's inbox.
        $this->assertSame('¡Bienvenido a Consultfest!', $mailMessage->subject);
        $this->assertStringContainsString('Mariana', (string) $mailMessage->greeting);
        $introBlob = strtolower(implode(' ', $mailMessage->introLines));
        $this->assertStringContainsString('buscar festivales', $introBlob);
        $this->assertStringContainsString('deadlines', $introBlob);
        $this->assertSame(url('/dashboard'), $mailMessage->actionUrl);
        $this->assertSame('Ir a mi panel', $mailMessage->actionText);
    }
}
