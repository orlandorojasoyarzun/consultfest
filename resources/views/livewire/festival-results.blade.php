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
                {{-- Lazy enrichment: the card links to a server-side redirect
                     that triggers FestivalSearchService::details() (1 credit,
                     24h cached per apiId) and 302s to the real organizer URL.
                     Browsing the list itself stays at 1 credit (list call)
                     regardless of pagination — we only spend when the user
                     signals intent by clicking. --}}
                <a
                    href="{{ route('festivals.redirect', ['apiId' => $festival->apiId]) }}"
                    class="block cinema-card p-6 hover:border-[var(--border-hover)] group"
                >
                    <div class="flex items-start justify-between gap-6">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline gap-3 mb-2">
                                <h3 class="text-lg font-medium text-[var(--text-primary)] group-hover:text-[var(--accent)] transition-colors truncate">
                                    {{ $festival->name }}
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
                        <div class="flex items-center gap-4 mt-4 pt-4 border-t border-[var(--border-color)]">
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
                    @endif
                </a>
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
</div>