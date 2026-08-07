<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureSubscriberSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_route_redirects_when_no_session(): void
    {
        // The middleware is wired to /dashboard via auth.subscriber alias.
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('auth-flash');
    }

    public function test_dashboard_route_redirects_when_session_points_at_deleted_subscriber(): void
    {
        session(['subscriber_id' => 999999]);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('auth-flash');
        // Side effect: stale id is wiped so the next request doesn't loop.
        $this->assertNull(session('subscriber_id'));
    }

    public function test_dashboard_route_loads_for_a_valid_subscriber(): void
    {
        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $this->assertEquals($subscriber->id, session('subscriber_id'));
    }
}
