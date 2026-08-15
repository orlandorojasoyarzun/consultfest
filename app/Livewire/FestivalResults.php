<?php

namespace App\Livewire;

use App\Services\FestivalApiService;
use App\Services\FestivalRateLimitException;
use App\Services\FestivalSearchService;
use Illuminate\Support\Collection;
use Livewire\Component;

class FestivalResults extends Component
{
    /**
     * Latest filters applied to the result set. Captured by the latest
     * `search-festivals` dispatch so pagination reuses them.
     *
     * @var array<string, mixed>
     */
    public array $filters = [];

    public bool $isLoading = false;

    /**
     * Current page of the local paginator. We don't use Livewire's
     * WithPagination because the underlying source is a Collection (the
     * API returns up to 100 items, no real DB query). Slicing client-side
     * with a tracked page number keeps the URL clean (no ?page= param) and
     * avoids the MethodNotAllowedHttpException you get when
     * $paginator->links() renders <a href> against the livewire/update route.
     */
    public int $page = 1;

    private const PER_PAGE = 10;

    /**
     * True when the last search was throttled by our internal rate-limit.
     * Drives the user-facing "Demasiadas búsquedas" message.
     */
    public bool $isRateLimited = false;

    /**
     * True when FestivalAPI is not configured on this deploy (no
     * FESTIVAL_API_KEY env var). We surface a friendly message instead of
     * letting the user stare at an empty list wondering if the search broke.
     */
    public bool $isApiNotConfigured = false;

    protected $listeners = [
        'search-festivals' => 'search',
    ];

    public function boot(FestivalSearchService $search, FestivalApiService $api): void
    {
        $this->searchService = $search;
        $this->apiService = $api;
    }

    private FestivalSearchService $searchService;
    private FestivalApiService $apiService;

    /**
     * Forward an open-subscribe-modal event to FestivalSubscribeModal.
     * The card's `wire:click="openSubscribeModal(...)"` invocation lands
     * here; we re-dispatch via Livewire v3's targeted `->to()` pattern
     * (same as FestivalCalendar::search uses for `search-festivals`).
     *
     * Important: this is a regular method, NOT a `#[On(...)]` listener.
     * If we registered both `wire:click` and `#[On('open-subscribe-modal')`
     * on the same handler, the listener would re-fire on every dispatch
     * (including the one we send here), causing either an infinite loop
     * or a stale event landing in the modal. The actual listener lives
     * on FestivalSubscribeModal and is the *target* of the dispatch.
     */
    public function openSubscribeModal(int $apiId, string $name): void
    {
        $this->dispatch('open-subscribe-modal', apiId: $apiId, name: $name)
            ->to('festival-subscribe-modal');
    }

    public function mount()
    {
        $this->search([
            'startDate' => now()->toDateString(),
            'endDate' => now()->addMonths(3)->toDateString(),
        ]);
    }

    public function search(array $filters)
    {
        $this->isLoading = true;
        $this->isRateLimited = false;
        $this->isApiNotConfigured = false;

        // Persist the last filters so paginate() can re-apply them when the
        // user clicks "next".
        $this->filters = $filters;
        // New search means we're back on page 1.
        $this->page = 1;

        $this->isLoading = false;
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function render()
    {
        // If FestivalAPI isn't wired up on this deploy, skip the search
        // round-trip entirely and surface a friendly message. Without this
        // guard the empty result list would be indistinguishable from a
        // broken search.
        if (!$this->apiService->isConfigured()) {
            $this->isApiNotConfigured = true;
            $results = collect();
            $totalPages = 1;
            $items = collect();
            $this->page = 1;
            return view('livewire.festival-results', [
                'totalCount' => 0,
                'festivals' => $items,
                'currentPage' => 1,
                'totalPages' => $totalPages,
            ]);
        }

        // Run the search. We catch the rate-limit exception so the UI
        // degrades to a friendly message instead of a 500.
        try {
            $results = $this->searchService->search($this->filters);
            $this->isRateLimited = false;
        } catch (FestivalRateLimitException $e) {
            $results = collect();
            $this->isRateLimited = true;
        }

        // FestivalAPI returns up to 100 results. We slice locally to fit
        // the 10-per-page UI.
        $perPage = self::PER_PAGE;
        $totalPages = max(1, (int) ceil($results->count() / $perPage));
        // Defensive: a stale $page from a previous larger result set could
        // point past the end. Clamp it.
        $this->page = min($this->page, $totalPages);

        $items = $results->slice(($this->page - 1) * $perPage, $perPage)
            ->values();

        // Detail enrichment used to happen here (1 credit per visible card,
        // 10/page, 100/page if you paginate). At ~5 credits per real visit
        // and the user's API budget, we switched to lazy enrichment:
        // detail is only fetched when the user actually clicks "Suscribirme"
        // or "Ver sitio" (see enrichAnd* methods). That keeps browsing the
        // list at 1 credit (list call) and only spends 1 more when the user
        // signals real intent — and it's cached 24h per apiId so the same
        // festival on the same day stays free.

        return view('livewire.festival-results', [
            'totalCount' => $results->count(),
            'festivals' => $items,
            'currentPage' => $this->page,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * Current page exposed to tests so they can verify pagination resets.
     */
    public function getCurrentPage(): int
    {
        return $this->page;
    }
}