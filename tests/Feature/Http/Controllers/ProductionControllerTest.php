<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Production;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_redirects_when_no_session(): void
    {
        $response = $this->get(route('productions.index'));
        $response->assertRedirect(route('home'));
    }

    public function test_index_redirects_when_session_points_to_missing_subscriber(): void
    {
        // Simulates a stale session after migrate:fresh --seed wiped the DB.
        session(['subscriber_id' => 999999]);

        $response = $this->get(route('productions.index'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('production-flash');
        $this->assertNull(session('subscriber_id')); // session was cleared
    }

    public function test_index_lists_only_own_productions(): void
    {
        $alice = Subscriber::factory()->create();
        $bob = Subscriber::factory()->create();

        $aliceProduction = Production::factory()->create(['subscriber_id' => $alice->id, 'title' => 'Alice corto']);
        $bobProduction = Production::factory()->create(['subscriber_id' => $bob->id, 'title' => 'Bob corto']);

        session(['subscriber_id' => $alice->id]);
        $response = $this->get(route('productions.index'));

        $response->assertStatus(200);
        $response->assertSee('Alice corto');
        $response->assertDontSee('Bob corto');
    }

    public function test_store_assigns_subscriber_id_from_session(): void
    {
        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $response = $this->post(route('productions.store'), [
            'title' => 'Mi nuevo corto',
            'category' => 'short_film',
            'genres_text' => 'drama, horror',
            'country' => 'Mexico',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('productions', [
            'subscriber_id' => $subscriber->id,
            'title' => 'Mi nuevo corto',
            'category' => 'short_film',
            'country' => 'Mexico',
        ]);

        $created = Production::where('subscriber_id', $subscriber->id)->first();
        $this->assertEquals(['drama', 'horror'], $created->genres);
    }

    public function test_store_redirects_when_no_session(): void
    {
        $response = $this->post(route('productions.store'), [
            'title' => 'Sin sesión',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertDatabaseCount('productions', 0);
    }

    public function test_store_validates_required_title(): void
    {
        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $response = $this->from(route('productions.create'))
            ->post(route('productions.store'), [
                'title' => '',
            ]);

        $response->assertRedirect(route('productions.create'));
        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('productions', 0);
    }

    public function test_store_validates_genres_text_length(): void
    {
        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $response = $this->post(route('productions.store'), [
            'title' => 'OK',
            'genres_text' => str_repeat('a,', 600),
        ]);

        $response->assertSessionHasErrors('genres_text');
    }

    public function test_store_dedupes_and_trims_genres(): void
    {
        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $this->post(route('productions.store'), [
            'title' => 'Con duplicados',
            'genres_text' => ' drama , horror,drama, , thriller ',
        ]);

        $created = Production::where('subscriber_id', $subscriber->id)->first();
        $this->assertEquals(['drama', 'horror', 'thriller'], $created->genres);
    }

    public function test_update_requires_ownership(): void
    {
        $alice = Subscriber::factory()->create();
        $bob = Subscriber::factory()->create();
        $aliceProduction = Production::factory()->create(['subscriber_id' => $alice->id]);

        session(['subscriber_id' => $bob->id]);

        $response = $this->put(route('productions.update', $aliceProduction), [
            'title' => 'Hackeado',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('productions', [
            'id' => $aliceProduction->id,
            'title' => $aliceProduction->title,
        ]);
    }

    public function test_update_accepts_valid_changes(): void
    {
        $subscriber = Subscriber::factory()->create();
        $production = Production::factory()->create(['subscriber_id' => $subscriber->id, 'title' => 'Original']);

        session(['subscriber_id' => $subscriber->id]);

        $response = $this->put(route('productions.update', $production), [
            'title' => 'Renombrado',
            'category' => 'horror',
        ]);

        $response->assertRedirect(route('productions.show', $production));
        $this->assertDatabaseHas('productions', [
            'id' => $production->id,
            'title' => 'Renombrado',
            'category' => 'horror',
        ]);
    }

    public function test_destroy_requires_ownership(): void
    {
        $alice = Subscriber::factory()->create();
        $bob = Subscriber::factory()->create();
        $aliceProduction = Production::factory()->create(['subscriber_id' => $alice->id]);

        session(['subscriber_id' => $bob->id]);

        $response = $this->delete(route('productions.destroy', $aliceProduction));

        $response->assertStatus(403);
        $this->assertDatabaseHas('productions', ['id' => $aliceProduction->id]);
    }

    public function test_destroy_deletes_own_production(): void
    {
        $subscriber = Subscriber::factory()->create();
        $production = Production::factory()->create(['subscriber_id' => $subscriber->id]);

        session(['subscriber_id' => $subscriber->id]);

        $response = $this->delete(route('productions.destroy', $production));

        $response->assertRedirect(route('productions.index'));
        $this->assertDatabaseMissing('productions', ['id' => $production->id]);
    }

    public function test_show_requires_ownership(): void
    {
        $alice = Subscriber::factory()->create();
        $bob = Subscriber::factory()->create();
        $aliceProduction = Production::factory()->create(['subscriber_id' => $alice->id]);

        session(['subscriber_id' => $bob->id]);

        $this->get(route('productions.show', $aliceProduction))->assertStatus(403);
        $this->get(route('productions.edit', $aliceProduction))->assertStatus(403);
    }

    public function test_matches_requires_ownership(): void
    {
        $alice = Subscriber::factory()->create();
        $bob = Subscriber::factory()->create();
        $aliceProduction = Production::factory()->create(['subscriber_id' => $alice->id]);

        session(['subscriber_id' => $bob->id]);

        $this->get(route('productions.matches', $aliceProduction))->assertStatus(403);
    }

    public function test_create_redirects_when_no_session(): void
    {
        $this->get(route('productions.create'))->assertRedirect(route('home'));
    }

    public function test_create_renders_form_when_authenticated(): void
    {
        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $response = $this->get(route('productions.create'));
        $response->assertStatus(200);
        $response->assertSee('Nueva producción');
    }

    public function test_matches_view_renders_for_own_production(): void
    {
        $subscriber = Subscriber::factory()->create();
        \App\Models\Festival::factory()->create([
            'category' => 'short_film',
            'country' => 'Mexico',
            'accepting_submissions' => true,
            'deadline' => now()->addDays(30),
        ]);
        $production = \App\Models\Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => 'Mexico',
            'genres' => [],
        ]);

        session(['subscriber_id' => $subscriber->id]);

        $response = $this->get(route('productions.matches', $production));
        $response->assertStatus(200);
        $response->assertSee('Festivales sugeridos');
        $response->assertSee('Suscribirme');
    }

    public function test_show_view_renders_for_own_production(): void
    {
        $subscriber = Subscriber::factory()->create();
        $production = \App\Models\Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'title' => 'Corto de prueba',
        ]);

        session(['subscriber_id' => $subscriber->id]);

        $response = $this->get(route('productions.show', $production));
        $response->assertStatus(200);
        $response->assertSee('Corto de prueba');
        $response->assertSee('Ver matches sugeridos');
    }

    public function test_get_productions_index_is_not_rate_limited(): void
    {
        // Regression guard: nav link "Producciones" was returning 429 after a
        // few clicks because throttle:productions was applied to read routes.
        // GETs must remain unlimited.
        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        for ($i = 0; $i < 25; $i++) {
            $this->get(route('productions.index'))->assertStatus(200);
        }
    }
}