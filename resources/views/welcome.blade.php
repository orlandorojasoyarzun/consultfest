<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Consultfest - Film Festival Search</title>
    @fonts
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles
</head>
<body class="min-h-screen">
    <nav class="border-b border-[#2a2a2a] bg-[#0a0a0a]/95 backdrop-blur-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-[#d4a853] rounded flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#0a0a0a]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                        </svg>
                    </div>
                    <span class="text-xl font-bold tracking-tight">Consultfest</span>
                </div>
                <a href="{{ route('festivals.index') }}" class="text-sm text-[#a3a3a3] hover:text-[#d4a853] transition-colors">
                    Ver todos los festivales
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold tracking-tight mb-2">Festivales de Cine</h1>
            <p class="text-[#a3a3a3]">Encuentra tu próximo festival y recibe recordatorios antes de los deadlines.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <aside class="lg:col-span-4 space-y-6">
                <div class="cinema-card p-6 cinema-glow">
                    <h2 class="text-lg font-semibold mb-6 flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#d4a853]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        Filtros
                    </h2>
                    @livewire('festival-calendar')
                </div>

                <div class="cinema-card p-6">
                    <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#d4a853]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        Notificaciones
                    </h2>
                    @livewire('subscriber-form')
                </div>
            </aside>

            <section class="lg:col-span-8">
                <div class="cinema-card overflow-hidden">
                    <div class="px-6 py-4 border-b border-[#2a2a2a] flex justify-between items-center">
                        <h2 class="font-semibold">Resultados</h2>
                        <span class="text-sm text-[#a3a3a3]">{{ \App\Models\Festival::count() }} festivales</span>
                    </div>

                    <div id="festival-results">
                        @livewire('festival-results')
                    </div>
                </div>
            </section>
        </div>
    </main>

    <footer class="border-t border-[#2a2a2a] mt-16 py-8">
        <div class="max-w-7xl mx-auto px-6 text-center text-sm text-[#525252]">
            <p>Consultfest — Tu herramienta para festivales de cine</p>
        </div>
    </footer>

    @livewireScripts
</body>
</html>
