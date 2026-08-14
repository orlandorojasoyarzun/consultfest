<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $production->exists ? 'Editar' : 'Nueva' }} producción - Consultfest</title>
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

    <main class="max-w-3xl mx-auto px-8 pt-12 pb-24 flex-1 w-full">
        <h1 class="text-3xl md:text-4xl font-semibold tracking-tight mb-8">
            {{ $production->exists ? 'Editar producción' : 'Nueva producción' }}
        </h1>

        @if($errors->any())
            <div class="cinema-card p-4 mb-6 border-[var(--danger)]">
                <ul class="text-sm text-[var(--danger)] space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ $production->exists ? route('productions.update', $production) : route('productions.store') }}" method="POST" class="space-y-5">
            @csrf
            @if($production->exists) @method('PUT') @endif

            <div class="cinema-card p-6">
                <label class="block text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-2">
                    Título <span class="text-[var(--danger)]">*</span>
                </label>
                <input type="text" name="title" value="{{ old('title', $production->title) }}" required maxlength="255"
                    class="cinema-input w-full px-4 py-2.5 text-sm @error('title') cinema-input-error @enderror">
            </div>

            <div class="cinema-card p-6">
                <label class="block text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-2">Sinopsis</label>
                <textarea name="synopsis" rows="4" maxlength="5000"
                    class="cinema-input w-full px-4 py-2.5 text-sm">{{ old('synopsis', $production->synopsis) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="cinema-card p-6">
                    <label class="block text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-2">Categoría</label>
                    <select name="category" class="cinema-input w-full px-4 py-2.5 text-sm">
                        <option value="">— Ninguna —</option>
                        @foreach(['short_film', 'feature', 'documentary', 'animation', 'horror', 'sci_fi', 'comedy', 'experimental'] as $cat)
                            <option value="{{ $cat }}" @selected(old('category', $production->category) === $cat)>
                                {{ ucfirst(str_replace('_', ' ', $cat)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="cinema-card p-6">
                    <label class="block text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-2">Duración (min)</label>
                    <input type="number" name="runtime_minutes" value="{{ old('runtime_minutes', $production->runtime_minutes) }}"
                        min="1" max="600" class="cinema-input w-full px-4 py-2.5 text-sm tabular-nums">
                </div>

                <div class="cinema-card p-6">
                    <label class="block text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-2">Formato</label>
                    <select name="format" class="cinema-input w-full px-4 py-2.5 text-sm">
                        <option value="">—</option>
                        @foreach(['digital', 'film', 'any'] as $fmt)
                            <option value="{{ $fmt }}" @selected(old('format', $production->format) === $fmt)>
                                {{ ucfirst($fmt) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="cinema-card p-6">
                    <label class="block text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-2">País</label>
                    <input type="text" name="country" value="{{ old('country', $production->country) }}" maxlength="255"
                        class="cinema-input w-full px-4 py-2.5 text-sm">
                </div>

                <div class="cinema-card p-6">
                    <label class="block text-xs font-medium text-[var(--text-muted)] uppercase tracking-wider mb-2">Estado de la producción</label>
                    <select name="status" class="cinema-input w-full px-4 py-2.5 text-sm">
                        @foreach(['draft', 'active', 'archived'] as $st)
                            <option value="{{ $st }}" @selected(old('status', $production->status ?? 'draft') === $st)>
                                {{ ucfirst($st) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Año: removed from the form on 2026-08-10. The Production
                 model still has a `production_year` column and the controller
                 still accepts it, so the data lives on if it was set before,
                 but the UI no longer asks for it. Re-enable by pasting back
                 the cinema-card block.
                 Label "Estado" → "Estado de la producción" on 2026-08-10 to
                 avoid confusion with a country's state/region. The form
                 field name is still `status` (unchanged). --}}
            {{-- Géneros: removed from the form on 2026-08-10. The Production
                 model still has a `genres` column and the controller still
                 accepts `genres_text`, so the data lives on if it was set
                 before, but the UI no longer asks for it. Re-enable by
                 pasting back the cinema-card block. --}}

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="cinema-btn px-6 py-3 text-sm">
                    {{ $production->exists ? 'Guardar cambios' : 'Crear producción' }}
                </button>
                <a href="{{ route('productions.index') }}" class="text-sm text-[var(--text-muted)] hover:text-[var(--text-primary)] transition-colors">
                    Cancelar
                </a>
            </div>
        </form>
    </main>

    <footer class="border-t border-[var(--border-color)] py-12">
        <div class="max-w-7xl mx-auto px-8 flex justify-between items-center">
            <p class="text-sm text-[var(--text-faintest)]">Consultfest</p>
            <p class="text-xs text-[var(--text-faint)]">Para cineastas independientes</p>
        </div>
    </footer>
</body>
</html>