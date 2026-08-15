<div>
    @if($success)
        {{-- Standalone toast-style success state. We close the modal on
             success but keep the toast visible until the user dismisses it.
             Lives outside the backdrop so it doesn't get caught by the
             backdrop click handler. --}}
        <div class="fixed bottom-6 right-6 z-[60] cinema-card p-5 max-w-sm flex items-start gap-3 border-[var(--success-border)]"
             role="status"
             aria-live="polite">
            <svg class="w-5 h-5 text-[var(--success)] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-[var(--text-primary)]">¡Listo!</p>
                <p class="mt-1 text-xs text-[var(--text-muted)]">
                    Te suscribiste a <span class="font-medium text-[var(--text-secondary)]">{{ $festivalName }}</span>. Te enviamos un email de confirmación.
                </p>
            </div>
            <button
                type="button"
                wire:click="dismissSuccess"
                class="text-[var(--text-faintest)] hover:text-[var(--text-secondary)] transition-colors shrink-0"
                aria-label="Cerrar"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    @if($isOpen)
        {{-- Backdrop. Stopping propagation on the inner card means clicking
             the backdrop (outside the card) calls close() while clicks
             inside the card don't bubble through to it. Livewire v3
             supports @click.stop natively. --}}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            wire:click.self="close"
        >
            <div
                class="cinema-card w-full max-w-lg max-h-[90vh] overflow-y-auto p-7 relative"
                wire:click.stop
                role="dialog"
                aria-modal="true"
                aria-labelledby="subscribe-modal-title"
            >
                {{-- Close X --}}
                <button
                    type="button"
                    wire:click="close"
                    class="absolute top-4 right-4 text-[var(--text-faintest)] hover:text-[var(--text-secondary)] transition-colors"
                    aria-label="Cerrar"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                @if($isLoading && !$festivalApiId)
                    {{-- Initial fetch state — no data yet. Just spinner. --}}
                    <div class="flex flex-col items-center justify-center py-12 gap-3">
                        <svg class="animate-spin h-6 w-6 text-[var(--accent)]" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-sm text-[var(--text-muted)]">Cargando información del festival…</p>
                    </div>
                @elseif($error)
                    {{-- Error state — festival couldn't be loaded. --}}
                    <div class="text-center py-8">
                        <svg class="mx-auto h-10 w-10 text-[var(--danger)]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                        </svg>
                        <h3 id="subscribe-modal-title" class="mt-4 text-base font-medium text-[var(--text-primary)]">
                            No pudimos cargar el festival
                        </h3>
                        <p class="mt-2 text-sm text-[var(--text-muted)]">{{ $error }}</p>
                        <button
                            type="button"
                            wire:click="close"
                            class="mt-6 cinema-btn-outline px-4 py-2 text-sm"
                        >
                            Cerrar
                        </button>
                    </div>
                @else
                    {{-- Data loaded — show the preview + confirm form. --}}
                    <div class="mb-6">
                        <div class="text-[10px] text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-2">
                            Suscribirme a
                        </div>
                        <h2 id="subscribe-modal-title" class="text-2xl font-semibold tracking-tight text-[var(--text-primary)]">
                            {{ $festivalName }}
                        </h2>
                        @if($city || $country)
                            <div class="flex items-center gap-1.5 mt-2 text-sm text-[var(--text-muted)]">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span>{{ trim(implode(', ', array_filter([$city, $country]))) }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Preview grid — only shows fields we actually have. --}}
                    <dl class="grid grid-cols-2 gap-4 mb-6 p-5 rounded-lg bg-[var(--bg-secondary)] border border-[var(--border-color)]">
                        @if($primaryCategory)
                            <div>
                                <dt class="text-[10px] text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-1">Categoría</dt>
                                <dd class="text-sm text-[var(--text-primary)]">{{ ucfirst(str_replace('_', ' ', $primaryCategory)) }}</dd>
                            </div>
                        @endif
                        @if($deadline)
                            <div>
                                <dt class="text-[10px] text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-1">Deadline</dt>
                                <dd class="text-sm font-medium text-[var(--danger)] tabular-nums">{{ $deadline }}</dd>
                            </div>
                        @endif
                        @if($openingDate)
                            <div>
                                <dt class="text-[10px] text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-1">Apertura</dt>
                                <dd class="text-sm font-medium text-[var(--success)] tabular-nums">{{ $openingDate }}</dd>
                            </div>
                        @endif
                        @if($regularFee)
                            <div>
                                <dt class="text-[10px] text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-1">Submission fee</dt>
                                <dd class="text-sm font-medium text-[var(--text-primary)] tabular-nums">{{ $regularFee }}</dd>
                            </div>
                        @endif
                    </dl>

                    {{-- External links (only shown if present). --}}
                    @if($submissionUrl || $website)
                        <div class="flex flex-wrap items-center gap-3 mb-6 text-xs">
                            @if($submissionUrl)
                                <a href="{{ $submissionUrl }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1.5 text-[var(--accent)] hover:text-[var(--accent-hover)] transition-colors">
                                    Sitio del festival
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                    </svg>
                                </a>
                            @endif
                            @if($website)
                                <a href="{{ $website }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1.5 text-[var(--accent)] hover:text-[var(--accent-hover)] transition-colors">
                                    Web
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    @endif

                    {{-- Notification preference radio group. --}}
                    <fieldset class="mb-6">
                        <legend class="text-xs font-semibold tracking-wide uppercase text-[var(--text-muted)] mb-3">
                            ¿Cuándo querés que te avisemos?
                        </legend>
                        <div class="space-y-2">
                            <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--border-color)] cursor-pointer hover:border-[var(--border-hover)] transition-colors has-[:checked]:border-[var(--accent)] has-[:checked]:bg-[var(--accent-soft)]">
                                <input type="radio" wire:model="notificationType" value="both" class="mt-0.5 accent-[var(--accent)]">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-[var(--text-primary)]">Cuando abra y antes del deadline</div>
                                    <div class="text-xs text-[var(--text-muted)] mt-0.5">Recomendado · 2 emails por festival</div>
                                </div>
                            </label>
                            <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--border-color)] cursor-pointer hover:border-[var(--border-hover)] transition-colors has-[:checked]:border-[var(--accent)] has-[:checked]:bg-[var(--accent-soft)]">
                                <input type="radio" wire:model="notificationType" value="opening" class="mt-0.5 accent-[var(--accent)]">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-[var(--text-primary)]">Solo cuando abra la convocatoria</div>
                                    <div class="text-xs text-[var(--text-muted)] mt-0.5">1 email</div>
                                </div>
                            </label>
                            <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--border-color)] cursor-pointer hover:border-[var(--border-hover)] transition-colors has-[:checked]:border-[var(--accent)] has-[:checked]:bg-[var(--accent-soft)]">
                                <input type="radio" wire:model="notificationType" value="deadline" class="mt-0.5 accent-[var(--accent)]">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-[var(--text-primary)]">Solo antes del deadline</div>
                                    <div class="text-xs text-[var(--text-muted)] mt-0.5">1 email</div>
                                </div>
                            </label>
                        </div>
                    </fieldset>

                    {{-- Inline error after a failed confirm(). --}}
                    @if($error)
                        <div class="mb-4 p-3 rounded-lg border border-[var(--danger-border)] bg-[var(--danger-soft)] flex items-start gap-2">
                            <svg class="w-4 h-4 text-[var(--danger)] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                            </svg>
                            <p class="text-xs text-[var(--danger)]">{{ $error }}</p>
                        </div>
                    @endif

                    {{-- Confirm / cancel. --}}
                    <div class="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            wire:click="close"
                            class="cinema-btn-outline px-4 py-2 text-sm"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            wire:click="confirm"
                            wire:loading.attr="disabled"
                            wire:target="confirm"
                            class="cinema-btn px-5 py-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span wire:loading.remove wire:target="confirm">Confirmar suscripción</span>
                            <span wire:loading wire:target="confirm" class="inline-flex items-center gap-2">
                                <svg class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Procesando…
                            </span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>