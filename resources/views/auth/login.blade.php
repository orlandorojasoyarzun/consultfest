<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión - Consultfest</title>
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
                        <a href="{{ route('auth.login') }}" class="text-sm text-[var(--text-primary)] font-medium">Iniciar sesión</a>
                        <a href="{{ route('auth.register') }}" class="cinema-btn px-4 py-2 text-sm">Registrarse</a>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-1 flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
            <div class="text-center mb-10">
                <h1 class="text-3xl md:text-4xl font-semibold tracking-tight mb-3 cinema-fade-up cinema-stagger-1">
                    <span class="font-display italic font-normal">Inicia sesión</span>
                </h1>
                <p class="text-sm text-[var(--text-muted)] cinema-fade-up cinema-stagger-2">
                    Accede a tu panel para gestionar tus producciones y suscripciones.
                </p>
            </div>

            @if(session('auth-flash'))
                <div class="cinema-card p-4 mb-6 border-[var(--success-border)]">
                    <p class="text-sm text-[var(--success)]">{{ session('auth-flash') }}</p>
                </div>
            @endif

            @if($errors->any())
                <div class="cinema-card p-4 mb-6 border-[var(--danger)]">
                    <ul class="text-sm text-[var(--danger)] space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <a href="{{ route('auth.google') }}" target="_blank" rel="noopener noreferrer" class="cinema-btn w-full inline-flex items-center justify-center gap-3 px-6 py-3.5 text-sm font-medium cinema-fade-up cinema-stagger-3">
                <svg class="w-4 h-4" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
                    <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
                    <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0124 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
                    <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 01-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
                </svg>
                <span>Continuar con Google</span>
            </a>

            <div class="flex items-center gap-3 my-6">
                <div class="flex-1 h-px bg-[var(--border-color)]"></div>
                <span class="text-xs uppercase tracking-wider text-[var(--text-faintest)]">o</span>
                <div class="flex-1 h-px bg-[var(--border-color)]"></div>
            </div>

            <form action="{{ route('auth.login.process') }}" method="POST" class="space-y-4 cinema-fade-up cinema-stagger-5">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-2">
                        Email
                    </label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus maxlength="255"
                        class="cinema-input w-full px-4 py-3 text-sm @error('email') cinema-input-error @enderror"
                        placeholder="tu@correo.com">
                    @error('email')
                        <p class="mt-2 text-xs text-[var(--danger)]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-2">
                        Contraseña
                    </label>
                    <input type="password" id="password" name="password" required
                        class="cinema-input w-full px-4 py-3 text-sm @error('password') cinema-input-error @enderror"
                        placeholder="Tu contraseña">
                    @error('password')
                        <p class="mt-2 text-xs text-[var(--danger)]">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between text-sm">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}
                            class="rounded border-[var(--border-color)] bg-[var(--bg-tertiary)] text-[var(--accent)] focus:ring-[var(--accent)] focus:ring-offset-0">
                        <span class="text-[var(--text-muted)]">Recuérdame</span>
                    </label>
                    <a href="{{ route('auth.forgot-password') }}" class="text-[var(--text-muted)] hover:text-[var(--accent)] transition-colors">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>

                <button type="submit" class="cinema-btn w-full px-6 py-3 text-sm font-medium">
                    Iniciar sesión
                </button>
            </form>

            <p class="text-center text-sm text-[var(--text-muted)] mt-8 cinema-fade-up cinema-stagger-7">
                ¿No tienes cuenta?
                <a href="{{ route('auth.register') }}" class="text-[var(--accent)] hover:underline transition-colors font-medium">Crea una →</a>
            </p>
        </div>
    </main>

    <footer class="border-t border-[var(--border-color)] py-8">
        <div class="max-w-7xl mx-auto px-8 flex justify-between items-center">
            <p class="text-sm text-[var(--text-faintest)]">Consultfest</p>
            <p class="text-xs text-[var(--text-faint)]">Para cineastas independientes</p>
        </div>
    </footer>
</body>
</html>
