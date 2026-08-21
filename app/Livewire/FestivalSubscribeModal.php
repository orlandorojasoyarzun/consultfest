<?php

namespace App\Livewire;

use App\Models\Festival;
use App\Models\Subscriber;
use App\Services\FestivalApiService;
use App\Services\FestivalSubscriptionService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Standalone subscribe-festival confirmation modal. Same UX as the
 * inlined modal in FestivalResults, but lives in its own component so
 * it can be reused from any page — e.g. productions/matches, which is
 * a plain Blade view (not inside FestivalResults).
 *
 * Wired from parent views via:
 *   `onclick="Livewire.dispatch('openSubscribeModal', { apiId: 12345 })"`
 * The listener uses the `$listeners` array because the project's CSP
 * setup drops #[On] / alias dispatches silently. See memory:
 * livewire-sibling-dispatch-pattern.
 *
 * Subscribe side-effects (DB write + email) go through
 * FestivalSubscriptionService so this component shares the exact code
 * path as FestivalResults::confirmSubscribe and
 * FestivalController::subscribe — single source of truth, no duplicate
 * "what does subscribe mean" logic.
 */
class FestivalSubscribeModal extends Component
{
    /**
     * Visibility flag. Named `subscribeModalOpen` (not generic `isOpen`)
     * so the morph.updated hook in the view can read this key off the
     * snapshot without colliding with any other Livewire component on
     * the page that uses `isOpen` (e.g. DeleteProductionModal).
     */
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
     * Optional map of apiId → festival name. The parent view can pass
     * it via `:festival-names="[...]"` so the modal title shows up
     * instantly without a second round-trip. We don't pass the name
     * through wire:click because @js() triggers a CSP unsafe-eval
     * error under `script-src 'self'`.
     *
     * @var array<int, string>
     */
    public array $festivalNames = [];

    protected $listeners = [
        'openSubscribeModal' => 'openSubscribeModal',
        // See UnsubscribeFestivalModal — same reason. The action buttons
        // (Cancelar / Confirmar suscripción / Cerrar error / dismiss
        // success toast) live inside a native <dialog> opened with
        // `showModal()`, and `wire:click` is unreliable there under
        // Livewire v4 + Alpine in production. Route them through the
        // same `livewireFire` helper the row button uses to OPEN the
        // modal so dispatch and listener share one code path.
        'closeSubscribeModal' => 'closeSubscribeModal',
        'confirmSubscribe' => 'confirmSubscribe',
        'dismissSubscribeSuccess' => 'dismissSubscribeSuccess',
    ];

    public function boot(
        FestivalApiService $api,
        FestivalSubscriptionService $subscriptionService,
    ): void {
        $this->apiService = $api;
        $this->subscriptionService = $subscriptionService;
    }

    private FestivalApiService $apiService;
    private FestivalSubscriptionService $subscriptionService;

    /**
     * Open the modal and fetch the festival preview. Called from the
     * `+ Suscribirme` button via
     * `Livewire.dispatch('openSubscribeModal', { apiId: 12345 })`.
     */
    public function openSubscribeModal(int $apiId): void
    {
        $this->resetSubscribeState();
        $this->subscribeFestivalApiId = $apiId;
        // Try to grab the name from the in-memory map so the modal
        // shows it instantly. If the parent didn't populate the map,
        // the DB lookup below fills it in.
        $this->subscribeFestivalName = $this->festivalNames[$apiId] ?? '';
        $this->subscribeModalOpen = true;
        $this->subscribeLoading = true;

        $subscriberId = session('subscriber_id');
        $this->subscribeSubscriberLoggedIn = $subscriberId !== null;
        $this->subscriberEmail = session('subscriber_email');

        // Self-heal: users who logged in before the bugfix that started
        // setting subscriber_email in the session will have subscriber_id
        // but no email key. Look it up from the DB once per request so
        // they don't have to log out / back in to recover.
        if ($this->subscribeSubscriberLoggedIn && !$this->subscriberEmail) {
            $this->subscriberEmail = Subscriber::whereKey($subscriberId)->value('email');
        }

        try {
            $festival = Festival::where('api_id', $apiId)->first();
            if (!$festival) {
                $synced = $this->apiService->syncFestivalDetails($apiId);
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
            Log::error('FestivalSubscribeModal::openSubscribeModal failed', [
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
     * Run the subscribe flow via FestivalSubscriptionService — same
     * service FestivalResults::confirmSubscribe uses. Going through
     * the service (instead of Http::post loopback) avoids the PHP
     * session-lock deadlock.
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
            Log::error('FestivalSubscribeModal::confirmSubscribe failed', [
                'api_id' => $this->subscribeFestivalApiId,
                'error' => $e->getMessage(),
            ]);
            $this->subscribeError = 'Error inesperado. Revisá tu conexión.';
        } finally {
            $this->subscribeLoading = false;
        }
    }

    public function render(): View
    {
        return view('livewire.festival-subscribe-modal');
    }

    private function hydrateSubscribeFromModel(Festival $festival): void
    {
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