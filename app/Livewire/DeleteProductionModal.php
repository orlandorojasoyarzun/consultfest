<?php

namespace App\Livewire;

use App\Models\Production;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Confirmation modal for deleting a production. Replaces the native
 * `confirm()` prompt that used to live inline on /productions and
 * /productions/{id} so the destructive action gets the same cinema-
 * styled treatment as the rest of the app (subscribe modal, etc.).
 *
 * Wired from parent views via a tiny vanilla handler:
 *   `onclick="Livewire.dispatch('openDeleteModal', { id: 5 })"`
 * which any wire:click inside a Livewire parent would also do, but
 * productions/index and productions/show are plain Blade pages —
 * not Livewire components — so we use the global dispatch.
 *
 * The listener uses the `$listeners` array (not #[On]) because the
 * project's CSP setup drops #[On] / alias dispatches silently. See
 * memory: livewire-sibling-dispatch-pattern.
 */
class DeleteProductionModal extends Component
{
    /**
     * Visibility flag for the modal. Named `deleteModalOpen` (not the
     * generic `isOpen`) so the morph.updated hook in the view can read
     * this key off the snapshot without colliding with any other
     * Livewire component on the page that uses `isOpen`.
     */
    public bool $deleteModalOpen = false;

    /**
     * The id of the production pending deletion. Set by openDeleteModal;
     * consulted by confirmDelete to load + delete the row. null when no
     * modal is open so accidental clicks can't fire on a stale target.
     */
    public ?int $productionId = null;

    /**
     * True while the deletion is in flight. Drives a spinner inside
     * the confirm button so double-clicks are inert during the
     * round-trip.
     */
    public bool $isDeleting = false;

    protected $listeners = [
        'openDeleteModal' => 'openDeleteModal',
    ];

    /**
     * Open the modal for a given production id. Called via
     * `Livewire.dispatch('openDeleteModal', { id })` from the parent
     * Blade pages (or wire:click if the parent were Livewire).
     */
    public function openDeleteModal(int $id): void
    {
        $this->resetState();
        $this->productionId = $id;
        $this->deleteModalOpen = true;
    }

    public function closeDeleteModal(): void
    {
        $this->deleteModalOpen = false;
        $this->resetState();
    }

    /**
     * Perform the actual delete. We resolve the route here (rather than
     * wrapping the modal around a real <form method="POST">) so the
     * component stays self-contained and the parent views don't have
     * to coordinate a hidden form per row.
     *
     * The ownership check mirrors ProductionController::destroy — same
     * `subscriber_id` match on the session, no exceptions to surface.
     * If the check fails we 403 so a stale id on the page can't delete
     * someone else's row.
     */
    public function confirmDelete(): void
    {
        if (!$this->productionId) {
            return;
        }

        if ($this->isDeleting) {
            // Already mid-delete; ignore double-click.
            return;
        }

        $subscriberId = session('subscriber_id');
        if (!$subscriberId) {
            abort(403);
        }

        $production = Production::find($this->productionId);

        if (!$production || (int) $production->subscriber_id !== (int) $subscriberId) {
            abort(403);
        }

        $this->isDeleting = true;

        try {
            $production->delete();
        } catch (\Throwable $e) {
            Log::error('DeleteProductionModal::confirmDelete failed', [
                'production_id' => $this->productionId,
                'error' => $e->getMessage(),
            ]);
            $this->isDeleting = false;
            $this->deleteModalOpen = false;
            session()->flash('production-flash', 'No pudimos eliminar la producción. Intentá de nuevo.');
            return;
        }

        // Close the dialog and bounce to the productions list with the
        // standard "Producción eliminada" flash so it matches the
        // controller's flash message on the index page.
        $this->closeDeleteModal();
        session()->flash('production-flash', 'Producción eliminada.');

        $this->redirectRoute('productions.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.delete-production-modal');
    }

    private function resetState(): void
    {
        $this->productionId = null;
        $this->isDeleting = false;
    }
}