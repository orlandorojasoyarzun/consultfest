<?php

namespace Tests\Feature\Auth;

use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Build a Socialite User double that exposes the methods we read in the
     * callback (email, name). Avoids spinning up a real OAuth flow.
     */
    private function fakeGoogleUser(string $email, string $name = 'Jane Doe'): SocialiteUser
    {
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getEmail')->andReturn($email);
        $user->shouldReceive('getName')->andReturn($name);
        return $user;
    }

    public function test_redirect_to_google_returns_a_redirect_response(): void
    {
        // Socialite::driver('google')->redirect() returns a RedirectResponse
        // pointing at accounts.google.com. We don't care about the URL,
        // only that the route exists and responds 302.
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google'));

        $response->assertStatus(302);
    }

    public function test_callback_creates_new_subscriber_and_redirects_to_dashboard(): void
    {
        $user = $this->fakeGoogleUser('new@example.com', 'New Person');

        Socialite::shouldReceive('driver->user')->andReturn($user);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('subscribers', [
            'email' => 'new@example.com',
            'name' => 'New Person',
        ]);

        $subscriber = Subscriber::where('email', 'new@example.com')->first();
        $this->assertEquals($subscriber->id, session('subscriber_id'));
        $response->assertSessionHas('auth-flash');
    }

    public function test_callback_reuses_existing_subscriber_by_email(): void
    {
        // Same Google account logs in twice — must NOT create a duplicate row.
        Subscriber::factory()->create([
            'email' => 'returning@example.com',
            'name' => 'Returning Person',
        ]);

        $user = $this->fakeGoogleUser('returning@example.com', 'Returning Person');

        Socialite::shouldReceive('driver->user')->andReturn($user);

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('subscribers', 1);
        $subscriber = Subscriber::where('email', 'returning@example.com')->first();
        $this->assertEquals($subscriber->id, session('subscriber_id'));
    }

    public function test_callback_redirects_home_with_flash_when_google_fails(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new \RuntimeException('token expired'));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('auth-flash');
        $this->assertNull(session('subscriber_id'));
        $this->assertDatabaseCount('subscribers', 0);
    }

    public function test_callback_redirects_home_when_google_returns_no_email(): void
    {
        // Some Google accounts hide the email behind permissions. We refuse to
        // create a Subscriber without an email (it would break uniqueness).
        $user = Mockery::mock(SocialiteUser::class);
        $user->shouldReceive('getEmail')->andReturn(null);
        $user->shouldReceive('getName')->andReturn('No Email User');

        Socialite::shouldReceive('driver->user')->andReturn($user);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('home'));
        $this->assertDatabaseCount('subscribers', 0);
    }

    public function test_logout_clears_session_and_redirects_home(): void
    {
        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $response = $this->post(route('auth.logout'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('auth-flash');
        $this->assertNull(session('subscriber_id'));
    }
}
