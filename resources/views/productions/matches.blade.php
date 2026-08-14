<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Matches para {{ $production->title }} - Consultfest</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <script>
        (function () {
            const stored = localStorage.getItem('consultfest-theme');
            const theme = stored || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
            document.body.setAttribute('data-theme', theme);
            if (theme === 'dark') {
                document.body.classList.add('dark');
            }
        })();
    </script>
    @fonts
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body class="min-h-screen antialiased flex flex-col" data-theme="dark">
    <nav class="border-b border-[var(--border-color)] bg-[var(--bg-primary)]/80 backdrop-blur-xl sticky top-0 z-50 transition-colors">
        <div class="max-w-7xl mx-auto px-8 py-5">
            <div class="flex justify-between items-center">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <div class="w-7 h-7 bg-[var(--accent)] rounded-md flex items-center justify-center">
                        <svg class="w-4 h-4 text-[var(--accent-text)]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                        </svg>
                    </div>
                    <span class="text-base font-semibold tracking-tight">Consultfest</span>
                </a>
                <div class="flex items-center gap-3">
                    <a href="{{ route('productions.show', $production) }}" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">← Producción</a>
                    <a href="{{ route('festivals.index') }}" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Festivales</a>
                    <button type="button" class="theme-toggle" aria-label="Cambiar tema">
                        <svg class="icon-sun" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                        </svg>
                        <svg class="icon-moon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-8 pt-12 pb-24 flex-1 w-full">
        <div class="mb-10">
            <h1 class="text-3xl md:text-4xl font-semibold tracking-tight mb-2">
                Festivales sugeridos para <span class="font-display italic text-[var(--accent)]">{{ $production->title }}</span>
            </h1>
            <p class="text-sm text-[var(--text-muted)]">
                {{ $totalMatches }} {{ $totalMatches === 1 ? 'festival matchea' : 'festivales matchean' }} con tu producción.
                Solo recibirás notificaciones de los que te suscribas.
            </p>
        </div>

        @if($festivals->isEmpty())
            <div class="cinema-card p-12 text-center">
                <svg class="w-12 h-12 mx-auto mb-4 text-[var(--text-faintest)]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <h2 class="text-lg font-medium mb-2">No hay matches por ahora</h2>
                <p class="text-sm text-[var(--text-muted)] mb-6 max-w-md mx-auto">
                    Esto puede pasar si tu producción tiene pocos metadatos, o si ningún festival abierto coincide con tu categoría, país y género.
                    Edita la producción para refinar los filtros.
                </p>
                <a href="{{ route('productions.edit', $production) }}" class="cinema-btn-outline inline-block px-6 py-2.5 text-sm">
                    Editar producción
                </a>
            </div>
        @else
            <div class="space-y-3">
                @foreach($festivals as $festival)
                    <article class="cinema-card p-5">
                        <div class="flex items-start justify-between gap-4 mb-3">
                            <div class="flex-1 min-w-0">
                                <h2 class="text-base font-semibold tracking-tight mb-1">{{ $festival->name }}</h2>
                                <div class="flex items-center gap-2 text-xs text-[var(--text-muted)] flex-wrap">
                                    @if($festival->country)
                                        <span>{{ $festival->country }}</span>
                                    @endif
                                    @if($festival->primaryCategory)
                                        <span class="text-[var(--text-faintest)]">·</span>
                                        <span>{{ ucfirst(str_replace('_', ' ', $festival->primaryCategory)) }}</span>
                                    @endif
                                    @if($festival->compositeScore !== null)
                                        <span class="text-[var(--text-faintest)]">·</span>
                                        <span class="tabular-nums">Score {{ $festival->compositeScore }}</span>
                                    @endif
                                </div>
                            </div>
                            @if($festival->compositeScore !== null)
                                <div class="text-2xl font-semibold text-[var(--accent)] tabular-nums">{{ $festival->compositeScore }}</div>
                            @endif
                        </div>

                        {{-- FestivalAPI returns null deadline_regular / event_start_date /
                             regular_fee for many festivals whose dates aren't published yet
                             (verified 2026-08-10: Sitges, Bilbao Fantasy, Terrassa Horror all
                             come back with all three fields null from both list and detail
                             endpoints). When every column would be "—" we collapse the grid
                             into a single contextual message instead of three dead dashes. --}}
                        @php
                            $hasAnyDate = $festival->eventStartDate !== null
                                || $festival->deadline !== null
                                || $festival->regularFee !== null;
                        @endphp
                        @if($hasAnyDate)
                            <div class="grid grid-cols-3 gap-3 mb-3 text-xs">
                                <div>
                                    <div class="text-[var(--text-faint)] mb-0.5">Apertura</div>
                                    <div class="font-medium tabular-nums text-[var(--success)]">
                                        {{ $festival->eventStartDate?->format('M d, Y') ?? '—' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[var(--text-faint)] mb-0.5">Deadline</div>
                                    <div class="font-medium tabular-nums
                                        @if($festival->deadline && $festival->deadline->isPast()) text-[var(--text-faint)]
                                        @elseif($festival->deadline && $festival->deadline->diffInDays(now()) < 14) text-[var(--danger)]
                                        @else text-[var(--text-primary)] @endif">
                                        {{ $festival->deadline?->format('M d, Y') ?? '—' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[var(--text-faint)] mb-0.5">Fee</div>
                                    <div class="font-medium tabular-nums text-[var(--text-primary)]">
                                        @if($festival->regularFee !== null) ${{ number_format($festival->regularFee, 2) }} @else — @endif
                                    </div>
                                </div>
                            </div>
                        @else
                            <p class="text-xs text-[var(--text-faint)] mb-3">
                                Fechas no publicadas · visitá el sitio del festival para confirmar.
                            </p>
                        @endif

                        {{-- Prefer genres; fall back to categories (FestivalAPI often
                             returns genres=[] for festivals whose only classification
                             is at the category level, e.g. Sitges is horror/fantasy/sci_fi
                             but reports no genres). Humanize the slugs ("sci_fi" → "Sci fi"). --}}
                        @php
                            $chips = count($festival->genres) > 0
                                ? $festival->genres
                                : $festival->categories;
                            $chips = array_slice($chips, 0, 5);
                            $humanize = fn (string $s) => ucfirst(str_replace('_', ' ', $s));
                        @endphp
                        @if(count($chips) > 0)
                            <div class="flex flex-wrap gap-1.5 mb-4">
                                @foreach($chips as $chip)
                                    <span class="px-2 py-0.5 text-xs bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-full text-[var(--text-secondary)]">
                                        {{ $humanize($chip) }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex items-center gap-3 pt-3 border-t border-[var(--border-color)]">
                            <a href="{{ route('festivals.redirect', ['apiId' => $festival->apiId]) }}" target="_blank" rel="noopener" class="text-xs text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">
                                Ver sitio del festival →
                            </a>
                            <form action="{{ route('festivals.subscribe') }}" method="POST" class="ml-auto">
                                @csrf
                                <input type="hidden" name="festival_api_id" value="{{ $festival->apiId }}">
                                <input type="hidden" name="notification_type" value="both">
                                <button type="submit" class="cinema-btn px-4 py-2 text-xs">
                                    + Suscribirme
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Pagination — same list call serves all pages (1h cache), so
                 flipping pages costs 0 FestivalAPI credits. Only the top
                 5 matches get detail-enriched; the rest show with list-endpoint
                 data only (chips via categories fallback still work). --}}
            @if($totalPages > 1)
                <div class="mt-6 pt-6 border-t border-[var(--border-color)] flex items-center justify-between">
                    <div class="text-xs text-[var(--text-faintest)] tabular-nums">
                        Página {{ $currentPage }} de {{ $totalPages }}
                    </div>
                    <div class="flex items-center gap-2">
                        @if($currentPage > 1)
                            <a href="{{ route('productions.matches', array_merge(['production' => $production, 'page' => $currentPage - 1])) }}"
                                class="cinema-btn-outline px-3 py-1.5 text-xs">
                                ← Anterior
                            </a>
                        @else
                            <button type="button" disabled
                                class="cinema-btn-outline px-3 py-1.5 text-xs disabled:opacity-40 disabled:cursor-not-allowed">
                                ← Anterior
                            </button>
                        @endif
                        @if($currentPage < $totalPages)
                            <a href="{{ route('productions.matches', array_merge(['production' => $production, 'page' => $currentPage + 1])) }}"
                                class="cinema-btn-outline px-3 py-1.5 text-xs">
                                Siguiente →
                            </a>
                        @else
                            <button type="button" disabled
                                class="cinema-btn-outline px-3 py-1.5 text-xs disabled:opacity-40 disabled:cursor-not-allowed">
                                Siguiente →
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </main>

    <footer class="border-t border-[var(--border-color)] py-12">
        <div class="max-w-7xl mx-auto px-8 flex justify-between items-center">
            <p class="text-sm text-[var(--text-faintest)]">Consultfest</p>
            <p class="text-xs text-[var(--text-faint)]">Para cineastas independientes</p>
        </div>
    </footer>
</body>
</html>