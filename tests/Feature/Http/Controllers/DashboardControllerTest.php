<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Festival;
use App\Models\Production;
use App\Models\Subscriber;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_to_home_when_no_session(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('auth-flash');
    }

    public function test_index_redirects_when_subscriber_was_deleted(): void
    {
        // Stale session pointing at a Subscriber that no longer exists.
        session(['subscriber_id' => 999999]);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('auth-flash');
        $this->assertNull(session('subscriber_id'));
    }

    public function test_index_renders_with_subscribers_productions_and_subscriptions(): void
    {
        $subscriber = Subscriber::factory()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $production = Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'title' => 'Mi corto de prueba',
        ]);

        $festival = Festival::factory()->create(['name' => 'Cannes']);
        Subscription::create([
            'subscriber_id' => $subscriber->id,
            'festival_id' => $festival->id,
            'notification_type' => 'both',
        ]);

        // Other user's data — must NOT leak into the view.
        $bob = Subscriber::factory()->create();
        Production::factory()->create(['subscriber_id' => $bob->id, 'title' => 'Bob secreto']);

        session(['subscriber_id' => $subscriber->id]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Alice');
        $response->assertSee('Mi corto de prueba');
        $response->assertSee('Cannes');
        $response->assertDontSee('Bob secreto');
    }

    public function test_index_handles_zero_productions_and_subscriptions_gracefully(): void
    {
        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        // Empty-state copy lives in the view — verify it's there.
        $response->assertSee('Aún no tienes producciones inscritas');
        $response->assertSee('No estás suscrito a ningún festival todavía');
    }
}
