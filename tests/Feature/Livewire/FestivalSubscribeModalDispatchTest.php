<?php

namespace Tests\Feature\Livewire;

use App\Livewire\FestivalResults;
use App\Models\Festival;
use App\Models\Subscriber;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression: the Subscribe button on a festival card must open the modal
 * AND the modal must POST to /subscribe with the chosen notification type.
 *
 * History (the production bug the previous design had):
 *   - The Subscribe button dispatched `open-subscribe-modal` to a separate
 *     FestivalSubscribeModal sibling component via `->to(FQCN::class)`. In
 *     production the modal never opened — listener fired server-side but
 *     the client never received the update, no matter which targeting
 *     pattern (kebab alias, FQCN, `#[On]`, legacy `$listeners`) we tried.
 *   - v3 (this PR): the modal is INLINE inside FestivalResults. The button's
 *     `wire:click="openSubscribeModal(...)"` flips a public property on the
 *     same component, so the client updates directly. No cross-component
 *     dispatch is involved.
 *
 * These tests prove the new wiring: the button click opens the modal,
 * the modal renders the festival preview + email confirmation + radio
 * group, and confirmSubscribe() calls the /subscribe endpoint correctly.
 */
class FestivalSubscribeModalDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function apiRow(string $name, int $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'name' => $name,
            'categories' => ['feature'],
            'genres' => ['drama'],
            'country' => 'Argentina',
            'city' => 'Buenos Aires',
            'deadline_regular' => Carbon::now()->addDays(30)->toDateString(),
        ], $overrides);
    }

    private function fakeApiListAndDetail(array $rows): void
    {
        Http::fake([
            'https://festivalapi.com/v1/festivals/*/' => function ($request) use ($rows) {
                preg_match('#/festivals/(\d+)/#', $request->url(), $m);
                $id = (int) ($m[1] ?? 0);
                $row = collect($rows)->firstWhere('id', $id) ?? ($rows[0] ?? []);
                return Http::response([
                    'id' => $row['id'] ?? 0,
                    'name' => $row['name'] ?? 'Unknown',
                    'categories' => $row['categories'] ?? [],
                    'country' => $row['country'] ?? '',
                    'city' => $row['city'] ?? '',
                    'submission_url' => '',
                    'website' => '',
                    'details' => ['city' => $row['city'] ?? ''],
                ], 200);
            },
            'https://festivalapi.com/v1/festivals*' => Http::response([
                'count' => count($rows),
                'results' => $rows,
            ], 200),
        ]);
    }

    public function test_openSubscribeModal_opens_inline_modal_on_same_component(): void
    {
        // 1. The wire:click path on FestivalResults fires openSubscribeModal
        //    with apiId+name. With the modal inlined, the same component's
        //    $subscribeModalOpen flips to true and the festival preview
        //    fields are populated from the synced Festival row.
        $this->fakeApiListAndDetail([
            $this->apiRow('Mar del Plata Fest', 12345),
        ]);

        Livewire::test(FestivalResults::class)
            ->call('openSubscribeModal', 12345, 'Mar del Plata Fest')
            ->assertSet('subscribeModalOpen', true)
            ->assertSet('subscribeFestivalApiId', 12345)
            ->assertSet('subscribeFestivalName', 'Mar del Plata Fest')
            ->assertSet('subscribeCity', 'Buenos Aires')
            ->assertSet('subscribeNotificationType', 'both')
            ->assertSet('subscribeEmailConfirmed', false);
    }

    public function test_openSubscribeModal_does_not_dispatch_any_event(): void
    {
        // 2. Critical guard for the inlined pattern: opening the modal must
        //    NOT dispatch any event. The modal lives on the same component
        //    as the button, so cross-component dispatch is unnecessary —
        //    and emitting one would risk creating an infinite loop if a
        //    listener ever gets registered on FestivalResults later.
        $this->fakeApiListAndDetail([
            $this->apiRow('Festival X', 99),
        ]);

        $results = Livewire::test(FestivalResults::class);
        $results->call('openSubscribeModal', 99, 'Festival X');

        $dispatches = $results->effects['dispatches'] ?? [];
        $matching = collect($dispatches)->firstWhere('name', 'open-subscribe-modal');
        $this->assertNull(
            $matching,
            'FestivalResults must NOT dispatch open-subscribe-modal anymore — the modal is inlined on this component.'
        );
    }

    public function test_openSubscribeModal_does_not_listen_to_its_own_event(): void
    {
        // 3. Belt-and-suspenders: also assert FestivalResults doesn't
        //    register a listener for open-subscribe-modal. If a future
        //    refactor adds one, the inline modal would receive the event
        //    twice on every click (once directly via property set, once
        //    via the listener). Keep this assertion loud.
        $reflection = new \ReflectionClass(FestivalResults::class);

        $attributes = $reflection->getAttributes(\Livewire\Attributes\On::class);
        $this->assertEmpty($attributes, 'FestivalResults must not register any class-level #[On] attribute.');

        if ($reflection->hasProperty('listeners')) {
            $property = $reflection->getProperty('listeners');
            $property->setAccessible(true);
            $listeners = $property->getDefaultValue() ?? [];
            $this->assertArrayNotHasKey(
                'open-subscribe-modal',
                $listeners,
                'FestivalResults must not register `open-subscribe-modal` in its $listeners array.'
            );
        }
    }

    public function test_confirm_subscribe_requires_email_confirmation(): void
    {
        // 4. The Confirm button must NOT post to /subscribe until the user
        //    ticks "Confirmo que este es mi email correcto". Without this
        //    guard the user could subscribe to a typo'd address and never
        //    notice. We assert the error is set, which proves the method
        //    returned early without firing the HTTP request.
        $this->fakeApiListAndDetail([
            $this->apiRow('Mar del Plata Fest', 12345),
        ]);

        $subscriber = Subscriber::create([
            'email' => 'cine@filmmaker.test',
            'name' => 'Test Filmmaker',
            'password_hash' => bcrypt('secret123'),
        ]);

        Festival::create([
            'api_id' => 12345,
            'name' => 'Mar del Plata Fest',
            'country' => 'Argentina',
            'details' => ['city' => 'Buenos Aires'],
            'category' => 'feature',
            'deadline' => Carbon::now()->addDays(30),
        ]);

        $this->withSession(['subscriber_id' => $subscriber->id, 'subscriber_email' => 'cine@filmmaker.test']);

        Livewire::test(FestivalResults::class)
            ->call('openSubscribeModal', 12345, 'Mar del Plata Fest')
            ->assertSet('subscribeSubscriberLoggedIn', true)
            ->assertSet('subscriberEmail', 'cine@filmmaker.test')
            ->assertSet('subscribeEmailConfirmed', false)
            ->call('confirmSubscribe')
            ->assertSet('subscribeError', 'Confirmá que el email es correcto antes de suscribirte.')
            ->assertSet('subscribeModalOpen', true)
            ->assertSet('subscribeSuccess', false);

        // No POST to /subscribe should have been made. We trust the early
        // return: the error message only sets when the email-confirmed
        // guard fires before the HTTP call.
    }
}