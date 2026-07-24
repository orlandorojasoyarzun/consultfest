<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $festival->name }} - Consultfest</title>
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
                <a href="{{ route('festivals.index') }}" class="text-sm text-[#a3a3a3] hover:text-[#d4a853] transition-colors">
                    ← Volver a festivales
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-6 py-8">
        <div class="mb-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight mb-2">{{ $festival->name }}</h1>
                    <div class="flex items-center gap-4 text-[#a3a3a3]">
                        @if($festival->country)
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                {{ $festival->country }}
                            </span>
                        @endif
                        @if($festival->category)
                            <span class="px-2 py-0.5 bg-[#2a2a2a] rounded text-sm">
                                {{ ucfirst(str_replace('_', ' ', $festival->category)) }}
                            </span>
                        @endif
                    </div>
                </div>
                @if($festival->festival_score)
                    <div class="text-right">
                        <div class="text-xs text-[#525252] uppercase tracking-wider mb-1">Score</div>
                        <span class="text-3xl font-bold text-[#d4a853]">{{ $festival->festival_score }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-8">
            <div class="cinema-card p-6">
                <h2 class="text-sm text-[#525252] uppercase tracking-wider mb-4">Fechas Importantes</h2>
                <div class="space-y-4">
                    <div>
                        <div class="text-xs text-[#a3a3a3] mb-1">Deadline</div>
                        @if($festival->deadline)
                            <div class="flex items-center gap-2">
                                <span class="text-xl font-semibold">{{ $festival->deadline->format('M d, Y') }}</span>
                                @if($festival->deadline->isFuture())
                                    @php $daysLeft = now()->diffInDays($festival->deadline) @endphp
                                    <span class="text-xs px-2 py-0.5 bg-[#dc2626]/15 text-[#f87171] rounded">
                                        {{ $daysLeft }} días
                                    </span>
                                @endif
                            </div>
                        @else
                            <span class="text-[#525252]">No disponible</span>
                        @endif
                    </div>
                    <div>
                        <div class="text-xs text-[#a3a3a3] mb-1">Apertura</div>
                        @if($festival->opening_date)
                            <span class="text-xl font-semibold">{{ $festival->opening_date->format('M d, Y') }}</span>
                        @else
                            <span class="text-[#525252]">No disponible</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="cinema-card p-6">
                <h2 class="text-sm text-[#525252] uppercase tracking-wider mb-4">Información</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-[#a3a3a3]">Fee</span>
                        <span class="font-medium">
                            @if($festival->submission_fee)
                                ${{ number_format($festival->submission_fee, 2) }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#a3a3a3]">Estado</span>
                        @if($festival->accepting_submissions)
                            <span class="flex items-center gap-1 text-[#16a34a]">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Abriendo
                            </span>
                        @else
                            <span class="flex items-center gap-1 text-[#dc2626]">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                Cerrado
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if(isset($festival->details['genres']) && is_array($festival->details['genres']))
            <div class="cinema-card p-6 mb-8">
                <h2 class="text-sm text-[#525252] uppercase tracking-wider mb-4">Géneros</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($festival->details['genres'] as $genre)
                        <span class="px-3 py-1 bg-[#2a2a2a] rounded-full text-sm text-[#a3a3a3]">
                            {{ $genre }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="cinema-card p-6">
            <h2 class="text-lg font-semibold mb-4">Suscribirse a Notificaciones</h2>

            @if(session('success'))
                <div class="p-4 bg-[#16a34a]/10 border border-[#16a34a]/20 rounded-lg mb-4">
                    <p class="text-[#4ade80]">{{ session('success') }}</p>
                </div>
            @endif

            @if($isSubscribed)
                <div class="p-4 bg-[#16a34a]/10 border border-[#16a34a]/20 rounded-lg">
                    <div class="flex items-center justify-between">
                        <p class="text-[#fafafa]">Estás suscrito a este festival.</p>
                        <form action="{{ route('festivals.unsubscribe', $festival->api_id) }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-sm text-[#dc2626] hover:text-[#f87171] transition-colors">
                                Desuscribirse
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <form action="{{ route('festivals.subscribe') }}" method="POST" class="flex gap-4">
                    @csrf
                    <input type="hidden" name="festival_api_id" value="{{ $festival->api_id }}">

                    <select name="notification_type" required class="cinema-input px-4 py-2.5 flex-1">
                        <option value="both">Recordatorio de apertura y deadline</option>
                        <option value="opening">Solo apertura</option>
                        <option value="deadline">Solo deadline</option>
                    </select>

                    <button type="submit" class="cinema-btn px-6 py-2.5">
                        Suscribirme
                    </button>
                </form>
                <p class="text-xs text-[#525252] mt-3">
                    Recibirás un email recordándote antes de que cierre la postulación.
                </p>
            @endif
        </div>
    </main>

    @livewireStyles
</body>
</html>
