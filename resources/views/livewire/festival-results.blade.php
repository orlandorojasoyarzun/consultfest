<div>
    @if($isLoading)
        <div class="flex items-center justify-center py-16">
            <div class="flex items-center gap-3 text-[#a3a3a3]">
                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Cargando festivales...</span>
            </div>
        </div>
    @elseif($totalCount === 0)
        <div class="text-center py-16">
            <svg class="mx-auto h-12 w-12 text-[#525252]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
            </svg>
            <h3 class="mt-4 text-lg font-medium text-[#a3a3a3]">No hay festivales</h3>
            <p class="mt-1 text-sm text-[#525252]">Intenta con otro rango de fechas o ajusta los filtros.</p>
        </div>
    @else
        <div class="divide-y divide-[#2a2a2a]">
            @foreach($festivals as $festival)
                <a
                    href="{{ route('festivals.show', $festival) }}"
                    class="block px-6 py-5 hover:bg-[#1a1a1a]/50 transition-colors group"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 mb-1">
                                <h3 class="font-medium text-[#fafafa] group-hover:text-[#d4a853] transition-colors truncate">
                                    {{ $festival->name }}
                                </h3>
                                @if($festival->festival_score)
                                    <span class="cinema-badge cinema-badge-score shrink-0">
                                        {{ $festival->festival_score }}
                                    </span>
                                @endif
                            </div>
                            <div class="flex items-center gap-4 text-sm text-[#a3a3a3]">
                                @if($festival->country)
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        {{ $festival->country }}
                                    </span>
                                @endif
                                @if($festival->category)
                                    <span class="px-2 py-0.5 bg-[#2a2a2a] rounded text-xs">
                                        {{ ucfirst(str_replace('_', ' ', $festival->category)) }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-col items-end gap-2 shrink-0">
                            @if($festival->deadline)
                                <div class="text-right">
                                    <div class="text-xs text-[#525252] uppercase tracking-wider mb-0.5">Deadline</div>
                                    <span class="cinema-badge cinema-badge-deadline">
                                        {{ $festival->deadline->format('M d, Y') }}
                                    </span>
                                </div>
                            @endif
                            @if($festival->opening_date)
                                <div class="text-right">
                                    <div class="text-xs text-[#525252] uppercase tracking-wider mb-0.5">Apertura</div>
                                    <span class="cinema-badge cinema-badge-opening">
                                        {{ $festival->opening_date->format('M d, Y') }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($festival->submission_fee || $festival->accepting_submissions)
                        <div class="flex items-center gap-4 mt-3 pt-3 border-t border-[#2a2a2a]">
                            @if($festival->submission_fee)
                                <span class="text-sm text-[#a3a3a3]">
                                    Fee: <span class="text-[#fafafa]">${{ number_format($festival->submission_fee, 2) }}</span>
                                </span>
                            @endif
                            @if($festival->accepting_submissions)
                                <span class="flex items-center gap-1 text-sm text-[#16a34a]">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    Abierto
                                </span>
                            @else
                                <span class="flex items-center gap-1 text-sm text-[#dc2626]">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
                                    Cerrado
                                </span>
                            @endif
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</div>
