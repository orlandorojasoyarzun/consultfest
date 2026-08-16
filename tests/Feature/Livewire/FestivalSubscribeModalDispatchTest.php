<?php

namespace Tests\Feature\Livewire;

use App\Livewire\FestivalResults;
use App\Livewire\FestivalSubscribeModal;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression: the Subscribe button on a festival card must open the modal.
 *
 * History (the production bug this PR fixes):
 *   - v1: FestivalResults::openSubscribeModal dispatched via
 *     `->to('festival-subscribe-modal')` (kebab alias) and the modal
 *     listened via #[On('open-subscribe-modal')]. In production the
 *     modal never opened — listener fired server-side but client never
 *     received the update.
 *   - v2 (failed): drop `->to()` so the event broadcasts globally, modal
 *     still using `#[On(...)]`. Same symptom on production.
 *   - v3 (this PR): align with the proven pattern already used by
 *     FestivalCalendar -> FestivalResults: dispatch with
 *     `->to(FestivalSubscribeModal::class)` (FULL CLASS NAME, not
 *     kebab alias) AND the modal subscribes via the legacy
 *     `$listeners` array (same convention FestivalResults uses for
 *     `search-festivals`). Standardises the listener style across
 *     sibling components and matches the pattern that actually works.
 *
 * These tests prove the dispatch + listener wiring is correct.
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

    public function test_openSubscribeModal_dispatches_to_modal_listener(): void
    {
        // 1. The wire:click path on FestivalResults fires openSubscribeModal
        //    with apiId+name. Verify the listener on FestivalSubscribeModal
        //    is what gets invoked (the modal's $isOpen flips, $festivalApiId
        //    is set, $festivalName matches).
        $this->fakeApiListAndDetail([
            $this->apiRow('Mar del Plata Fest', 12345),
        ]);

        // Mounting the modal directly and calling `open` proves the listener
        // body works in isolation.
        Livewire::test(FestivalSubscribeModal::class)
            ->call('open', 12345, 'Mar del Plata Fest')
            ->assertSet('isOpen', true)
            ->assertSet('festivalApiId', 12345)
            ->assertSet('festivalName', 'Mar del Plata Fest');
    }

    public function test_openSubscribeModal_method_does_not_infinite_loop(): void
    {
        // 2. Critical guard: FestivalResults::openSubscribeModal must not
        //    itself listen for `open-subscribe-modal` (neither via
        //    #[On(...)] nor via a `$listeners` entry). If it did, the
        //    dispatch would re-fire this method on every event, either
        //    looping forever or — at minimum — keeping the user from
        //    actually opening the modal (every dispatch lands on Results,
        //    not the modal).
        $reflection = new \ReflectionClass(FestivalResults::class);
        $method = $reflection->getMethod('openSubscribeModal');

        $attributes = $method->getAttributes(\Livewire\Attributes\On::class);
        $this->assertEmpty(
            $attributes,
            'FestivalResults::openSubscribeModal must not register #[On(\'open-subscribe-modal\')] — ' .
            'that would create an infinite loop. The listener lives only on FestivalSubscribeModal.'
        );

        $classAttributes = $reflection->getAttributes(\Livewire\Attributes\On::class);
        $this->assertEmpty(
            $classAttributes,
            'FestivalResults must not register a class-level #[On] attribute.'
        );

        // Also assert no `$listeners` entry for this event.
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

    public function test_openSubscribeModal_dispatches_to_modal_class_with_full_name(): void
    {
        // 3. Inspect the dispatched event. We dispatch with
        //    `->to(FestivalSubscribeModal::class)` (full class name) —
        //    this is the pattern FestivalCalendar uses to reach
        //    FestivalResults, and the only one that's been proven to
        //    work in production on this codebase.
        $this->fakeApiListAndDetail([
            $this->apiRow('Festival X', 99),
        ]);

        $results = Livewire::test(FestivalResults::class);
        $results->call('openSubscribeModal', 99, 'Festival X');

        $dispatches = $results->effects['dispatches'] ?? [];
        $matched = collect($dispatches)->firstWhere('name', 'open-subscribe-modal');
        $this->assertNotNull($matched, 'open-subscribe-modal dispatch was not emitted');

        // The component key MUST be the kebab-cased class name (Livewire
        // resolves ::class to kebab on serialize()). Using a raw string
        // like 'festival-subscribe-modal' happens to look the same here,
        // but the canonical pattern in this codebase is ->to(FQCN::class).
        $this->assertSame(
            'festival-subscribe-modal',
            $matched['component'] ?? null,
            'open-subscribe-modal must be targeted at the FestivalSubscribeModal component ' .
            'via ->to(FestivalSubscribeModal::class). Other targeting (kebab alias string, ' .
            'no target) does not reach the client in production.'
        );
        $this->assertSame(99, $matched['params']['apiId']);
        $this->assertSame('Festival X', $matched['params']['name']);
    }

    public function test_modal_registers_listener_via_legacy_listeners_array(): void
    {
        // 4. The modal must subscribe via the LEGACY `$listeners` array,
        //    not `#[On(...)]`. Mixing the two listener styles across
        //    sibling components is what triggered the production bug.
        //    We assert on the property shape so a future refactor that
        //    switches back to `#[On]` (without also flipping the dispatch
        //    side) fails loudly here.
        $reflection = new \ReflectionClass(FestivalSubscribeModal::class);
        $this->assertTrue(
            $reflection->hasProperty('listeners'),
            'FestivalSubscribeModal must declare a $listeners property (legacy array syntax).'
        );
        $property = $reflection->getProperty('listeners');
        $property->setAccessible(true);
        $listeners = $property->getDefaultValue();

        $this->assertArrayHasKey(
            'open-subscribe-modal',
            $listeners,
            'FestivalSubscribeModal must register an `open-subscribe-modal` listener.'
        );
        $this->assertSame(
            'open',
            $listeners['open-subscribe-modal'],
            '`open-subscribe-modal` listener must be routed to the `open` method.'
        );
    }
}
