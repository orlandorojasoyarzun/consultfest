<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $festival->name }} - Consultfest</title>
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
                <a href="/" class="flex items-center gap-2.5">
                    <div class="w-7 h-7 bg-[var(--accent)] rounded-md flex items-center justify-center">
                        <svg class="w-4 h-4 text-[var(--accent-text)]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                        </svg>
                    </div>
                    <span class="text-base font-semibold tracking-tight">Consultfest</span>
                </a>
                <div class="flex items-center gap-3">
                    <a href="{{ route('festivals.index') }}" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">
                        ← Festivales
                    </a>
                    @if(session('subscriber_id'))
                        <a href="{{ route('dashboard') }}" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">
                            Mi panel
                        </a>
                        <form action="{{ route('auth.logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Cerrar sesión</button>
                        </form>
                    @else
                        <a href="{{ route('auth.login') }}" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">
                            Iniciar sesión
                        </a>
                        <a href="{{ route('auth.register') }}" class="cinema-btn px-4 py-2 text-sm">Crear cuenta</a>
                    @endif
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

    <main class="max-w-4xl mx-auto px-8 pt-16 pb-24 flex-1 w-full">
        <div class="mb-12">
            <div class="flex items-start justify-between gap-6 mb-4">
                <div class="flex-1">
                    <h1 class="text-4xl md:text-5xl font-semibold tracking-tight mb-3">
                        {{ $festival->name }}
                    </h1>
                    <div class="flex items-center gap-3 text-sm text-[var(--text-muted)]">
                        @if($festival->country)
                            <span class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                {{ $festival->country }}
                            </span>
                        @endif
                        @if($festival->category)
                            <span class="text-[var(--text-faintest)]">·</span>
                            <span>{{ ucfirst(str_replace('_', ' ', $festival->category)) }}</span>
                        @endif
                    </div>
                </div>
                @if($festival->festival_score)
                    <div class="text-right">
                        <div class="text-xs text-[var(--text-faintest)] uppercase tracking-wider font-medium mb-1">Score</div>
                        <div class="text-4xl font-semibold text-[var(--accent)] tabular-nums">{{ $festival->festival_score }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-12">
            <div class="cinema-card p-6">
                <div class="text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-3">Apertura</div>
                @if($festival->opening_date)
                    <div class="text-2xl font-semibold tabular-nums">{{ $festival->opening_date->format('M d, Y') }}</div>
                @else
                    <span class="text-[var(--text-faintest)]">No disponible</span>
                @endif
            </div>

            <div class="cinema-card p-6">
                <div class="text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-3">Deadline</div>
                @if($festival->deadline)
                    <div class="text-2xl font-semibold tabular-nums mb-2">{{ $festival->deadline->format('M d, Y') }}</div>
                    @if($festival->deadline->isFuture())
                        @php $daysLeft = (int) now()->diffInDays($festival->deadline) @endphp
                        <div class="text-sm text-[var(--danger)] tabular-nums">{{ $daysLeft }} días restantes</div>
                    @endif
                @else
                    <span class="text-[var(--text-faintest)]">No disponible</span>
                @endif
            </div>
        </div>

        <div class="cinema-card p-6 mb-6">
            <div class="text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-4">Detalles</div>
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <div class="text-xs text-[var(--text-muted)] mb-1.5">Submission Fee</div>
                    <div class="text-lg font-medium tabular-nums">
                        @if($festival->submission_fee)
                            ${{ number_format($festival->submission_fee, 2) }}
                        @else
                            <span class="text-[var(--text-faintest)]">—</span>
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-xs text-[var(--text-muted)] mb-1.5">Estado</div>
                    <div class="text-lg font-medium">
                        @if($festival->accepting_submissions)
                            <span class="text-[var(--success)]">Abierto</span>
                        @else
                            <span class="text-[var(--text-muted)]">Cerrado</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if(isset($festival->details['genres']) && is_array($festival->details['genres']))
            <div class="cinema-card p-6 mb-6">
                <div class="text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-4">Géneros</div>
                <div class="flex flex-wrap gap-2">
                    @foreach($festival->details['genres'] as $genre)
                        <span class="px-3 py-1 bg-[var(--bg-tertiary)] border border-[var(--border-color)] rounded-full text-sm text-[var(--text-secondary)]">
                            {{ $genre }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="cinema-card p-6">
            <div class="text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-4">Notificaciones</div>

            @if(session('success'))
                <div class="p-3 mb-4 bg-[var(--success-soft)] border border-[var(--success-border)] rounded-lg">
                    <p class="text-sm text-[var(--success)]">{{ session('success') }}</p>
                </div>
            @endif

            @if($isSubscribed)
                <div class="p-4 bg-[var(--success-soft)] border border-[var(--success-border)] rounded-lg">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-[var(--text-primary)]">Estás suscrito a este festival.</p>
                        <form action="{{ route('festivals.unsubscribe', $festival->api_id) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-sm text-[var(--text-muted)] hover:text-[var(--danger)] transition-colors">
                                Desuscribirse
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <form action="{{ route('festivals.subscribe') }}" method="POST" class="flex gap-3">
                    @csrf
                    <input type="hidden" name="festival_api_id" value="{{ $festival->api_id }}">

                    <select name="notification_type" required class="cinema-input px-4 py-2.5 flex-1 text-sm">
                        <option value="both">Apertura + Deadline</option>
                        <option value="opening">Solo apertura</option>
                        <option value="deadline">Solo deadline</option>
                    </select>

                    <button type="submit" class="cinema-btn px-6 py-2.5 text-sm">
                        Suscribirme
                    </button>
                </form>
                <p class="text-xs text-[var(--text-faintest)] mt-3">
                    Te avisaremos antes de que cierre la postulación.
                </p>
            @endif
        </div>
    </main>

    @livewireStyles
</body>
</html>
