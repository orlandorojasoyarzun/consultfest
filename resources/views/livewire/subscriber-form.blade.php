<div>
    @if(!$isRegistered)
        <form wire:submit="register" class="space-y-4">
            <div>
                <label class="block text-xs text-[#a3a3a3] mb-1.5 uppercase tracking-wider">Nombre</label>
                <input
                    type="text"
                    wire:model="name"
                    required
                    placeholder="Tu nombre"
                    class="cinema-input w-full px-3 py-2.5 text-sm"
                >
            </div>

            <div>
                <label class="block text-xs text-[#a3a3a3] mb-1.5 uppercase tracking-wider">Email</label>
                <input
                    type="email"
                    wire:model="email"
                    required
                    placeholder="tu@email.com"
                    class="cinema-input w-full px-3 py-2.5 text-sm"
                >
            </div>

            <div>
                <label class="block text-xs text-[#a3a3a3] mb-1.5 uppercase tracking-wider">Teléfono (opcional)</label>
                <input
                    type="tel"
                    wire:model="phone"
                    placeholder="+1 234 567 890"
                    class="cinema-input w-full px-3 py-2.5 text-sm"
                >
            </div>

            <div class="flex items-center gap-3">
                <input
                    type="checkbox"
                    wire:model="notificationsEnabled"
                    id="notificationsEnabled"
                    class="w-4 h-4 rounded border-[#2a2a2a] bg-[#141414] text-[#d4a853] focus:ring-[#d4a853] focus:ring-offset-0"
                >
                <label for="notificationsEnabled" class="text-sm text-[#a3a3a3]">
                    Recibir recordatorios
                </label>
            </div>

            <button
                type="submit"
                class="cinema-btn w-full py-2.5 text-sm"
            >
                Registrarme
            </button>
        </form>
    @else
        <div>
            <div class="flex items-center gap-3 p-3 bg-[#16a34a]/10 border border-[#16a34a]/20 rounded-lg mb-4">
                <div class="w-8 h-8 bg-[#16a34a]/20 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-[#4ade80]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-[#fafafa]">Hola, {{ $name }}</p>
                    <p class="text-xs text-[#a3a3a3]">{{ $email }}</p>
                </div>
            </div>

            <p class="text-xs text-[#a3a3a3] mb-3">Festivales suscritos:</p>

            @if($subscriptions->isEmpty())
                <p class="text-sm text-[#525252] py-4 text-center border border-dashed border-[#2a2a2a] rounded">
                    No has suscrito a ningún festival todavía.
                </p>
            @else
                <ul class="space-y-2">
                    @foreach($subscriptions as $subscription)
                        <li class="flex items-center justify-between p-2 bg-[#141414] rounded border border-[#2a2a2a]">
                            <span class="text-sm truncate">{{ $subscription->festival->name }}</span>
                            <button
                                wire:click="unsubscribeFromFestival({{ $subscription->festival->api_id }})"
                                class="text-xs text-[#dc2626] hover:text-[#f87171] transition-colors ml-2 shrink-0"
                            >
                                Eliminar
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            <button
                wire:click="logout"
                class="mt-4 text-xs text-[#525252] hover:text-[#a3a3a3] transition-colors"
            >
                Cerrar sesión
            </button>
        </div>
    @endif
</div>
