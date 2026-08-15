<?php

namespace App\Livewire;

use App\Models\Festival;
use App\Services\FestivalApiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Preview-and-confirm modal for subscribing to a festival from the
 * /festivals list. The card in festival-results dispatches
 * `open-subscribe-modal` with the apiId + name; we hydrate the preview
 * here (from local DB if cached, otherwise from FestivalAPI detail),
 * ask the user to pick a notification_type, and POST to /subscribe.
 *
 * Why a Livewire modal instead of a plain HTML form:
 *  - We want to fetch the detail payload *before* showing the modal, so
 *    the user sees real data (not just the name we already had).
 *  - We want to keep the modal open with success/error feedback; a
 *    plain form POST would close the page.
 *  - We want to centralize the "subscribed" state so the card can
 *    update visually (eventually — toggling subscription status
 *    visually is out of scope for this PR).
 *
 * Out of scope:
 *  - Updating the parent card's state after a successful subscribe
 *    (we just close the modal; the user reloads to see the change).
 */
class FestivalSubscribeModal extends Component
{
    public bool $isOpen = false;
    public ?int $festivalApiId = null;
    public string $festivalName = '';

    // Hydrated from FestivalAPI on open. Empty strings represent "absent".
    public string $country = '';
    public string $city = '';
    public string $primaryCategory = '';
    public string $deadline = '';
    public string $openingDate = '';
    public string $regularFee = '';
    public string $submissionUrl = '';
    public string $website = '';

    public string $notificationType = 'both';

    public bool $isLoading = false;
    public ?string $error = null;
    public bool $success = false;

    /**
     * Open the modal and fetch the preview data. We try the local DB first
     * (zero credits if already synced); fall back to FestivalAPI detail.
     */
    #[On('open-subscribe-modal')]
    public function open(int $apiId, string $name): void
    {
        $this->resetState();
        $this->festivalApiId = $apiId;
        $this->festivalName = $name;
        $this->isOpen = true;
        $this->isLoading = true;

        try {
            $festival = Festival::where('api_id', $apiId)->first();
            if (!$festival) {
                // Sync on-demand; the controller endpoint also does this,
                // but we want to show the preview *before* the user
                // commits. Costs 1 FestivalAPI credit.
                $synced = app(FestivalApiService::class)->syncFestivalDetails($apiId);
                if (!$synced) {
                    $this->error = 'No pudimos cargar la información del festival. Intentá de nuevo.';
                    return;
                }
                $festival = Festival::where('api_id', $apiId)->first();
            }

            if (!$festival) {
                $this->error = 'Festival no encontrado.';
                return;
            }

            $this->hydrateFromModel($festival);
        } catch (\Throwable $e) {
            Log::error('FestivalSubscribeModal: open failed', [
                'api_id' => $apiId,
                'error' => $e->getMessage(),
            ]);
            $this->error = 'Error inesperado al cargar el festival.';
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Close the modal and reset transient state. Keeps the listener
     * registered for the next open.
     */
    public function close(): void
    {
        $this->isOpen = false;
        $this->resetState();
    }

    /**
     * POST to /subscribe. On success, show confirmation and close after 2s.
     * On failure, show the error inline (no auto-close).
     */
    public function confirm(): void
    {
        if (!$this->festivalApiId) {
            $this->error = 'Festival inválido.';
            return;
        }

        $this->isLoading = true;
        $this->error = null;

        try {
            $response = Http::asForm()->post(route('festivals.subscribe'), [
                'festival_api_id' => $this->festivalApiId,
                'notification_type' => $this->notificationType,
            ]);

            if ($response->successful()) {
                $this->success = true;
                $this->isOpen = false; // close modal visually
                return;
            }

            $payload = $response->json();
            $this->error = $payload['error'] ?? 'No pudimos completar la suscripción.';
        } catch (\Throwable $e) {
            Log::error('FestivalSubscribeModal: confirm failed', [
                'api_id' => $this->festivalApiId,
                'error' => $e->getMessage(),
            ]);
            $this->error = 'Error de red. Revisá tu conexión.';
        } finally {
            $this->isLoading = false;
        }
    }

    public function dismissSuccess(): void
    {
        $this->success = false;
        $this->resetState();
    }

    public function render()
    {
        return view('livewire.festival-subscribe-modal');
    }

    private function hydrateFromModel(Festival $festival): void
    {
        $this->country = $festival->country ?? '';
        // `city` is not a dedicated column — it lives inside the raw
        // `details` JSON payload that FestivalAPI returns and we store
        // verbatim. The list endpoint sometimes has it; the detail
        // endpoint always does.
        $details = $festival->details ?? [];
        $this->city = is_string($details['city'] ?? null) ? $details['city'] : '';
        $this->primaryCategory = $festival->category ?? '';
        $this->deadline = $festival->deadline?->format('M d, Y') ?? '';
        $this->openingDate = $festival->opening_date?->format('M d, Y') ?? '';
        $this->regularFee = $festival->submission_fee !== null
            ? '$' . number_format($festival->submission_fee, 2)
            : '';
        $this->submissionUrl = $this->extractUrl($details['submission_url'] ?? null);
        $this->website = $this->extractUrl($details['website'] ?? $details['url'] ?? null);
    }

    private function extractUrl(mixed $candidate): string
    {
        if (!is_string($candidate)) {
            return '';
        }
        $trimmed = trim($candidate);
        return $trimmed === '' ? '' : $trimmed;
    }

    private function resetState(): void
    {
        $this->festivalApiId = null;
        $this->festivalName = '';
        $this->country = '';
        $this->city = '';
        $this->primaryCategory = '';
        $this->deadline = '';
        $this->openingDate = '';
        $this->regularFee = '';
        $this->submissionUrl = '';
        $this->website = '';
        $this->notificationType = 'both';
        $this->isLoading = false;
        $this->error = null;
        $this->success = false;
    }
}
