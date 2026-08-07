<?php

namespace App\Livewire;

use App\Services\FestivalRateLimitException;
use App\Services\FestivalSearchService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class FestivalResults extends Component
{
    use WithPagination;

    /**
     * Latest filters applied to the result set. Captured by the latest
     * `search-festivals` dispatch so pagination reuses them.
     *
     * @var array<string, mixed>
     */
    public array $filters = [];

    public bool $isLoading = false;

    /**
     * True when the last search was throttled by our internal rate-limit.
     * Drives the user-facing "Demasiadas búsquedas" message.
     */
    public bool $isRateLimited = false;

    protected $listeners = [
        'search-festivals' => 'search',
    ];

    public function boot(FestivalSearchService $search): void
    {
        $this->searchService = $search;
    }

    private FestivalSearchService $searchService;

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

        // Persist the last filters so paginate() can re-apply them when the
        // user clicks "next".
        $this->filters = $filters;
        // New search means we're back on page 1.
        $this->resetPage();

        $this->isLoading = false;
    }

    public function render()
    {
        // Run the search. We catch the rate-limit exception so the UI
        // degrades to a friendly message instead of a 500.
        try {
            $results = $this->searchService->search($this->filters);
            $this->isRateLimited = false;
        } catch (FestivalRateLimitException $e) {
            $results = collect();
            $this->isRateLimited = true;
        }

        // FestivalAPI returns up to 100 results, no pagination. We slice
        // locally to fit the 10-per-page UI. WithPagination's `gotoPage`
        // mutates $paginators['page']; reading it via getPage() honors it.
        $perPage = 10;
        $page = $this->getPage();
        $items = $results->slice(($page - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $results->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
                'query' => request()->query(),
            ],
        );

        return view('livewire.festival-results', [
            'paginator' => $paginator,
            'totalCount' => $results->count(),
            'festivals' => $items,
        ]);
    }

    /**
     * Livewire's WithPagination hook: tells the framework which view to
     * use for the paginator links. Our override lives in
     * resources/views/vendor/pagination/tailwind.blade.php with Spanish
     * labels.
     */
    public function paginationView(): string
    {
        return 'vendor.pagination.tailwind';
    }

    public function paginationSimpleView(): string
    {
        return 'vendor.pagination.simple-tailwind';
    }

    /**
     * Current page exposed to tests so they can verify pagination resets.
     */
    public function getCurrentPage(): int
    {
        return $this->getPage();
    }
}