<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Festivales - Consultfest</title>
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
                    <a href="/" class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-[#d4a853] rounded flex items-center justify-center">
                            <svg class="w-5 h-5 text-[#0a0a0a]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                            </svg>
                        </div>
                        <span class="text-xl font-bold tracking-tight">Consultfest</span>
                    </a>
                </div>
                <a href="/" class="text-sm text-[#a3a3a3] hover:text-[#d4a853] transition-colors">
                    ← Volver al inicio
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold tracking-tight mb-2">Todos los Festivales</h1>
            <p class="text-[#a3a3a3]">{{ $festivals->total() }} festivales en la base de datos</p>
        </div>

        <div class="cinema-card overflow-hidden">
            <table class="cinema-table">
                <thead>
                    <tr>
                        <th>Festival</th>
                        <th>Categoría</th>
                        <th>País</th>
                        <th>Deadline</th>
                        <th>Apertura</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($festivals as $festival)
                        <tr>
                            <td>
                                <a href="{{ route('festivals.show', $festival) }}" class="font-medium hover:text-[#d4a853] transition-colors">
                                    {{ $festival->name }}
                                </a>
                            </td>
                            <td class="text-[#a3a3a3]">
                                {{ $festival->category ? ucfirst(str_replace('_', ' ', $festival->category)) : '-' }}
                            </td>
                            <td class="text-[#a3a3a3]">
                                {{ $festival->country ?? '-' }}
                            </td>
                            <td>
                                @if($festival->deadline)
                                    <span class="cinema-badge cinema-badge-deadline">
                                        {{ $festival->deadline->format('M d, Y') }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($festival->opening_date)
                                    <span class="cinema-badge cinema-badge-opening">
                                        {{ $festival->opening_date->format('M d, Y') }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($festival->festival_score)
                                    <span class="cinema-badge cinema-badge-score">
                                        {{ $festival->festival_score }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-12 text-[#525252]">
                                No hay festivales disponibles.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $festivals->links() }}
        </div>
    </main>

    @livewireStyles
</body>
</html>
