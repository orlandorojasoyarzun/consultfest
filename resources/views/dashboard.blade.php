<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi panel - Consultfest</title>
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
                <div class="flex items-center gap-4">
                    <a href="{{ route('festivals.index') }}" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Festivales</a>
                    <span class="text-xs text-[var(--text-faintest)] hidden md:inline">{{ $subscriber->email }}</span>
                    <form action="{{ route('auth.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">
                            Cerrar sesión
                        </button>
                    </form>
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

    <main class="max-w-7xl mx-auto px-8 pt-12 pb-24 flex-1 w-full">
        @if(session('auth-flash'))
            <div class="cinema-card p-4 mb-6 border-[var(--success-border)]">
                <p class="text-sm text-[var(--success)]">{{ session('auth-flash') }}</p>
            </div>
        @endif

        <div class="mb-12">
            <h1 class="text-4xl md:text-5xl font-semibold tracking-tight mb-2">
                <span class="font-display italic font-normal">Hola,</span> {{ $subscriber->name }}
            </h1>
            <p class="text-sm text-[var(--text-muted)]">
                Gestiona tus producciones y suscripciones desde un solo lugar.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">

            {{-- Producciones --}}
            <section class="lg:col-span-7 cinema-card p-7">
                <div class="flex items-baseline justify-between mb-6">
                    <h2 class="text-sm font-semibold tracking-wide uppercase text-[var(--text-muted)]">Producciones</h2>
                    <span class="text-xs text-[var(--text-faintest)] tabular-nums">{{ $productions->count() }}</span>
                </div>

                @if($productions->isEmpty())
                    <div class="text-center py-10 border border-dashed border-[var(--border-color)] rounded-lg">
                        <svg class="w-10 h-10 mx-auto mb-3 text-[var(--text-faintest)]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5l4.5 4.5m15 0l-4.5 4.5m4.5-4.5l-4.5-4.5M3.75 19.5l4.5-4.5m0 0l4.5 4.5M8.25 15l4.5-4.5"/>
                        </svg>
                        <p class="text-sm text-[var(--text-muted)] mb-5">Aún no tienes producciones inscritas.</p>
                        <a href="{{ route('productions.create') }}" class="cinema-btn inline-block px-5 py-2 text-sm">Crear primera producción</a>
                    </div>
                @else
                    <ul class="space-y-3">
                        @foreach($productions as $production)
                            <li class="flex items-center justify-between gap-3 p-3 bg-[var(--bg-tertiary)] rounded-lg border border-[var(--border-color)]">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ route('productions.show', $production) }}" class="text-sm font-medium hover:text-[var(--accent)] transition-colors truncate block">{{ $production->title }}</a>
                                    <div class="flex items-center gap-2 text-xs text-[var(--text-faintest)] mt-0.5">
                                        @if($production->category)
                                            <span>{{ ucfirst(str_replace('_', ' ', $production->category)) }}</span>
                                        @endif
                                        @if($production->runtime_minutes)
                                            <span>·</span>
                                            <span class="tabular-nums">{{ $production->runtime_minutes }} min</span>
                                        @endif
                                        @if($production->country)
                                            <span>·</span>
                                            <span>{{ $production->country }}</span>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('productions.matches', $production) }}" class="text-xs text-[var(--accent)] hover:underline shrink-0">Matches →</a>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-5 text-right">
                        <a href="{{ route('productions.index') }}" class="text-xs text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Gestionar producciones →</a>
                    </div>
                @endif
            </section>

            {{-- Suscripciones --}}
            <section class="lg:col-span-5 cinema-card p-7">
                <div class="flex items-baseline justify-between mb-6">
                    <h2 class="text-sm font-semibold tracking-wide uppercase text-[var(--text-muted)]">Tus festivales</h2>
                    <span class="text-xs text-[var(--text-faintest)] tabular-nums">{{ $subscriptions->count() }}</span>
                </div>

                @if($subscriptions->isEmpty())
                    <div class="text-center py-10 border border-dashed border-[var(--border-color)] rounded-lg">
                        <p class="text-sm text-[var(--text-muted)] mb-5">No estás suscrito a ningún festival todavía.</p>
                        <a href="{{ route('festivals.index') }}" class="cinema-btn inline-block px-5 py-2 text-sm">Explorar festivales</a>
                    </div>
                @else
                    <ul class="space-y-2">
                        @foreach($subscriptions as $subscription)
                            <li class="flex items-center justify-between gap-3 p-3 bg-[var(--bg-tertiary)] rounded-lg border border-[var(--border-color)]">
                                <span class="text-sm truncate">{{ $subscription->festival->name ?? 'Festival eliminado' }}</span>
                                <span class="text-xs text-[var(--text-faintest)] shrink-0">
                                    @if($subscription->festival?->deadline)
                                        {{ \Carbon\Carbon::parse($subscription->festival->deadline)->format('d M Y') }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </main>

    @livewireScripts

    <footer class="border-t border-[var(--border-color)] py-12">
        <div class="max-w-7xl mx-auto px-8 flex justify-between items-center">
            <p class="text-sm text-[var(--text-faintest)]">Consultfest</p>
            <p class="text-xs text-[var(--text-faint)]">Para cineastas independientes</p>
        </div>
    </footer>
</body>
</html>
