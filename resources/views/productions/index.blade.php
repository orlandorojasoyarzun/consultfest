<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis producciones - Consultfest</title>
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
                    <a href="{{ route('festivals.index') }}" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Festivales</a>
                    <a href="{{ route('productions.index') }}" class="text-sm text-[var(--accent)] font-medium">Producciones</a>
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
        <div class="flex items-baseline justify-between mb-10">
            <div>
                <h1 class="text-4xl md:text-5xl font-semibold tracking-tight mb-2">
                    <span class="font-display italic font-normal">Mis</span> producciones
                </h1>
                <p class="text-sm text-[var(--text-muted)]">
                    Inscribe tus cortometrajes y te sugeriremos festivales donde podrían calificar.
                </p>
            </div>
            <a href="{{ route('productions.create') }}" class="cinema-btn px-6 py-3 text-sm">
                + Nueva producción
            </a>
        </div>

        @if(session('production-flash'))
            <div class="cinema-card p-4 mb-6 border-[var(--success-border)]">
                <p class="text-sm text-[var(--success)]">{{ session('production-flash') }}</p>
            </div>
        @endif

        @if($productions->isEmpty())
            <div class="cinema-card p-12 text-center">
                <svg class="w-12 h-12 mx-auto mb-4 text-[var(--text-faintest)]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5l4.5 4.5m15 0l-4.5 4.5m4.5-4.5l-4.5-4.5M3.75 19.5l4.5-4.5m0 0l4.5 4.5M8.25 15l4.5-4.5"/>
                </svg>
                <h2 class="text-lg font-medium mb-2">Aún no tienes producciones</h2>
                <p class="text-sm text-[var(--text-muted)] mb-6">
                    Inscribe tu primer cortometraje para recibir sugerencias de festivales donde podría calificar.
                </p>
                <a href="{{ route('productions.create') }}" class="cinema-btn inline-block px-6 py-2.5 text-sm">
                    Crear producción
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                @foreach($productions as $production)
                    <article class="cinema-card p-6 cinema-fade-up" style="animation-delay: {{ min($loop->index * 60, 600) }}ms">
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div class="flex-1 min-w-0">
                                <h2 class="text-lg font-semibold tracking-tight mb-1 truncate">{{ $production->title }}</h2>
                                <div class="flex items-center gap-2 text-xs text-[var(--text-muted)]">
                                    @if($production->category)
                                        <span>{{ ucfirst(str_replace('_', ' ', $production->category)) }}</span>
                                    @endif
                                    @if($production->runtime_minutes)
                                        <span class="text-[var(--text-faintest)]">·</span>
                                        <span class="tabular-nums">{{ $production->runtime_minutes }} min</span>
                                    @endif
                                    @if($production->country)
                                        <span class="text-[var(--text-faintest)]">·</span>
                                        <span>{{ $production->country }}</span>
                                    @endif
                                </div>
                            </div>
                            <span class="px-2.5 py-1 text-xs font-medium rounded-full
                                @switch($production->status)
                                    @case('active') bg-[var(--success-soft)] text-[var(--success)] @break
                                    @case('archived') bg-[var(--bg-tertiary)] text-[var(--text-faint)] @break
                                    @default bg-[var(--bg-tertiary)] text-[var(--text-muted)]
                                @endswitch
                            ">
                                {{ ucfirst($production->status) }}
                            </span>
                        </div>

                        @if($production->genres && count($production->genres) > 0)
                            <div class="flex flex-wrap gap-1.5 mb-4">
                                @foreach(array_slice($production->genres, 0, 4) as $genre)
                                    <span class="px-2 py-0.5 text-xs bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-full text-[var(--text-secondary)]">
                                        {{ $genre }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        @if($production->synopsis)
                            <p class="text-sm text-[var(--text-secondary)] line-clamp-3 mb-4">{{ $production->synopsis }}</p>
                        @endif

                        <div class="flex items-center gap-2 pt-3 border-t border-[var(--border-color)]">
                            <a href="{{ route('productions.show', $production) }}" class="text-xs text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">
                                Ver detalles
                            </a>
                            <span class="text-[var(--text-faintest)]">·</span>
                            <a href="{{ route('productions.matches', $production) }}" class="text-xs text-[var(--accent)] hover:underline">
                                Ver matches →
                            </a>
                            <button
                                type="button"
                                onclick="Livewire.dispatch('openDeleteModal', { id: {{ $production->id }} })"
                                class="ml-auto text-xs text-[var(--text-muted)] hover:text-[var(--danger)] transition-colors"
                            >
                                Eliminar
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </main>

    <footer class="border-t border-[var(--border-color)] py-12">
        <div class="max-w-7xl mx-auto px-8 flex justify-between items-center">
            <p class="text-sm text-[var(--text-faintest)]">Consultfest</p>
            <p class="text-xs text-[var(--text-faint)]">Para cineastas independientes</p>
        </div>
    </footer>

    {{-- Delete-production confirmation modal — global to this page so
         every card's Eliminar button can dispatch into it. State lives
         on the DeleteProductionModal Livewire component; the buttons
         above just dispatch `openDeleteModal` via vanilla JS. --}}
    <livewire:delete-production-modal />

    @livewireScripts
</body>
</html>