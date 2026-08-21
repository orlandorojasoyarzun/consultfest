<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Confirmation modal for unsubscribing from a festival. Lives on the
 * dashboard ("Tus festivales" section) and replaces the native
 * `confirm()` prompt that used to ship with the inline form — the
 * native dialog bypassed the app's design language, said "127.0.0.1:8000
 * dice:" instead of branding, and clipped long festival names in an
 * ugly way. The cinema-styled <dialog> matches the delete-production
 * modal that the productions page already uses.
 *
 * Wired from the dashboard via the same `livewireFire()` helper that
 * the productions page uses for its delete button:
 *   `livewireFire('unsubscribe-festival-modal', 'openUnsubscribeModal',
 *                  { apiId: 5, name: 'Sitges' })`
 *
 * The listener uses `$listeners` (not #[On]) because the project's CSP
 * setup drops #[On] / alias dispatches silently — see memory:
 * livewire-sibling-dispatch-pattern.
 *
 * The actual delete lives in FestivalSubscriptionService so the
 * controller and this modal stay on a single code path.
 */
class UnsubscribeFestivalModal extends Component
{
    /**
     * Visibility flag for the modal. Named `unsubscribeModalOpen` (not
     * generic `isOpen`) so the morph.updated hook in the view can read
     * this key off the snapshot without colliding with the delete or
     * subscribe modals that may live on the same page.
     */
    public bool $unsubscribeModalOpen = false;

    /**
     * The FestivalAPI id of the subscription pending removal. Set by
     * openUnsubscribeModal; consulted by confirmUnsubscribe to delegate
     * to the service. null when no modal is open so accidental clicks
     * can't fire on a stale target.
     */
    public ?int $festivalApiId = null;

    /**
     * Festival name shown in the modal copy. Carried from the row that
     * fired the event so the modal can render before any round-trip.
     */
    public ?string $festivalName = null;

    /**
     * True while the unsubscribe is in flight. Drives a spinner inside
     * the confirm button so double-clicks are inert during the
     * round-trip — same anti-pattern guard DeleteProductionModal uses.
     */
    public bool $isProcessing = false;

    protected $listeners = [
        'openUnsubscribeModal' => 'openUnsubscribeModal',
        // Register the modal's own action methods as listeners too, so the
        // Confirmar / Cancelar buttons can dispatch them via the same
        // `livewireFire()` helper the row uses to OPEN the modal. `wire:click`
        // on elements inside a `<dialog>` opened with `showModal()` is
        // unreliable in Livewire v4 with Alpine — the dialog enters the
        // browser's top layer and Alpine's `@click` binding doesn't fire
        // consistently there. `livewireFire` dispatches a CustomEvent on the
        // component's root `<div>` (NOT on the dialog), which `listen2()`
        // catches regardless of where the trigger element lives.
        'closeUnsubscribeModal' => 'closeUnsubscribeModal',
        'confirmUnsubscribe' => 'confirmUnsubscribe',
    ];

    /**
     * Open the modal for a given festival. Called from the dashboard
     * festival row via livewireFire('unsubscribe-festival-modal', ...).
     *
     * Both the api id and the festival name come from the row that
     * fired the event so the modal can render its title and the
     * service call can target the right row without re-querying for the
     * name.
     */
    public function openUnsubscribeModal(int $apiId, string $name): void
    {
        $this->resetState();
        $this->festivalApiId = $apiId;
        $this->festivalName = $name;
        $this->unsubscribeModalOpen = true;
    }

    public function closeUnsubscribeModal(): void
    {
        $this->unsubscribeModalOpen = false;
        $this->resetState();
    }

    /**
     * Perform the actual unsubscribe. Delegates to
     * FestivalSubscriptionService::unsubscribe so the Livewire path and
     * the controller's DELETE /unsubscribe/{id} path stay in lockstep —
     * earlier the modal was calling the controller via Http::post()
     * loopback, which deadlocked the PHP session lock for 30s+; the
     * service call avoids that entirely.
     *
     * After success we close the modal and redirect to /dashboard with
     * a flash so the festival list re-renders without the removed row.
     * `navigate: true` triggers Livewire's SPA fetch so the page comes
     * back without a full reload flicker.
     */
    public function confirmUnsubscribe(): void
    {
        if (!$this->festivalApiId) {
            return;
        }

        if ($this->isProcessing) {
            return;
        }

        $subscriberId = session('subscriber_id');
        if (!$subscriberId) {
            abort(403);
        }

        $this->isProcessing = true;

        try {
            $result = app(\App\Services\FestivalSubscriptionService::class)
                ->unsubscribe($subscriberId, $this->festivalApiId);
        } catch (\Throwable $e) {
            Log::error('UnsubscribeFestivalModal::confirmUnsubscribe failed', [
                'festival_api_id' => $this->festivalApiId,
                'error' => $e->getMessage(),
            ]);
            $this->isProcessing = false;
            $this->closeUnsubscribeModal();
            session()->flash('auth-flash', 'No pudimos desuscribirte. Intentá de nuevo.');
            return;
        }

        $this->closeUnsubscribeModal();
        session()->flash('auth-flash', $result->message);

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.unsubscribe-festival-modal');
    }

    private function resetState(): void
    {
        $this->festivalApiId = null;
        $this->festivalName = null;
        $this->isProcessing = false;
    }
}
