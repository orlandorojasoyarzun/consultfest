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

        $subscriber = Subscriber::factory()->create();
        session(['subscriber_id' => $subscriber->id]);

        $response = $this->postJson(route('festivals.subscribe'), [
            'festival_api_id' => 99999999,
            'notification_type' => 'opening',
        ]);

        $response->assertStatus(404);
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
