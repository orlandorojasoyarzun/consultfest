<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Production;
use App\Models\Subscriber;
use App\Notifications\ProductionCreatedNotification;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProductionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The array cache driver persists within a single phpunit process
        // (only the DB is reset by RefreshDatabase). Without this flush, a
        // matcher call from an earlier test could leak into later ones and
        // serve stale results — which is exactly what breaks the pagination
        // test (it sees Página 2 on a fresh GET because the cached pool
        // already has 12 entries from a prior run).
        Cache::flush();
        $this->app->make(RateLimiter::class)->clear('festivalapi:127.0.0.1');
    }

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

    public function test_store_sends_confirmation_notification_to_subscriber(): void
    {
        Notification::fake();

        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $this->post(route('productions.store'), [
            'title' => 'Mi corto con email',
            'category' => 'short_film',
            'genres_text' => 'drama',
            'country' => 'Mexico',
        ]);

        $production = Production::where('subscriber_id', $subscriber->id)->first();
        $this->assertNotNull($production);

        Notification::assertSentTo($subscriber, ProductionCreatedNotification::class);
    }

    public function test_store_does_not_send_notification_on_validation_failure(): void
    {
        Notification::fake();

        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $this->post(route('productions.store'), [
            'title' => '', // empty title triggers validation error
        ]);

        Notification::assertNothingSent();
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
        // ProductionMatcher hits FestivalSearchService → FestivalAPI and
        // now also enriches each match via the detail endpoint. Fake BOTH
        // endpoints. Order matters: the detail pattern goes FIRST because
        // Http::fake first-match-wins and `/festivals*` would otherwise
        // swallow `/festivals/9001/`.
        Http::fake([
            'https://festivalapi.com/v1/festivals/9001/' => Http::response([
                'id' => 9001,
                'name' => 'Mock Spain Shorts',
                'country' => 'Spain',
                'categories' => ['short_film'],
                'genres' => ['drama'],
                'deadline_regular' => now()->addDays(45)->toDateString(),
                'event_start_date' => now()->addMonths(2)->toDateString(),
                'regular_fee' => 15.0,
                'submission_url' => 'https://example.com/submit',
                'website' => 'https://example.com',
                'composite_score' => 80.0,
            ], 200),
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 1,
                'results' => [
                    [
                        'id' => 9001,
                        'name' => 'Mock Spain Shorts',
                        'country' => 'Spain',
                        'categories' => ['short_film'],
                        'genres' => ['drama'],
                        'deadline_regular' => now()->addDays(45)->toDateString(),
                        'event_start_date' => now()->addMonths(2)->toDateString(),
                        'regular_fee' => 15.0,
                        'submission_url' => 'https://example.com/submit',
                        'website' => 'https://example.com',
                        'composite_score' => 80.0,
                    ],
                ],
            ], 200),
        ]);

        $subscriber = Subscriber::factory()->create();
        $production = \App\Models\Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => 'Spain',
            'genres' => [],
        ]);

        session(['subscriber_id' => $subscriber->id]);

        $response = $this->get(route('productions.matches', $production));
        $response->assertStatus(200);
        $response->assertSee('Festivales sugeridos');
        $response->assertSee('Mock Spain Shorts');
        $response->assertSee('Suscribirme');
    }

    public function test_matches_view_shows_categories_as_chips_when_genres_empty(): void
    {
        // FestivalAPI returns genres=[] for festivals classified only at the
        // category level (e.g. Sitges comes back with
        // categories=['horror','fantasy','sci_fi'] and genres=[]). The view
        // must fall back to categories so the card isn't missing chips.
        Http::fake([
            'https://festivalapi.com/v1/festivals/404/' => Http::response([
                'id' => 404,
                'name' => 'Sitges Film Festival',
                'country' => 'Spain',
                'categories' => ['horror', 'fantasy', 'sci_fi'],
                'genres' => [],
                'deadline_regular' => null,
                'event_start_date' => null,
                'regular_fee' => null,
                'composite_score' => 0.0,
            ], 200),
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 1,
                'results' => [[
                    'id' => 404,
                    'name' => 'Sitges Film Festival',
                    'country' => 'Spain',
                    'categories' => ['horror', 'fantasy', 'sci_fi'],
                    'genres' => [],
                    'deadline_regular' => null,
                    'event_start_date' => null,
                    'regular_fee' => null,
                    'composite_score' => 0.0,
                ]],
            ], 200),
        ]);

        $subscriber = Subscriber::factory()->create();
        $production = \App\Models\Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => 'Spain',
            'genres' => ['horror'],
        ]);

        session(['subscriber_id' => $subscriber->id]);

        $response = $this->get(route('productions.matches', $production));
        $response->assertStatus(200);
        // Categories must be humanized ("sci_fi" → "Sci fi") and rendered.
        $response->assertSee('Horror');
        $response->assertSee('Fantasy');
        $response->assertSee('Sci fi');
    }

    public function test_matches_view_shows_dates_message_when_all_three_dates_missing(): void
    {
        // FestivalAPI returns null for deadline_regular, event_start_date AND
        // regular_fee for festivals whose dates/fee aren't published yet
        // (verified 2026-08-10: Sitges, Bilbao Fantasy, Terrassa Horror). The
        // view must collapse the 3-column grid into a single contextual
        // message instead of three "—" dashes.
        Http::fake([
            'https://festivalapi.com/v1/festivals/404/' => Http::response([
                'id' => 404,
                'name' => 'Sitges Film Festival',
                'country' => 'Spain',
                'categories' => ['horror'],
                'genres' => [],
                'deadline_regular' => null,
                'event_start_date' => null,
                'regular_fee' => null,
                'composite_score' => 0.0,
            ], 200),
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 1,
                'results' => [[
                    'id' => 404,
                    'name' => 'Sitges Film Festival',
                    'country' => 'Spain',
                    'categories' => ['horror'],
                    'genres' => [],
                    'deadline_regular' => null,
                    'event_start_date' => null,
                    'regular_fee' => null,
                    'composite_score' => 0.0,
                ]],
            ], 200),
        ]);

        $subscriber = Subscriber::factory()->create();
        $production = \App\Models\Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => 'Spain',
            'genres' => ['horror'],
        ]);

        session(['subscriber_id' => $subscriber->id]);

        $response = $this->get(route('productions.matches', $production));
        $response->assertStatus(200);
        $response->assertSee('Fechas no publicadas');
        $response->assertSee('visitá el sitio del festival');
        // The grid labels should NOT render when all three values are null.
        $response->assertDontSee('>Apertura<', false);
        $response->assertDontSee('>Deadline<', false);
        $response->assertDontSee('>Fee<', false);
    }

    public function test_matches_view_still_shows_grid_when_any_one_date_is_present(): void
    {
        // Partial data (only deadline, no apertura or fee) must still render
        // the grid with one real value and two "—". The fallback message
        // only fires when ALL three are missing.
        Http::fake([
            'https://festivalapi.com/v1/festivals/500/' => Http::response([
                'id' => 500,
                'name' => 'Partial Fest',
                'country' => 'Spain',
                'categories' => ['horror'],
                'genres' => [],
                'deadline_regular' => now()->addDays(20)->toDateString(),
                'event_start_date' => null,
                'regular_fee' => null,
                'composite_score' => 5.0,
            ], 200),
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 1,
                'results' => [[
                    'id' => 500,
                    'name' => 'Partial Fest',
                    'country' => 'Spain',
                    'categories' => ['horror'],
                    'genres' => [],
                    'deadline_regular' => now()->addDays(20)->toDateString(),
                    'event_start_date' => null,
                    'regular_fee' => null,
                    'composite_score' => 5.0,
                ]],
            ], 200),
        ]);

        $subscriber = Subscriber::factory()->create();
        $production = \App\Models\Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => 'Spain',
            'genres' => ['horror'],
        ]);

        session(['subscriber_id' => $subscriber->id]);

        $response = $this->get(route('productions.matches', $production));
        $response->assertStatus(200);
        $response->assertSee('Apertura');
        $response->assertSee('Deadline');
        $response->assertSee('Fee');
        $response->assertDontSee('Fechas no publicadas');
    }

    public function test_matches_view_paginates_results(): void
    {
        // The matches page paginates 5 per page. With 12 faked matches the
        // user sees page 1 (5 results), page 2 (5 results), page 3 (2).
        // Flipping pages MUST NOT trigger extra FestivalAPI calls — only
        // page 1 hits the detail endpoint (5 enrichments), subsequent
        // pages reuse the same list response from cache.
        Http::fake([
            'https://festivalapi.com/v1/festivals/*/' => function ($request) {
                preg_match('#/festivals/(\d+)/#', $request->url(), $m);
                $id = (int) ($m[1] ?? 0);
                return Http::response([
                    'id' => $id,
                    'name' => "Fest {$id}",
                    'categories' => ['short_film'],
                    'genres' => [],
                    'deadline_regular' => null,
                    'event_start_date' => null,
                    'regular_fee' => null,
                    'composite_score' => 50.0,
                    'submission_url' => '',
                    'website' => '',
                ], 200);
            },
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => 12,
                'results' => array_map(
                    fn ($i) => [
                        'id' => $i,
                        'name' => "Fest {$i}",
                        'categories' => ['short_film'],
                        'genres' => [],
                        'deadline_regular' => null,
                        'event_start_date' => null,
                        'regular_fee' => null,
                        'composite_score' => 50.0,
                    ],
                    range(1, 12),
                ),
            ], 200),
        ]);

        $subscriber = Subscriber::factory()->create();
        $production = \App\Models\Production::factory()->create([
            'subscriber_id' => $subscriber->id,
            'category' => 'short_film',
            'country' => 'Spain',
            'genres' => ['horror'],
        ]);

        session(['subscriber_id' => $subscriber->id]);

        // Page 1: 5 visible.
        // Match against the h2 tag content (">Fest N</h2>") so "Fest 1"
        // doesn't false-match "Fest 10" / "Fest 11" / "Fest 12" — both
        // assertSee and assertSeeText do substring search. Pass $escape=false
        // because the literal pattern has HTML chars that we'd otherwise
        // double-escape in the search value.
        $page1 = $this->get(route('productions.matches', $production));
        $page1->assertStatus(200);
        $page1->assertSee('>Fest 1</h2>', false);
        $page1->assertSee('>Fest 5</h2>', false);
        $page1->assertDontSee('>Fest 6</h2>', false);
        $page1->assertSee('Página 1 de 3');

        // Page 2: 5 more.
        $page2 = $this->get(route('productions.matches', ['production' => $production, 'page' => 2]));
        $page2->assertStatus(200);
        $page2->assertSee('>Fest 6</h2>', false);
        $page2->assertSee('>Fest 10</h2>', false);
        $page2->assertDontSee('>Fest 1</h2>', false);
        $page2->assertSee('Página 2 de 3');

        // Page 3: last 2.
        $page3 = $this->get(route('productions.matches', ['production' => $production, 'page' => 3]));
        $page3->assertStatus(200);
        $page3->assertSee('>Fest 11</h2>', false);
        $page3->assertSee('>Fest 12</h2>', false);
        $page3->assertSee('Página 3 de 3');

        // Cost guard: 1 list call + 5 detail calls. The 5 details all
        // happen on the first page render (ENRICH_TOP_N=5, regardless of
        // pagination). Pages 2 and 3 should only need to read the cached
        // matcher result — no HTTP calls.
        Http::assertSentCount(6);
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