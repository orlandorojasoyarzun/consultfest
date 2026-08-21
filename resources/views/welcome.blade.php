<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consultfest - Film Festival Search</title>
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
                    <button type="button" class="theme-toggle" aria-label="Cambiar tema">
                        <svg class="icon-sun" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                        </svg>
                        <svg class="icon-moon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
                        </svg>
                    </button>
                    @if(session('subscriber_id'))
                        <a href="{{ route('dashboard') }}" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Mi panel</a>
                        <form action="{{ route('auth.logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Cerrar sesión</button>
                        </form>
                    @else
                        <a href="{{ route('auth.login') }}" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Iniciar sesión</a>
                        <a href="{{ route('auth.register') }}" class="cinema-btn px-4 py-2 text-sm">Registrarse</a>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    @if(session('auth-flash'))
        <div class="max-w-7xl mx-auto px-8 pt-6">
            <div class="cinema-card p-4 border-[var(--success-border)]">
                <p class="text-sm text-[var(--success)]">{{ session('auth-flash') }}</p>
            </div>
        </div>
    @endif

    <main class="max-w-7xl mx-auto px-8 flex-1 w-full">
        <section class="pt-24 pb-20 relative clap-trigger">
            {{-- Clap decorativo. Renderizado enorme, con opacidad baja
                 y color accent (dorado del theme) para que sea apenas un
                 guiño cinematográfico en el fondo. La animación del lucide-
                 animated (dos <g> anidados con pivotes 4/20 y 3/11) se
                 reproduce en CSS puro cuando el usuario hace hover sobre
                 cualquier parte del hero — sacar y volver a pasar el mouse
                 la reinicia. `aria-hidden` porque es decorativo puro. --}}
            <div class="absolute bottom-[-12rem] right-0 pointer-events-none overflow-visible cinema-fade-up cinema-stagger-8" style="animation-duration: 900ms;">
                <svg
                    class="clap-svg w-[28rem] h-[28rem] md:w-[40rem] md:h-[40rem] text-[var(--accent)] opacity-[0.07]"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    viewBox="0 0 24 24"
                    style="overflow: visible;"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <g class="clap-outer">
                        <g class="clap-inner">
                            <path d="M20.2 6 3 11l-.9-2.4c-.3-1.1.3-2.2 1.3-2.5l13.5-4c1.1-.3 2.2.3 2.5 1.3Z" />
                            <path d="m6.2 5.3 3.1 3.9" />
                            <path d="m12.4 3.4 3.1 4" />
                        </g>
                        <path d="M3 11h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z" />
                    </g>
                </svg>
            </div>

            <div class="max-w-4xl relative z-10">
                <div class="inline-flex items-center gap-2 px-3 py-1 mb-8 cinema-card cinema-fade-up cinema-stagger-1">
                    <div class="w-1.5 h-1.5 bg-[var(--accent)] rounded-full"></div>
                    <span class="text-xs font-medium text-[var(--text-secondary)] tracking-wide">Tracking de festivales para cineastas independientes</span>
                </div>

                <h1 class="text-5xl md:text-6xl lg:text-7xl tracking-tighter leading-[1.05] mb-6 cinema-fade-up cinema-stagger-3">
                    <span class="font-display italic font-normal">Encuentra tu próximo</span><br>
                    <span class="font-display font-normal text-[var(--accent)]">festival</span>
                </h1>

                <p class="text-lg text-[var(--text-secondary)] max-w-2xl leading-relaxed mb-10 cinema-fade-up cinema-stagger-5">
                    Inscribe tus producciones audiovisuales una vez. Te avisamos qué festivales podrían aceptarlos antes de que cierren las convocatorias.
                </p>

                <div class="flex flex-col sm:flex-row gap-3 items-start cinema-fade-up cinema-stagger-7">
                    @if(session('subscriber_id'))
                        <a href="{{ route('dashboard') }}" class="cinema-btn inline-flex items-center gap-2 px-6 py-3.5 text-sm font-medium">
                            Ir a mi panel
                        </a>
                    @else
                        <a href="{{ route('auth.register') }}" class="cinema-btn inline-flex items-center gap-2 px-6 py-3.5 text-sm font-medium">
                            Crear cuenta gratis
                        </a>
                        <a href="{{ route('auth.login') }}" class="inline-flex items-center gap-2 px-6 py-3.5 text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">
                            Iniciar sesión →
                        </a>
                    @endif
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-[var(--border-color)] py-12">
        <div class="max-w-7xl mx-auto px-8 flex justify-between items-center">
            <p class="text-sm text-[var(--text-faintest)]">Consultfest</p>
            <p class="text-xs text-[var(--text-faint)]">Para cineastas independientes</p>
        </div>
    </footer>
</body>
</html>
