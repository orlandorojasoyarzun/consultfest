<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Restablecer contraseña - Consultfest</title>
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
                    @else
                        <a href="{{ route('auth.login') }}" class="text-sm text-[var(--text-secondary)] hover:text-[var(--text-primary)] transition-colors">Iniciar sesión</a>
                        <a href="{{ route('auth.register') }}" class="cinema-btn px-4 py-2 text-sm">Registrarse</a>
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-1 flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
            <div class="text-center mb-10">
                <h1 class="text-3xl md:text-4xl font-semibold tracking-tight mb-3">
                    <span class="font-display italic font-normal">Nueva contraseña</span>
                </h1>
                <p class="text-sm text-[var(--text-muted)]">
                    Elige una contraseña nueva para tu cuenta.
                </p>
            </div>

            @if($errors->any())
                <div class="cinema-card p-4 mb-6 border-[var(--danger)]">
                    <ul class="text-sm text-[var(--danger)] space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('auth.password.store') }}" method="POST" class="space-y-4">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div>
                    <label for="password" class="block text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-2">
                        Nueva contraseña
                    </label>
                    <input type="password" id="password" name="password" required autofocus minlength="8"
                        class="cinema-input w-full px-4 py-3 text-sm @error('password') cinema-input-error @enderror"
                        placeholder="Mínimo 8 caracteres">
                    @error('password')
                        <p class="mt-2 text-xs text-[var(--danger)]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-2">
                        Confirma tu contraseña
                    </label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8"
                        class="cinema-input w-full px-4 py-3 text-sm"
                        placeholder="Repite la contraseña">
                </div>

                <button type="submit" class="cinema-btn w-full px-6 py-3 text-sm font-medium mt-2">
                    Restablecer contraseña
                </button>
            </form>

            <p class="text-center text-sm text-[var(--text-muted)] mt-8">
                <a href="{{ route('auth.login') }}" class="text-[var(--accent)] hover:underline transition-colors font-medium">← Volver a iniciar sesión</a>
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
