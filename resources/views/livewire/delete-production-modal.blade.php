<div>
    {{-- ─────────────────────────────────────────────────────────────────
         Delete production confirmation modal. Native <dialog> so it pops
         up in the browser's top layer (avoids the containing-block bug
         that fixed-position modals hit when an ancestor uses
         transform/filter/backdrop-filter — same constraint the subscribe
         modal works around). Visibility is driven by Livewire state +
         a Livewire.hook at the bottom of the file that mirrors the
         subscribe modal's pattern.
         ───────────────────────────────────────────────────────────────── --}}

    <dialog
        id="delete-production-modal"
        class="bg-transparent p-0"
        style="margin: auto; max-width: 28rem; width: calc(100vw - 2rem); max-height: 90vh; padding: 0; border: 0; outline: 0; box-shadow: none; background: transparent; color-scheme: dark; border-radius: 0.75rem; overflow: hidden;"
        wire:click.self="closeDeleteModal"
    >
        <style>[open]#delete-production-modal{background:transparent;border:0;box-shadow:none;outline:0;padding:0;color-scheme:dark}[open]#delete-production-modal::backdrop{background-color:rgba(0,0,0,.6);backdrop-filter:blur(4px)}</style>
        <div
            class="cinema-card w-full max-h-[90vh] overflow-y-auto p-7 relative"
            style="border-radius: 0.75rem;"
            wire:click.stop
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-production-modal-title"
        >
            <div class="flex items-start gap-4 mb-5">
                <div class="shrink-0 w-10 h-10 rounded-full bg-[var(--danger-soft)] flex items-center justify-center">
                    <svg class="w-5 h-5 text-[var(--danger)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 id="delete-production-modal-title" class="text-lg font-semibold tracking-tight text-[var(--text-primary)]">
                        ¿Eliminar producción?
                    </h2>
                    <p class="mt-1.5 text-sm text-[var(--text-muted)] leading-relaxed">
                        Esta acción no se puede deshacer. Se eliminarán también las suscripciones y matches asociados.
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="closeDeleteModal"
                    class="text-[var(--text-faintest)] hover:text-[var(--text-secondary)] transition-colors shrink-0 -mt-1 -mr-1"
                    aria-label="Cerrar"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button
                    type="button"
                    wire:click="closeDeleteModal"
                    class="cinema-btn-outline px-4 py-2 text-sm"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    wire:click="confirmDelete"
                    wire:loading.attr="disabled"
                    wire:target="confirmDelete"
                    @disabled($isDeleting)
                    class="cinema-btn px-5 py-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    style="background-color: var(--danger); color: var(--accent-text);"
                >
                    <span wire:loading.remove wire:target="confirmDelete">Eliminar</span>
                    <span wire:loading wire:target="confirmDelete" class="inline-flex items-center justify-center gap-2">
                        <svg class="animate-spin h-3.5 w-3.5 inline-block align-middle shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="align-middle">Eliminando…</span>
                    </span>
                </button>
            </div>
        </div>
    </dialog>
</div>

{{-- Same hook the subscribe modal uses: read the latest snapshot of
     `deleteModalOpen` after every Livewire update and call showModal()/
     close() on the native <dialog>. Reading from the snapshot keeps
     the modal in sync with server state — earlier approaches that
     flipped a `hidden` class from a click handler were undone by
     morphdom reapplying `hidden` on the next request.

     Property name is intentionally `deleteModalOpen` (not generic
     `isOpen`) so this hook only acts on the DeleteProductionModal
     component and doesn't fight with other Livewire state. --}}
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', ({ el, component }) => {
            const state = component.snapshot?.data?.deleteModalOpen;
            if (state === undefined) return;
            const dialog = document.getElementById('delete-production-modal');
            if (!dialog) return;
            if (state === true) {
                if (!dialog.open) dialog.showModal();
            } else {
                if (dialog.open) dialog.close();
            }
        });

        // ESC closes the native <dialog> automatically; we listen for the
        // close event so Livewire state stays in sync (otherwise isOpen
        // stays true and the next morph cycle reopens the dialog).
        document.addEventListener('close', (e) => {
            if (e.target && e.target.id === 'delete-production-modal') {
                Livewire.dispatch('closeDeleteModal');
            }
        }, true);
    });
</script>