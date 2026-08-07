<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $production->title }} - Consultfest</title>
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
                    <a href="{{ route('productions.index') }}" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">← Producciones</a>
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

    <main class="max-w-4xl mx-auto px-8 pt-12 pb-24 flex-1 w-full">
        @if(session('production-flash'))
            <div class="cinema-card p-4 mb-6 border-[var(--success-border)]">
                <p class="text-sm text-[var(--success)]">{{ session('production-flash') }}</p>
            </div>
        @endif

        <div class="flex items-baseline justify-between mb-8 gap-4">
            <div>
                <span class="px-2.5 py-1 text-xs font-medium rounded-full
                    @switch($production->status)
                        @case('active') bg-[var(--success-soft)] text-[var(--success)] @break
                        @case('archived') bg-[var(--bg-tertiary)] text-[var(--text-faint)] @break
                        @default bg-[var(--bg-tertiary)] text-[var(--text-muted)]
                    @endswitch
                ">{{ ucfirst($production->status) }}</span>
                <h1 class="text-3xl md:text-4xl font-semibold tracking-tight mt-3">{{ $production->title }}</h1>
            </div>
            <a href="{{ route('productions.matches', $production) }}" class="cinema-btn px-5 py-2.5 text-sm whitespace-nowrap">
                Ver matches →
            </a>
        </div>

        @if($production->synopsis)
            <div class="cinema-card p-6 mb-6">
                <div class="text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-3">Sinopsis</div>
                <p class="text-sm text-[var(--text-secondary)] leading-relaxed">{{ $production->synopsis }}</p>
            </div>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            @if($production->category)
                <div class="cinema-card p-5">
                    <div class="text-xs text-[var(--text-muted)] uppercase tracking-wider mb-1">Categoría</div>
                    <div class="text-sm font-medium">{{ ucfirst(str_replace('_', ' ', $production->category)) }}</div>
                </div>
            @endif
            @if($production->runtime_minutes)
                <div class="cinema-card p-5">
                    <div class="text-xs text-[var(--text-muted)] uppercase tracking-wider mb-1">Duración</div>
                    <div class="text-sm font-medium tabular-nums">{{ $production->runtime_minutes }} min</div>
                </div>
            @endif
            @if($production->format)
                <div class="cinema-card p-5">
                    <div class="text-xs text-[var(--text-muted)] uppercase tracking-wider mb-1">Formato</div>
                    <div class="text-sm font-medium">{{ ucfirst($production->format) }}</div>
                </div>
            @endif
            @if($production->country)
                <div class="cinema-card p-5">
                    <div class="text-xs text-[var(--text-muted)] uppercase tracking-wider mb-1">País</div>
                    <div class="text-sm font-medium">{{ $production->country }}</div>
                </div>
            @endif
            @if($production->production_year)
                <div class="cinema-card p-5">
                    <div class="text-xs text-[var(--text-muted)] uppercase tracking-wider mb-1">Año</div>
                    <div class="text-sm font-medium tabular-nums">{{ $production->production_year }}</div>
                </div>
            @endif
        </div>

        @if($production->genres && count($production->genres) > 0)
            <div class="cinema-card p-6 mb-6">
                <div class="text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-3">Géneros</div>
                <div class="flex flex-wrap gap-2">
                    @foreach($production->genres as $genre)
                        <span class="px-3 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-full text-sm text-[var(--text-secondary)]">
                            {{ $genre }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex items-center gap-3">
            <a href="{{ route('productions.edit', $production) }}" class="cinema-btn px-6 py-2.5 text-sm">
                Editar
            </a>
            <a href="{{ route('productions.matches', $production) }}" class="cinema-btn-outline px-6 py-2.5 text-sm">
                Ver matches sugeridos
            </a>
            <form action="{{ route('productions.destroy', $production) }}" method="POST" class="ml-auto"
                onsubmit="return confirm('¿Eliminar esta producción? No se puede deshacer.');">
                @csrf @method('DELETE')
                <button type="submit" class="text-sm text-[var(--danger)] hover:underline">
                    Eliminar
                </button>
            </form>
        </div>
    </main>

    <footer class="border-t border-[var(--border-color)] py-12">
        <div class="max-w-7xl mx-auto px-8 flex justify-between items-center">
            <p class="text-sm text-[var(--text-faintest)]">Consultfest</p>
            <p class="text-xs text-[var(--text-faint)]">Para cineastas independientes</p>
        </div>
    </footer>
</body>
</html>