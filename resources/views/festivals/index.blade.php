<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Festivales - Consultfest</title>
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
                    <a href="/" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">
                        ← Volver
                    </a>
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

    <main class="max-w-7xl mx-auto px-8 pt-16 pb-24 flex-1 w-full">
        <div class="mb-12">
            <h1 class="text-4xl font-semibold tracking-tight mb-2">Explorar festivales</h1>
            <p class="text-sm text-[var(--text-muted)]">Filtra por fecha de apertura o deadline para encontrar los festivales que te interesan.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <aside class="lg:col-span-4 space-y-6">
                <div class="cinema-card p-7">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-sm font-semibold tracking-wide uppercase text-[var(--text-muted)]">Filtros</h3>
                        <svg class="w-4 h-4 text-[var(--text-faintest)]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                    </div>
                    @livewire('festival-calendar')
                </div>
            </aside>

            <section class="lg:col-span-8">
                <div class="flex items-baseline justify-between mb-6">
                    <h3 class="text-sm font-semibold tracking-wide uppercase text-[var(--text-muted)]">Resultados</h3>
                </div>

                <div id="festival-results">
                    @livewire('festival-results')
                </div>
            </section>
        </div>
    </main>

    @livewireScripts
</body>
</html>
