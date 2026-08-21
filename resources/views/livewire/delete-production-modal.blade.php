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
        style="margin: auto; max-width: 32rem; width: calc(100vw - 2rem); max-height: 90vh; padding: 0; border: 0; outline: 0; box-shadow: none; background: transparent; color-scheme: dark; border-radius: 0.75rem; overflow: hidden;"
        onclick="if(event.target===this && event.isTrusted && !this._ignoreNextClick)livewireFire('delete-production-modal','closeDeleteModal')"
    >
        <style>[open]#delete-production-modal{background:transparent;border:0;box-shadow:none;outline:0;padding:0;color-scheme:dark}[open]#delete-production-modal::backdrop{background-color:rgba(0,0,0,.6);backdrop-filter:blur(4px)}@keyframes dpm-in{from{opacity:0;transform:translateY(8px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}[open]#delete-production-modal .cinema-card{animation:dpm-in 220ms cubic-bezier(.2,.7,.2,1) both}</style>
        <div
            class="cinema-card w-full max-h-[90vh] overflow-y-auto px-14 sm:px-20 py-20 sm:py-24 relative text-center"
            style="border-radius: 0.75rem;"
            onclick="event.stopPropagation()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-production-modal-title"
        >
            {{-- Centered column: icon → title → description. No top-right
                 close X because the native <dialog> already listens to ESC
                 and the Cancelar button covers the explicit-close path —
                 keeping a third close affordance here unbalanced the
                 visual weight and made the description text feel
                 off-center against the icon. --}}
            <div class="flex flex-col items-center text-center">
                <div class="shrink-0 w-11 h-11 rounded-full bg-[var(--danger-soft)] flex items-center justify-center mb-5">
                    <svg class="w-5 h-5 text-[var(--danger)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                </div>
                <h2 id="delete-production-modal-title" class="text-lg font-semibold tracking-tight text-[var(--text-primary)]">
                    ¿Eliminar producción?
                </h2>
                <p class="mt-3 text-sm text-[var(--text-muted)] leading-relaxed max-w-[18rem] mx-auto">
                    Esta acción no se puede deshacer. Se eliminarán también las suscripciones y matches asociados.
                </p>
            </div>

            {{-- Actions sit on a centered row so the modal reads 1:1
                 symmetric top-to-bottom — icon column above, button row
                 below, both anchored to the horizontal center. No fixed
                 min-width on the buttons so they shrink gracefully on
                 narrow viewports without overflowing the padding
                 (px-20 + two min-w-[7rem] buttons was 230px in a 163px
                 content area on a 360px screen — buttons touched the
                 modal edges and the layout read as cramped). Generous
                 mt-48 + mt-56 separates the description from the
                 action row so neither column crowds the other. No
                 divider line — the gap alone reads as separation, and
                 a full-width border made the modal feel sliced. --}}
            <div class="flex items-center justify-center gap-4 mt-44 sm:mt-56">
                <button
                    type="button"
                    onclick="livewireFire('delete-production-modal','closeDeleteModal')"
                    class="cinema-btn-outline px-6 py-2.5 text-sm"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    onclick="livewireFire('delete-production-modal','confirmDelete')"
                    wire:loading.attr="disabled"
                    wire:target="confirmDelete"
                    @disabled($isDeleting)
                    class="cinema-btn px-6 py-2.5 text-sm disabled:opacity-50 disabled:cursor-not-allowed"
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
                if (!dialog.open) {
                    // Some browsers fire a click on the <dialog> during
                    // showModal() — synthetic, but with isTrusted=true on
                    // Safari. Block backdrop-close for a beat so the click
                    // doesn't auto-dismiss the modal that just opened.
                    dialog._ignoreNextClick = true;
                    dialog.showModal();
                    setTimeout(() => { dialog._ignoreNextClick = false; }, 80);
                }
            } else {
                if (dialog.open) dialog.close();
            }
        });

        // ESC closes the native <dialog> automatically; we listen for the
        // close event so Livewire state stays in sync (otherwise isOpen
        // stays true and the next morph cycle reopens the dialog).
        // Dispatch the closeDeleteModal event on this component's own root
        // element (closest [wire\\:id]) — that's where Livewire v4's
        // `listen2()` attaches `$listeners` entries.
        document.addEventListener('close', (e) => {
            if (!e.target || e.target.id !== 'delete-production-modal') return;
            const root = e.target.closest('[wire\\:id]');
            if (root) {
                root.dispatchEvent(new CustomEvent('closeDeleteModal', { bubbles: true }));
            }
        }, true);
    });
</script>