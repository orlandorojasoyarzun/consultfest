<div>
    {{-- ─────────────────────────────────────────────────────────────────
         Unsubscribe-festival confirmation modal. Native <dialog> so it
         pops up in the browser's top layer (same containing-block
         workaround the delete and subscribe modals use). Visibility
         driven by Livewire state + the morph.updated hook at the
         bottom of this file.

         The icon here is a neutral minus-circle in muted colors —
         unsubscribe is destructive (the user loses a future email)
         but reversible (one more click re-subscribes them), so it
         doesn't earn the danger-red treatment the delete-production
         modal uses. Keeping the visual weight lower also reads as
         "you're pausing something" instead of "this is permanent",
         which is the actual semantic.
         ───────────────────────────────────────────────────────────────── --}}

    <dialog
        wire:ignore.self
        id="unsubscribe-festival-modal"
        class="bg-transparent p-0"
        style="margin: auto; max-width: 32rem; width: calc(100vw - 2rem); max-height: 90vh; padding: 0; border: 0; outline: 0; box-shadow: none; background: transparent; color-scheme: dark; border-radius: 0.75rem; overflow: hidden;"
    >
        <style>[open]#unsubscribe-festival-modal{background:transparent;border:0;box-shadow:none;outline:0;padding:0;color-scheme:dark}[open]#unsubscribe-festival-modal::backdrop{background-color:rgba(0,0,0,.6);backdrop-filter:blur(4px)}@keyframes ufm-in{from{opacity:0;transform:translateY(8px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}[open]#unsubscribe-festival-modal .cinema-card{animation:ufm-in 220ms cubic-bezier(.2,.7,.2,1) both}</style>
        <div
            class="cinema-card w-full max-h-[90vh] overflow-y-auto px-14 sm:px-20 py-20 sm:py-24 relative text-center"
            style="border-radius: 0.75rem;"
            onclick="event.stopPropagation()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="unsubscribe-festival-modal-title"
        >
            {{-- Centered column: icon → title → description. Mirrors the
                 delete-production-modal layout so the two destructive
                 modals feel like siblings, but with a neutral icon
                 since this one is reversible. --}}
            <div class="flex flex-col items-center text-center">
                <div class="shrink-0 w-11 h-11 rounded-full bg-[var(--bg-secondary)] flex items-center justify-center mb-5">
                    <svg class="w-5 h-5 text-[var(--text-muted)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h2 id="unsubscribe-festival-modal-title" class="text-lg font-semibold tracking-tight text-[var(--text-primary)]">
                    ¿Desuscribirte?
                </h2>
                <p class="mt-3 text-sm text-[var(--text-muted)] leading-relaxed max-w-[18rem] mx-auto">
                    Vas a dejar de recibir avisos de
                    <span class="text-[var(--text-secondary)] font-medium">{{ $festivalName ?? 'este festival' }}</span>.
                    Podés volver a suscribirte cuando quieras.
                </p>
            </div>

            {{-- Actions on a centered row, same balanced-pair layout
                 as the delete modal — both buttons size to content so
                 they never overflow narrow modals, with a generous
                 gap so the description reads as a separate column
                 rather than crammed against the controls. --}}
            <div class="flex items-center justify-center gap-4 mt-44 sm:mt-56">
                <button
                    type="button"
                    onclick="livewireFire('unsubscribe-festival-modal','closeUnsubscribeModal')"
                    class="cinema-btn-outline px-6 py-2.5 text-sm"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    onclick="livewireFire('unsubscribe-festival-modal','confirmUnsubscribe')"
                    wire:loading.attr="disabled"
                    wire:target="confirmUnsubscribe"
                    @disabled($isProcessing)
                    class="cinema-btn px-6 py-2.5 text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span wire:loading.remove wire:target="confirmUnsubscribe">Sí, desuscribirme</span>
                    <span wire:loading wire:target="confirmUnsubscribe" class="inline-flex items-center justify-center gap-2">
                        <svg class="animate-spin h-3.5 w-3.5 inline-block align-middle shrink-0" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="align-middle">Desuscribiendo…</span>
                    </span>
                </button>
            </div>
        </div>
    </dialog>
</div>

{{-- Same hook the delete and subscribe modals use: read the latest
     snapshot of `unsubscribeModalOpen` after every Livewire update
     and call showModal()/close() on the native <dialog>. Property
     name is intentionally `unsubscribeModalOpen` so the hook only
     acts on this component and doesn't fight with other Livewire
     state.

     ESC closes the native <dialog> automatically; the close listener
     below dispatches `closeUnsubscribeModal` onto this component's
     own root (closest [wire\\:id]) — that's where Livewire v4's
     `listen2()` attaches `$listeners` entries. --}}
<script>
    document.addEventListener('livewire:init', () => {
        console.log('[ufm-script] livewire:init received — modal script is live');

        // ─── BACKDROP-CLICK HANDLER ────────────────────────────────────────
        // Bound once on the <dialog>. Only fires on backdrop clicks
        // (target === dialog) and swallows the very first such click after
        // showModal() — that's the synthetic isTrusted=true click some
        // browsers (Safari, sometimes Firefox) emit when showModal() runs.
        // `_dialogOpening` is a belt-and-braces guard that stays true for
        // 250ms after open, so even a late-arriving synthetic click is
        // ignored while the modal is settling.
        //
        // We do NOT register the `close` listener until the opening
        // transition is over. Earlier versions attached it eagerly, and that
        // was the second root cause of the "open and immediately close"
        // bug: the close listener dispatched `closeUnsubscribeModal`
        // server-side, which flipped state to false, which the next morph
        // cycle rendered as `dialog.close()` — racing with the still-
        // settling modal and yanking it shut before the user could see it.
        // The 250ms delay is harmless (ESC fires well after that) and lets
        // us avoid the race entirely.
        //
        // `<dialog wire:ignore.self>` also keeps morphdom from touching the
        // dialog element itself between morph.updated firings, so its
        // `open` state, our flags, and our listener all survive across
        // morph cycles.

        const DIALOG_ID = 'unsubscribe-festival-modal';
        const COMPONENT_NAME = 'unsubscribe-festival-modal';

        Livewire.hook('morph.updated', ({ el, component }) => {
            const state = component.snapshot?.data?.unsubscribeModalOpen;
            if (state === undefined) return;
            const dialog = document.getElementById(DIALOG_ID);
            if (!dialog) { console.warn('[ufm] dialog missing'); return; }

            console.log('[ufm] morph.updated state=' + state + ' dialog.open=' + dialog.open + ' _dialogOpening=' + !!dialog._dialogOpening);

            if (!dialog._backdropClickBound) {
                dialog.addEventListener('click', (e) => {
                    console.log('[ufm] dialog click target=' + (e.target === dialog ? 'dialog(backdrop)' : 'inner') + ' suppress=' + !!dialog._suppressNextBackdropClick + ' opening=' + !!dialog._dialogOpening);
                    if (e.target !== dialog) return;
                    if (dialog._suppressNextBackdropClick) {
                        dialog._suppressNextBackdropClick = false;
                        return;
                    }
                    if (dialog._dialogOpening) return;
                    livewireFire(COMPONENT_NAME, 'closeUnsubscribeModal');
                });
                dialog._backdropClickBound = true;
            }

            if (state === true) {
                if (!dialog.open) {
                    dialog._dialogOpening = true;
                    dialog._suppressNextBackdropClick = true;
                    console.log('[ufm] showModal()');
                    dialog.showModal();
                    setTimeout(() => {
                        dialog._dialogOpening = false;
                        if (!dialog._closeListenerBound) {
                            document.addEventListener('close', (e) => {
                                console.log('[ufm] close event target.id=' + (e.target && e.target.id) + ' opening=' + !!e.target._dialogOpening);
                                if (!e.target || e.target.id !== DIALOG_ID) return;
                                const root = e.target.closest('[wire\\:id]');
                                if (root && !e.target._dialogOpening) {
                                    root.dispatchEvent(new CustomEvent('closeUnsubscribeModal', { bubbles: true }));
                                }
                            }, true);
                            dialog._closeListenerBound = true;
                        }
                    }, 250);
                }
            } else {
                if (dialog._dialogOpening) return;
                if (dialog.open) { console.log('[ufm] dialog.close() (state=false)'); dialog.close(); }
            }
        });
    });
</script>
