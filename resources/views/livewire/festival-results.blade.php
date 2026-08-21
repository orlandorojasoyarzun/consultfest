<div>
    @if($isApiNotConfigured)
        <div class="text-center py-12 cinema-card">
            <svg class="mx-auto h-10 w-10 text-[var(--text-muted)]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
            </svg>
            <h3 class="mt-4 text-base font-medium text-[var(--text-secondary)]">Búsqueda de festivales no disponible</h3>
            <p class="mt-2 text-sm text-[var(--text-faintest)]">La integración con FestivalAPI no está configurada en este deploy.</p>
        </div>
    @elseif($isRateLimited)
        <div class="text-center py-12 cinema-card">
            <svg class="mx-auto h-10 w-10 text-[var(--warning)]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
            <h3 class="mt-4 text-base font-medium text-[var(--text-secondary)]">Demasiadas búsquedas</h3>
            <p class="mt-2 text-sm text-[var(--text-faintest)]">Esperá un minuto antes de volver a buscar para no agotar créditos de FestivalAPI.</p>
        </div>
    @elseif($isLoading)
        <div class="flex items-center justify-center py-20">
            <div class="flex items-center gap-3 text-[var(--text-muted)]">
                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sm">Cargando...</span>
            </div>
        </div>
    @elseif($totalCount === 0)
        <div class="text-center py-20 cinema-card">
            <svg class="mx-auto h-10 w-10 text-[var(--text-faintest)]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
            </svg>
            <h3 class="mt-6 text-base font-medium text-[var(--text-secondary)]">No hay festivales</h3>
            <p class="mt-2 text-sm text-[var(--text-faintest)]">Ajustá los filtros o el rango de fechas.</p>
        </div>
    @else
        <div class="mb-4 text-sm text-[var(--text-faintest)] tabular-nums">
            {{ $totalCount }} {{ $totalCount === 1 ? 'festival' : 'festivales' }}
            @if($totalCount >= 100)
                <span class="ml-2 text-xs">(primeros 100 resultados de FestivalAPI)</span>
            @endif
        </div>
        <div class="space-y-2">
            @foreach($festivals as $festival)
                <div
                    class="cinema-card p-6 hover:border-[var(--border-hover)] group cinema-fade-up" style="animation-delay: {{ min($loop->index * 80, 800) }}ms"
                >
                    <div class="flex items-start justify-between gap-6">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline gap-3 mb-2">
                                <h3 class="text-lg font-medium truncate">
                                    <a
                                        href="{{ route('festivals.redirect', ['apiId' => $festival->apiId]) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-[var(--text-primary)] hover:text-[var(--accent)] transition-colors cursor-pointer"
                                    >
                                        {{ $festival->name }}
                                    </a>
                                </h3>
                                @if($festival->compositeScore !== null)
                                    <span class="text-xs font-medium text-[var(--accent)] tabular-nums shrink-0">
                                        {{ number_format($festival->compositeScore, 1) }}
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center gap-3 text-sm text-[var(--text-muted)] flex-wrap">
                                @if($festival->country)
                                    <span class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        {{ $festival->city ? "{$festival->city}, " : '' }}{{ $festival->country }}
                                    </span>
                                @endif
                                @if($festival->primaryCategory)
                                    <span class="text-[var(--text-faintest)]">·</span>
                                    <span>{{ ucfirst(str_replace('_', ' ', $festival->primaryCategory)) }}</span>
                                @endif
                            </div>

                            @if(count($festival->genres) > 0)
                                <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                                    @foreach(array_slice($festival->genres, 0, 4) as $genre)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-[var(--bg-elevated)] text-[var(--text-muted)] border border-[var(--border-color)]">
                                            {{ $genre }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-col items-end gap-2 shrink-0">
                            @if($festival->eventStartDate)
                                <div class="text-right">
                                    <div class="text-[10px] text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-1">Apertura</div>
                                    <span class="text-sm font-medium text-[var(--success)] tabular-nums">
                                        {{ $festival->eventStartDate->format('M d, Y') }}
                                    </span>
                                </div>
                            @endif
                            @if($festival->deadline)
                                <div class="text-right">
                                    <div class="text-[10px] text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-1">Deadline</div>
                                    <span class="text-sm font-medium text-[var(--danger)] tabular-nums">
                                        {{ $festival->deadline->format('M d, Y') }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($festival->regularFee !== null || $festival->deadline !== null)
                        <div class="flex items-center justify-between gap-4 mt-4 pt-4 border-t border-[var(--border-color)]">
                            <div class="flex items-center gap-4">
                                @if($festival->regularFee !== null)
                                    <span class="text-xs text-[var(--text-muted)]">
                                        Fee <span class="text-[var(--text-secondary)] font-medium">${{ number_format($festival->regularFee, 2) }}</span>
                                    </span>
                                @endif
                                @if($festival->deadline !== null)
                                    @if($festival->isAcceptingSubmissions())
                                        <span class="flex items-center gap-1.5 text-xs text-[var(--success)]">
                                            <span class="w-1.5 h-1.5 bg-[var(--success)] rounded-full"></span>
                                            Abierto
                                        </span>
                                    @else
                                        <span class="flex items-center gap-1.5 text-xs text-[var(--text-muted)]">
                                            <span class="w-1.5 h-1.5 bg-[var(--text-faintest)] rounded-full"></span>
                                            Cerrado
                                        </span>
                                    @endif
                                @endif
                            </div>

                            <button
                                type="button"
                                wire:click="openSubscribeModal({{ $festival->apiId }})"
                                class="cinema-btn px-3 py-1.5 text-xs shrink-0"
                            >
                                + Suscribirme
                            </button>
                        </div>
                    @else
                        <div class="flex items-center justify-end mt-4 pt-4 border-t border-[var(--border-color)]">
                            <button
                                type="button"
                                wire:click="openSubscribeModal({{ $festival->apiId }})"
                                class="cinema-btn px-3 py-1.5 text-xs shrink-0"
                            >
                                + Suscribirme
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6 pt-6 border-t border-[var(--border-color)] flex items-center justify-between">
            <div class="text-xs text-[var(--text-faintest)] tabular-nums">
                Página {{ $currentPage }} de {{ $totalPages }}
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="previousPage"
                    @disabled($currentPage <= 1)
                    class="cinema-btn-outline px-3 py-1.5 text-xs disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    ← Anterior
                </button>
                <button
                    type="button"
                    wire:click="nextPage"
                    @disabled($currentPage >= $totalPages)
                    class="cinema-btn-outline px-3 py-1.5 text-xs disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    Siguiente →
                </button>
            </div>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────────
         Subscribe modal — inlined into FestivalResults so the button's
         wire:click toggles state on the same component. No cross-component
         dispatch, no listener registration race conditions. State lives in
         FestivalResults::$subscribeModalOpen + the subscribe* properties.
         ───────────────────────────────────────────────────────────────── --}}

    {{-- Toast-style success state. Lives outside the backdrop so backdrop
         clicks (which call closeSubscribeModal) don't dismiss it. --}}
    @if($subscribeSuccess)
        <div class="fixed bottom-6 right-6 z-[60] cinema-card p-5 max-w-sm flex items-start gap-3 border-[var(--success-border)]"
             role="status"
             aria-live="polite">
            <svg class="w-5 h-5 text-[var(--success)] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-[var(--text-primary)]">¡Listo!</p>
                <p class="mt-1 text-xs text-[var(--text-muted)]">
                    Te suscribiste a <span class="font-medium text-[var(--text-secondary)]">{{ $subscribeFestivalName }}</span>. Te enviamos un email de confirmación.
                </p>
            </div>
            <button
                type="button"
                onclick="livewireFire('festival-results','dismissSubscribeSuccess')"
                class="text-[var(--text-faintest)] hover:text-[var(--text-secondary)] transition-colors shrink-0"
                aria-label="Cerrar"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    {{-- The modal lives in the DOM permanently. Visibility is toggled by
         the Livewire.hook attached at the bottom of this file: on every
         `morph.updated` it reads subscribeModalOpen from the snapshot and
         calls showModal()/close() on the native <dialog>. Using the
         snapshot as the source of truth keeps the modal in sync with
         actual server state — earlier approaches that flipped a `hidden`
         class from a click handler were undone by morphdom reapplying
         `hidden` on the next request. --}}
    <dialog
        wire:ignore.self
        id="subscribe-modal"
        class="bg-transparent p-0"
        style="margin: auto; max-width: 32rem; width: calc(100vw - 2rem); max-height: 90vh; padding: 0; border: 0; outline: 0; box-shadow: none; background: transparent; color-scheme: dark; border-radius: 0.75rem; overflow: hidden;"
    >
        <style>[open]#subscribe-modal{background:transparent;border:0;box-shadow:none;outline:0;padding:0;color-scheme:dark}[open]#subscribe-modal::backdrop{background-color:rgba(0,0,0,.6);backdrop-filter:blur(4px)}</style>
        <div
            class="cinema-card w-full max-h-[90vh] overflow-y-auto p-7 relative"
            style="border-radius: 0.75rem;"
            onclick="event.stopPropagation()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="subscribe-modal-title"
        >
                @if($subscribeLoading && !$subscribeFestivalApiId)
                    <div class="flex flex-col items-center justify-center py-12 gap-3">
                        <svg class="animate-spin h-6 w-6 text-[var(--accent)]" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-sm text-[var(--text-muted)]">Cargando información del festival…</p>
                    </div>
                @elseif($subscribeError && !$subscribeFestivalApiId)
                    <div class="text-center py-8">
                        <svg class="mx-auto h-10 w-10 text-[var(--danger)]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                        </svg>
                        <h3 id="subscribe-modal-title" class="mt-4 text-base font-medium text-[var(--text-primary)]">
                            No pudimos cargar el festival
                        </h3>
                        <p class="mt-2 text-sm text-[var(--text-muted)]">{{ $subscribeError }}</p>
                        <button
                            type="button"
                            onclick="livewireFire('festival-results','closeSubscribeModal')"
                            class="mt-6 cinema-btn-outline px-4 py-2 text-sm"
                        >
                            Cerrar
                        </button>
                    </div>
                @else
                    <div class="mb-6">
                        <div class="text-[10px] text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-2">
                            Suscribirme a
                        </div>
                        <h2 id="subscribe-modal-title" class="text-2xl font-semibold tracking-tight text-[var(--text-primary)]">
                            {{ $subscribeFestivalName }}
                        </h2>
                        @if($subscribeCity || $subscribeCountry)
                            <div class="flex items-center gap-1.5 mt-2 text-sm text-[var(--text-muted)]">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span>{{ trim(implode(', ', array_filter([$subscribeCity, $subscribeCountry]))) }}</span>
                            </div>
                        @endif
                    </div>

                    @if($subscribePrimaryCategory || $subscribeDeadline || $subscribeOpeningDate || $subscribeRegularFee)
                        <dl class="grid grid-cols-2 gap-4 mb-6 p-5 rounded-lg bg-[var(--bg-secondary)] border border-[var(--border-color)]">
                            @if($subscribePrimaryCategory)
                                <div>
                                    <dt class="text-[10px] text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-1">Categoría</dt>
                                    <dd class="text-sm text-[var(--text-primary)]">{{ ucfirst(str_replace('_', ' ', $subscribePrimaryCategory)) }}</dd>
                                </div>
                            @endif
                            @if($subscribeDeadline)
                                <div>
                                    <dt class="text-[10px] text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-1">Deadline</dt>
                                    <dd class="text-sm font-medium text-[var(--danger)] tabular-nums">{{ $subscribeDeadline }}</dd>
                                </div>
                            @endif
                            @if($subscribeOpeningDate)
                                <div>
                                    <dt class="text-[10px] text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-1">Apertura</dt>
                                    <dd class="text-sm font-medium text-[var(--success)] tabular-nums">{{ $subscribeOpeningDate }}</dd>
                                </div>
                            @endif
                            @if($subscribeRegularFee)
                                <div>
                                    <dt class="text-[10px] text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-1">Submission fee</dt>
                                    <dd class="text-sm font-medium text-[var(--text-primary)] tabular-nums">{{ $subscribeRegularFee }}</dd>
                                </div>
                            @endif
                        </dl>
                    @endif

                    @php
                        // Deduplicate: many festivals expose submission_url and website
                        // pointing at the same page (e.g. festival's own site IS the
                        // submission form). Render one block per unique URL; if both
                        // URLs resolve to the same destination, we show a single
                        // "Sitio del festival" entry instead of two near-duplicates.
                        $subscribeLinks = collect([
                            $subscribeSubmissionUrl ? ['label' => 'Sitio del festival', 'url' => $subscribeSubmissionUrl] : null,
                            $subscribeWebsite && (!$subscribeSubmissionUrl || $subscribeWebsite !== $subscribeSubmissionUrl)
                                ? ['label' => 'Web', 'url' => $subscribeWebsite]
                                : null,
                        ])->filter()->values();
                    @endphp
                    @if($subscribeLinks->isNotEmpty())
                        <div class="space-y-2 mb-6">
                            @foreach($subscribeLinks as $link)
                                <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                                   class="flex items-center gap-3 px-4 py-3 rounded-lg border border-[var(--border-color)] hover:border-[var(--border-hover)] bg-[var(--bg-secondary)] transition-colors group">
                                    <svg class="w-4 h-4 text-[var(--text-muted)] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/>
                                    </svg>
                                    <span class="text-sm font-medium text-[var(--text-primary)] flex-1 min-w-0 truncate">{{ $link['label'] }}</span>
                                    <svg class="w-3.5 h-3.5 text-[var(--text-faintest)] group-hover:text-[var(--accent)] transition-colors shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                    </svg>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    {{-- Email confirmation block. Required so the user
                         doesn't subscribe to a typo'd address silently. --}}
                    <div class="mb-6 p-5 rounded-lg bg-[var(--bg-secondary)] border border-[var(--border-color)]">
                        @if($subscribeSubscriberLoggedIn && $subscriberEmail)
                            <p class="text-[10px] text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-1">
                                Te vamos a avisar a
                            </p>
                            <p class="text-sm font-medium text-[var(--text-primary)] break-all">
                                {{ $subscriberEmail }}
                            </p>
                            <label class="flex items-start gap-2 mt-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:model.live="subscribeEmailConfirmed"
                                    class="mt-0.5 accent-[var(--accent)]"
                                >
                                <span class="text-xs text-[var(--text-muted)]">
                                    Confirmo que este es mi email correcto
                                </span>
                            </label>
                        @else
                            <p class="text-sm text-[var(--text-muted)]">
                                Necesitás tener una cuenta para suscribirte.
                            </p>
                            <a
                                href="{{ route('auth.register') }}"
                                class="mt-3 inline-block cinema-btn px-4 py-2 text-sm"
                            >
                                Crear cuenta
                            </a>
                        @endif
                    </div>

                    <fieldset class="mb-6">
                        <legend class="text-xs font-semibold tracking-wide uppercase text-[var(--text-muted)] mb-3">
                            ¿Cuándo querés que te avisemos?
                        </legend>
                        <div class="space-y-2">
                            <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--border-color)] cursor-pointer hover:border-[var(--border-hover)] transition-colors has-[:checked]:border-[var(--accent)] has-[:checked]:bg-[var(--accent-soft)]">
                                <input type="radio" wire:model="subscribeNotificationType" value="both" class="mt-0.5 accent-[var(--accent)]">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-[var(--text-primary)]">Cuando abra y antes del deadline</div>
                                    <div class="text-xs text-[var(--text-muted)] mt-0.5">Recomendado · 2 emails por festival</div>
                                </div>
                            </label>
                            <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--border-color)] cursor-pointer hover:border-[var(--border-hover)] transition-colors has-[:checked]:border-[var(--accent)] has-[:checked]:bg-[var(--accent-soft)]">
                                <input type="radio" wire:model="subscribeNotificationType" value="opening" class="mt-0.5 accent-[var(--accent)]">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-[var(--text-primary)]">Solo cuando abra la convocatoria</div>
                                    <div class="text-xs text-[var(--text-muted)] mt-0.5">1 email</div>
                                </div>
                            </label>
                            <label class="flex items-start gap-3 p-3 rounded-lg border border-[var(--border-color)] cursor-pointer hover:border-[var(--border-hover)] transition-colors has-[:checked]:border-[var(--accent)] has-[:checked]:bg-[var(--accent-soft)]">
                                <input type="radio" wire:model="subscribeNotificationType" value="deadline" class="mt-0.5 accent-[var(--accent)]">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-[var(--text-primary)]">Solo antes del deadline</div>
                                    <div class="text-xs text-[var(--text-muted)] mt-0.5">1 email</div>
                                </div>
                            </label>
                        </div>
                    </fieldset>

                    @if($subscribeError)
                        <div class="mb-4 p-3 rounded-lg border border-[var(--danger-border)] bg-[var(--danger-soft)] flex items-start gap-2">
                            <svg class="w-4 h-4 text-[var(--danger)] shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                            </svg>
                            <p class="text-xs text-[var(--danger)]">{{ $subscribeError }}</p>
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            onclick="livewireFire('festival-results','closeSubscribeModal')"
                            class="cinema-btn-outline px-4 py-2 text-sm"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            onclick="livewireFire('festival-results','confirmSubscribe')"
                            wire:loading.attr="disabled"
                            wire:target="confirmSubscribe"
                            @disabled(!$subscribeSubscriberLoggedIn || !$subscribeEmailConfirmed)
                            class="cinema-btn px-5 py-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span wire:loading.remove wire:target="confirmSubscribe">Confirmar suscripción</span>
                            <span wire:loading wire:target="confirmSubscribe" class="inline-flex items-center justify-center gap-2">
                                <svg class="animate-spin h-3.5 w-3.5 inline-block align-middle shrink-0" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="align-middle">Procesando…</span>
                            </span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
</dialog>

{{-- The modal above uses the native `<dialog>` element so it pops up in
     the browser's top layer and is NOT constrained by any ancestor's
     `transform`/`filter`/`backdrop-filter`/etc. (which would create a
     containing block for fixed positioning and break `position: fixed;
     inset: 0` — that's what was happening: the modal was being rendered
     at `top: 3124` because an ancestor in the Livewire component chain
     had a transform-style utility). The dialog is shown via
     `showModal()` and hidden via `close()` from the morph.updated hook.

     Backdrop-click handler is bound once on the <dialog> (not inline)
     and uses a one-shot flag plus a 250 ms `_dialogOpening` window to
     refuse any close dispatch during the open transition. The native
     `close` listener is intentionally NOT registered here (and isn't
     needed — Cancelar and the confirm button both call
     `closeSubscribeModal` directly via `livewireFire`). This is the
     same race-free pattern the standalone subscribe / unsubscribe /
     delete modals use. --}}
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.hook('morph.updated', ({ el, component }) => {
            const state = component.snapshot?.data?.subscribeModalOpen;
            if (state === undefined) return;
            const modal = document.getElementById('subscribe-modal');
            if (!modal) return;

            if (!modal._backdropClickBound) {
                modal.addEventListener('click', (e) => {
                    if (e.target !== modal) return;
                    if (modal._suppressNextBackdropClick) {
                        modal._suppressNextBackdropClick = false;
                        return;
                    }
                    if (modal._dialogOpening) return;
                    livewireFire('festival-results', 'closeSubscribeModal');
                });
                modal._backdropClickBound = true;
            }

            if (state === true) {
                if (!modal.open) {
                    modal._dialogOpening = true;
                    modal._suppressNextBackdropClick = true;
                    modal.showModal();
                    setTimeout(() => { modal._dialogOpening = false; }, 250);
                }
            } else {
                if (modal._dialogOpening) return;
                if (modal.open) modal.close();
            }
        });
    });
</script>