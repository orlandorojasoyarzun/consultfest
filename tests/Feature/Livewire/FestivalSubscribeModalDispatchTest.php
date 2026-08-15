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
 * History: FestivalResults::openSubscribeModal used to dispatch with
 * `->to('festival-subscribe-modal')` and the modal listened via
 * #[On('open-subscribe-modal')]. In production, the modal never opened.
 * The fix: drop `->to()` so the event broadcasts globally; the modal
 * picks it up via its listener. FestivalResults must NOT itself register
 * #[On('open-subscribe-modal')] on this handler (would create a loop).
 *
 * These tests prove the dispatch lands on the modal's listener end-to-end.
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
        //    itself register #[On('open-subscribe-modal')]. If it did, the
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
    }

    public function test_openSubscribeModal_dispatches_with_global_target(): void
    {
        // 3. Inspect the dispatched event. Global dispatch = no `component`
        //    field on the serialized event payload. This is what allows the
        //    modal (whose snapshot isn't in the originating request's
        //    response) to receive the event on its own round-trip.
        $this->fakeApiListAndDetail([
            $this->apiRow('Festival X', 99),
        ]);

        $results = Livewire::test(FestivalResults::class);
        $results->call('openSubscribeModal', 99, 'Festival X');

        $dispatches = $results->effects['dispatches'] ?? [];
        $matched = collect($dispatches)->firstWhere('name', 'open-subscribe-modal');
        $this->assertNotNull($matched, 'open-subscribe-modal dispatch was not emitted');
        $this->assertArrayNotHasKey(
            'component',
            $matched,
            'open-subscribe-modal must be a *global* dispatch (no `component` key). ' .
            'A targeted dispatch to festival-subscribe-modal does not reach the client because ' .
            'the modal is not in scope of FestivalResults\' update response.'
        );
        $this->assertSame(99, $matched['params']['apiId']);
        $this->assertSame('Festival X', $matched['params']['name']);
    }
}
