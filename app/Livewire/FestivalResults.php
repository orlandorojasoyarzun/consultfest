<?php

namespace App\Livewire;

use App\Models\Festival;
use App\Models\Subscriber;
use App\Services\FestivalApiService;
use App\Services\FestivalRateLimitException;
use App\Services\FestivalSearchService;
use App\Services\FestivalSubscriptionService;
use Illuminate\Support\Facades\Log;
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

    // ──────────────────────────────────────────────────────────────────
    // Subscribe modal state (inlined here so the button works without
    // cross-component dispatch). The modal markup is rendered inline at
    // the bottom of festival-results.blade.php; toggling $isOpen
    // directly from the wire:click handler is parent-child state, so
    // Livewire updates the modal reliably in production.
    // ──────────────────────────────────────────────────────────────────

    public bool $subscribeModalOpen = false;
    public ?int $subscribeFestivalApiId = null;
    public string $subscribeFestivalName = '';
    public string $subscribeCountry = '';
    public string $subscribeCity = '';
    public string $subscribePrimaryCategory = '';
    public string $subscribeDeadline = '';
    public string $subscribeOpeningDate = '';
    public string $subscribeRegularFee = '';
    public string $subscribeSubmissionUrl = '';
    public string $subscribeWebsite = '';
    public string $subscribeNotificationType = 'both';
    public bool $subscribeLoading = false;
    public ?string $subscribeError = null;
    public bool $subscribeSuccess = false;
    public ?string $subscriberEmail = null;
    public bool $subscribeEmailConfirmed = false;
    public bool $subscribeSubscriberLoggedIn = false;

    /**
     * Map of apiId → festival name for the festivals currently visible
     * on the page. Used by openSubscribeModal() to show the modal title
     * immediately without needing a second server round-trip. We don't
     * pass the name through wire:click because @js() triggers a CSP
     * unsafe-eval error under `script-src 'self'`.
     *
     * @var array<int, string>
     */
    public array $festivalNames = [];

    protected $listeners = [
        'search-festivals' => 'search',
    ];

    public function boot(
        FestivalSearchService $search,
        FestivalApiService $api,
        FestivalSubscriptionService $subscriptionService,
    ): void {
        $this->searchService = $search;
        $this->apiService = $api;
        $this->subscriptionService = $subscriptionService;
    }

    private FestivalSearchService $searchService;
    private FestivalApiService $apiService;
    private FestivalSubscriptionService $subscriptionService;

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

        $this->filters = $filters;
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

    // ──────────────────────────────────────────────────────────────────
    // Subscribe modal — open / close / confirm. All state lives here so
    // the button's wire:click just flips $subscribeModalOpen.
    // ──────────────────────────────────────────────────────────────────

    /**
     * Open the modal and fetch the festival preview. Called from the
     * `+ Suscribirme` button via `wire:click="openSubscribeModal({{ apiId }})"`.
     *
     * IMPORTANT: we pass only the integer apiId — NOT the festival name.
     * Passing the name would require `@js($festival->name)` in the blade,
     * which Livewire then evals as JavaScript. With CSP `script-src 'self'`
     * (no `unsafe-eval`), that eval throws and the wire:click never reaches
     * the server. Looking up the name from the in-memory search results
     * here keeps the button click safe under strict CSP.
     */
    public function openSubscribeModal(int $apiId): void
    {
        $this->resetSubscribeState();
        $this->subscribeFestivalApiId = $apiId;
        // Try to grab the name from the current page's results so the modal
        // shows it instantly. If the user paginated and the festival isn't
        // on screen, the sync below will populate it.
        $this->subscribeFestivalName = $this->festivalNames[$apiId] ?? '';
        $this->subscribeModalOpen = true;
        $this->subscribeLoading = true;
        $subscriberId = session('subscriber_id');
        $this->subscribeSubscriberLoggedIn = $subscriberId !== null;
        $this->subscriberEmail = session('subscriber_email');
        // Self-heal: users who logged in before the bugfix that started
        // setting subscriber_email in the session will have subscriber_id
        // but no email key, which makes the modal render the "you need an
        // account" branch. Look up the email from the DB once per request
        // so they don't have to log out / back in to recover.
        if ($this->subscribeSubscriberLoggedIn && !$this->subscriberEmail) {
            $this->subscriberEmail = Subscriber::whereKey($subscriberId)->value('email');
        }

        try {
            $festival = Festival::where('api_id', $apiId)->first();
            if (!$festival) {
                $synced = app(FestivalApiService::class)->syncFestivalDetails($apiId);
                if (!$synced) {
                    $this->subscribeError = 'No pudimos cargar la información del festival. Intentá de nuevo.';
                    return;
                }
                $festival = Festival::where('api_id', $apiId)->first();
            }

            if (!$festival) {
                $this->subscribeError = 'Festival no encontrado.';
                return;
            }

            $this->hydrateSubscribeFromModel($festival);
        } catch (\Throwable $e) {
            Log::error('FestivalResults::openSubscribeModal failed', [
                'api_id' => $apiId,
                'error' => $e->getMessage(),
            ]);
            $this->subscribeError = 'Error inesperado al cargar el festival.';
        } finally {
            $this->subscribeLoading = false;
        }
    }

    public function closeSubscribeModal(): void
    {
        $this->subscribeModalOpen = false;
        $this->resetSubscribeState();
    }

    public function dismissSubscribeSuccess(): void
    {
        $this->subscribeSuccess = false;
        $this->resetSubscribeState();
    }

    /**
     * Run the subscribe flow via FestivalSubscriptionService — NOT via
     * Http::post() to our own /festivals/subscribe route. The loopback
     * pattern deadlocks the PHP session lock (the Livewire request holds
     * it, the loopback request waits for it, and PHP max_execution_time
     * fires at 30s). Going through the service skips the round-trip
     * entirely and shares the same code path as FestivalController::subscribe.
     */
    public function confirmSubscribe(): void
    {
        if (!$this->subscribeFestivalApiId) {
            $this->subscribeError = 'Festival inválido.';
            return;
        }

        if (!$this->subscribeSubscriberLoggedIn) {
            $this->subscribeError = 'Necesitás tener una cuenta para suscribirte.';
            return;
        }

        if (!$this->subscribeEmailConfirmed) {
            $this->subscribeError = 'Confirmá que el email es correcto antes de suscribirte.';
            return;
        }

        $this->subscribeLoading = true;
        $this->subscribeError = null;

        try {
            $result = $this->subscriptionService->subscribe(
                (int) session('subscriber_id'),
                $this->subscribeFestivalApiId,
                $this->subscribeNotificationType,
            );

            if ($result->ok) {
                $this->subscribeSuccess = true;
                $this->subscribeModalOpen = false;
                return;
            }

            $this->subscribeError = $result->error ?? 'No pudimos completar la suscripción.';
        } catch (\Throwable $e) {
            Log::error('FestivalResults::confirmSubscribe failed', [
                'api_id' => $this->subscribeFestivalApiId,
                'error' => $e->getMessage(),
            ]);
            $this->subscribeError = 'Error inesperado. Revisá tu conexión.';
        } finally {
            $this->subscribeLoading = false;
        }
    }

    public function render()
    {
        if (!$this->apiService->isConfigured()) {
            $this->isApiNotConfigured = true;
            $results = collect();
            $totalPages = 1;
            $items = collect();
            $this->page = 1;
            $this->festivalNames = [];
            return view('livewire.festival-results', [
                'totalCount' => 0,
                'festivals' => $items,
                'currentPage' => 1,
                'totalPages' => $totalPages,
            ]);
        }

        try {
            $results = $this->searchService->search($this->filters);
            $this->isRateLimited = false;
        } catch (FestivalRateLimitException $e) {
            $results = collect();
            $this->isRateLimited = true;
        }

        $perPage = self::PER_PAGE;
        $totalPages = max(1, (int) ceil($results->count() / $perPage));
        $this->page = min($this->page, $totalPages);

        $items = $results->slice(($this->page - 1) * $perPage, $perPage)
            ->values();

        // Snapshot the visible page's apiId → name so openSubscribeModal
        // can fill $subscribeFestivalName without a second round-trip and
        // without passing the name through @js() (CSP unsafe-eval kills that).
        $this->festivalNames = [];
        foreach ($items as $f) {
            $this->festivalNames[$f->apiId] = $f->name;
        }

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

    private function hydrateSubscribeFromModel(Festival $festival): void
    {
        // Authoritative source: the synced Festival row. This handles the
        // case where the user opened the modal for a festival that's NOT on
        // the currently visible page (so festivalNames has no entry).
        $this->subscribeFestivalName = $festival->name ?? '';
        $this->subscribeCountry = $festival->country ?? '';
        $details = $festival->details ?? [];
        $this->subscribeCity = is_string($details['city'] ?? null) ? $details['city'] : '';
        $this->subscribePrimaryCategory = $festival->category ?? '';
        $this->subscribeDeadline = $festival->deadline?->format('M d, Y') ?? '';
        $this->subscribeOpeningDate = $festival->opening_date?->format('M d, Y') ?? '';
        $this->subscribeRegularFee = $festival->submission_fee !== null
            ? '$' . number_format($festival->submission_fee, 2)
            : '';
        $this->subscribeSubmissionUrl = $this->extractUrl($details['submission_url'] ?? null);
        $this->subscribeWebsite = $this->extractUrl($details['website'] ?? $details['url'] ?? null);
    }

    private function extractUrl(mixed $candidate): string
    {
        if (!is_string($candidate)) {
            return '';
        }
        $trimmed = trim($candidate);
        return $trimmed === '' ? '' : $trimmed;
    }

    private function resetSubscribeState(): void
    {
        $this->subscribeFestivalApiId = null;
        $this->subscribeFestivalName = '';
        $this->subscribeCountry = '';
        $this->subscribeCity = '';
        $this->subscribePrimaryCategory = '';
        $this->subscribeDeadline = '';
        $this->subscribeOpeningDate = '';
        $this->subscribeRegularFee = '';
        $this->subscribeSubmissionUrl = '';
        $this->subscribeWebsite = '';
        $this->subscribeNotificationType = 'both';
        $this->subscribeLoading = false;
        $this->subscribeError = null;
        $this->subscribeSuccess = false;
        $this->subscriberEmail = null;
        $this->subscribeEmailConfirmed = false;
        $this->subscribeSubscriberLoggedIn = false;
    }
}