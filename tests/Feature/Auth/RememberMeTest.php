<?php

namespace Tests\Feature\Auth;

use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build the HMAC value the controller would have stored in the cookie.
     */
    private function cookieForSubscriber(int $subscriberId): string
    {
        return hash_hmac('sha256', (string) $subscriberId, config('app.key'));
    }

    public function test_remember_cookie_hydrates_session_when_session_expired(): void
    {
        $subscriber = Subscriber::factory()->create([
            'email' => 'remembered@example.com',
            'password' => 'hunter22hunter',
        ]);

        // No session, but a valid cookie.
        $response = $this->withCookie('remember_subscriber', $this->cookieForSubscriber($subscriber->id))
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $this->assertEquals($subscriber->id, session('subscriber_id'));
    }

    public function test_invalid_remember_cookie_does_not_hydrate(): void
    {
        Subscriber::factory()->create([
            'email' => 'invalid@example.com',
            'password' => 'whatever',
        ]);

        $response = $this->withCookie('remember_subscriber', 'not-a-real-hmac')
            ->get(route('dashboard'));

        $response->assertRedirect(route('home'));
        $this->assertNull(session('subscriber_id'));
    }

    public function test_logout_forgets_remember_cookie(): void
    {
        $subscriber = Subscriber::factory()->create([
            'email' => 'logout@example.com',
            'password' => 'whatever',
        ]);

        session(['subscriber_id' => $subscriber->id]);

        $response = $this->post(route('auth.logout'));

        $response->assertRedirect(route('home'));

        // When you forget a cookie, Laravel queues a "delete" cookie with
        // expire=1. The presence of a cookie with that name is the contract —
        // the browser then expires it. So we expect the cookie to be set
        // back to "null" with an expired date.
        $cookies = $response->headers->getCookies();
        $forgetCookie = null;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'remember_subscriber') {
                $forgetCookie = $cookie;
                break;
            }
        }

        $this->assertNotNull($forgetCookie, 'remember_subscriber cookie should be queued for forget');
        $this->assertLessThan(time(), $forgetCookie->getExpiresTime());
    }

    public function test_login_with_remember_is_long_lived(): void
    {
        $subscriber = Subscriber::factory()->create([
            'email' => 'cookie@example.com',
            'password' => 'correct-password-123',
        ]);

        $response = $this->post(route('auth.login.process'), [
            'email' => 'cookie@example.com',
            'password' => 'correct-password-123',
            'remember' => 1,
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertCookie('remember_subscriber');

        // The cookie value should be the HMAC for our subscriber.
        $cookie = $response->getCookie('remember_subscriber');
        $this->assertEquals($this->cookieForSubscriber($subscriber->id), $cookie->getValue());
    }
}
