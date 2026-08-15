<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Festival;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Notifications\FestivalSubscribedNotification;
use App\Notifications\FestivalUnsubscribedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FestivalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_festivals_index_page_loads(): void
    {
        $this->withoutMiddleware(\Illuminate\Auth\Middleware\Authenticate::class);

        Festival::factory()->count(3)->create();

        $response = $this->get('/festivals');
        $response->assertStatus(200);
    }

    public function test_festivals_show_page_loads(): void
    {
        $this->withoutMiddleware(\Illuminate\Auth\Middleware\Authenticate::class);

        $festival = Festival::factory()->create();
        $response = $this->get("/festivals/{$festival->id}");
        $response->assertStatus(200);
    }

    public function test_subscribe_creates_subscription_and_sends_notification(): void
    {
        Notification::fake();

        $subscriber = Subscriber::factory()->create();
        $festival = Festival::factory()->create(['api_id' => 12345]);

        session(['subscriber_id' => $subscriber->id]);

        $response = $this->postJson(route('festivals.subscribe'), [
            'festival_api_id' => 12345,
            'notification_type' => 'both',
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('subscriptions', [
            'subscriber_id' => $subscriber->id,
            'festival_id' => $festival->id,
            'notification_type' => 'both',
        ]);

        Notification::assertSentTo($subscriber, FestivalSubscribedNotification::class);
    }

    public function test_subscribe_does_not_send_notification_when_festival_missing(): void
    {
        Notification::fake();

        // The controller now tries FestivalAPI when the festival isn't in
        // our local DB. We fake the detail call returning 404 so the
        // test is hermetic (no real network round-trip) and the
        // controller reliably returns 404.
        \Illuminate\Support\Facades\Http::fake([
            'festivalapi.com/*' => \Illuminate\Support\Facades\Http::response('', 404),
        ]);

        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $response = $this->postJson(route('festivals.subscribe'), [
            'festival_api_id' => 99999999,
            'notification_type' => 'opening',
        ]);

        $response->assertStatus(404);
        Notification::assertNothingSent();
    }

    public function test_subscribe_syncs_festival_from_api_when_not_in_local_db(): void
    {
        Notification::fake();

        // FestivalAPI returns a real-looking payload. The controller must
        // persist it locally, create the subscription row, and fire the
        // confirmation notification — all without us pre-creating a
        // Festival row.
        \Illuminate\Support\Facades\Http::fake([
            'festivalapi.com/*' => \Illuminate\Support\Facades\Http::response([
                'id' => 12345,
                'name' => 'Almería Western Film Festival',
                'category' => 'short',
                'country' => 'Spain',
                'city' => 'Almería',
                'deadline' => '2026-09-15',
                'opening_date' => '2026-10-15',
                'submission_fee' => 25.0,
                'submission_url' => 'https://filmfreeway.com/Almeria',
                'website' => 'https://almeriawestern.com',
            ], 200),
        ]);

        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $this->assertDatabaseMissing('festivals', ['api_id' => 12345]);

        $response = $this->postJson(route('festivals.subscribe'), [
            'festival_api_id' => 12345,
            'notification_type' => 'both',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        // Festival was synced into the local DB.
        $festival = Festival::where('api_id', 12345)->first();
        $this->assertNotNull($festival);
        $this->assertSame('Almería Western Film Festival', $festival->name);
        $this->assertSame('Spain', $festival->country);

        // Subscription row was created against the newly-synced festival.
        $this->assertDatabaseHas('subscriptions', [
            'subscriber_id' => $subscriber->id,
            'festival_id' => $festival->id,
            'notification_type' => 'both',
        ]);

        Notification::assertSentTo($subscriber, FestivalSubscribedNotification::class);
    }

    public function test_subscribe_returns_404_when_festival_not_in_local_db_or_api(): void
    {
        Notification::fake();

        // FestivalAPI says the festival doesn't exist (404). The
        // controller must return 404 without creating anything.
        \Illuminate\Support\Facades\Http::fake([
            'festivalapi.com/*' => \Illuminate\Support\Facades\Http::response('', 404),
        ]);

        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $this->assertDatabaseMissing('festivals', ['api_id' => 99999999]);

        $response = $this->postJson(route('festivals.subscribe'), [
            'festival_api_id' => 99999999,
            'notification_type' => 'both',
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseMissing('festivals', ['api_id' => 99999999]);
        $this->assertDatabaseMissing('subscriptions', ['subscriber_id' => $subscriber->id]);
        Notification::assertNothingSent();
    }

    public function test_subscribe_does_not_send_notification_without_session(): void
    {
        Notification::fake();

        // No session('subscriber_id') — must reject before touching the API.
        $response = $this->postJson(route('festivals.subscribe'), [
            'festival_api_id' => 1,
            'notification_type' => 'deadline',
        ]);

        $response->assertStatus(401);
        Notification::assertNothingSent();
    }

    public function test_unsubscribe_deletes_subscription_and_sends_notification(): void
    {
        Notification::fake();

        $subscriber = Subscriber::factory()->create();
        $festival = Festival::factory()->create(['api_id' => 55555]);

        Subscription::create([
            'subscriber_id' => $subscriber->id,
            'festival_id' => $festival->id,
            'notification_type' => 'both',
        ]);

        session(['subscriber_id' => $subscriber->id]);

        $response = $this->deleteJson(route('festivals.unsubscribe', ['festivalApiId' => 55555]));

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('subscriptions', [
            'subscriber_id' => $subscriber->id,
            'festival_id' => $festival->id,
        ]);

        Notification::assertSentTo($subscriber, FestivalUnsubscribedNotification::class);
    }

    public function test_unsubscribe_does_not_send_notification_when_no_subscription_exists(): void
    {
        Notification::fake();

        $subscriber = Subscriber::factory()->create();
        $festival = Festival::factory()->create(['api_id' => 66666]);

        // No subscription row — festival exists but user never subscribed.
        session(['subscriber_id' => $subscriber->id]);

        $response = $this->deleteJson(route('festivals.unsubscribe', ['festivalApiId' => 66666]));

        // Endpoint still returns success (idempotent), but no email.
        $response->assertOk()->assertJson(['success' => true]);
        Notification::assertNothingSent();
    }

    public function test_unsubscribe_does_not_send_notification_without_session(): void
    {
        Notification::fake();

        $response = $this->deleteJson(route('festivals.unsubscribe', ['festivalApiId' => 1]));

        $response->assertStatus(401);
        Notification::assertNothingSent();
    }

    public function test_unsubscribe_returns_success_idempotent_when_festival_not_in_local_db(): void
    {
        Notification::fake();

        // User might click unsubscribe on a festival they remember
        // subscribing to but that was never synced. Endpoint must
        // return success (idempotent) without sending an email.
        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $response = $this->deleteJson(route('festivals.unsubscribe', ['festivalApiId' => 88888]));

        $response->assertOk()->assertJson(['success' => true]);
        Notification::assertNothingSent();
    }

    public function test_redirect_calls_detail_endpoint_and_redirects_to_real_url(): void
    {
        // The list endpoint often returns mis-mapped or empty
        // submission_url. The redirect endpoint must call the detail
        // endpoint (1 credit, 24h cache) and 302 to the real organizer URL.
        \Illuminate\Support\Facades\Http::fake([
            'https://festivalapi.com/v1/festivals/9999/' => \Illuminate\Support\Facades\Http::response([
                'id' => 9999,
                'name' => 'Sitges Film Festival',
                'submission_url' => 'https://filmfreeway.com/Sitges',
                'website' => 'https://sitgesfilmfestival.com',
            ], 200),
        ]);

        $response = $this->get(route('festivals.redirect', ['apiId' => 9999]));

        $response->assertRedirect('https://filmfreeway.com/Sitges');
    }

    public function test_redirect_falls_back_to_filmfreeway_when_detail_returns_no_url(): void
    {
        // Last-resort fallback: if the detail endpoint returns empty URLs,
        // bestUrl() builds a FilmFreeway search URL so the user always
        // lands somewhere useful.
        \Illuminate\Support\Facades\Http::fake([
            'https://festivalapi.com/v1/festivals/9999/' => \Illuminate\Support\Facades\Http::response([
                'id' => 9999,
                'name' => 'Mystery Fest',
                'submission_url' => '',
                'website' => '',
            ], 200),
        ]);

        $response = $this->get(route('festivals.redirect', ['apiId' => 9999]));

        $response->assertRedirect('https://filmfreeway.com/search?q=Mystery%20Fest');
    }

    public function test_redirect_handles_detail_endpoint_failure(): void
    {
        // If the detail endpoint is down or returns 5xx, the redirect
        // must still send the user SOMEWHERE rather than 500ing — the
        // stub DTO has no submission_url/website, so bestUrl() falls
        // through to FilmFreeway search using the apiId-based name from
        // the stub (which is empty). We assert it doesn't 500.
        \Illuminate\Support\Facades\Http::fake([
            'https://festivalapi.com/v1/festivals/9999/' => \Illuminate\Support\Facades\Http::response('', 500),
        ]);

        $response = $this->get(route('festivals.redirect', ['apiId' => 9999]));

        $response->assertStatus(302);
        // Redirect target is a valid URL (FilmFreeway root since stub
        // has no name to build a search from).
    }

    public function test_redirect_uses_cache_on_second_call(): void
    {
        // The 24h per-apiId cache means a revisit within the TTL costs
        // zero credits. Verify the detail endpoint is hit exactly once
        // across two consecutive redirect calls.
        \Illuminate\Support\Facades\Http::fake([
            'https://festivalapi.com/v1/festivals/9999/' => \Illuminate\Support\Facades\Http::response([
                'id' => 9999,
                'name' => 'Cached Fest',
                'submission_url' => 'https://filmfreeway.com/Cached',
                'website' => 'https://cached.com',
            ], 200),
        ]);

        \Illuminate\Support\Facades\Cache::flush();

        $this->get(route('festivals.redirect', ['apiId' => 9999]))->assertRedirect('https://filmfreeway.com/Cached');
        $this->get(route('festivals.redirect', ['apiId' => 9999]))->assertRedirect('https://filmfreeway.com/Cached');

        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }
}
