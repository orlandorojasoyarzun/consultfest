<div>
    @if (session()->has('subscriber-flash'))
        <div class="mb-4 px-3 py-2 rounded-lg bg-[var(--success-soft)] border border-[var(--success-border)] text-xs text-[var(--success)]">
            {{ session('subscriber-flash') }}
        </div>
    @endif

    @if (session()->has('production-flash'))
        <div class="mb-4 px-3 py-2 rounded-lg bg-[var(--danger-soft)] border border-[var(--danger-border)] text-xs text-[var(--danger)]">
            {{ session('production-flash') }}
        </div>
    @endif

    @if(!$isRegistered)
        <form wire:submit="register" class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-[var(--text-muted)] mb-2">Nombre</label>
                <input
                    type="text"
                    wire:model="name"
                    placeholder="Tu nombre"
                    class="cinema-input w-full px-3.5 py-2.5 text-sm @error('name') cinema-input-error @enderror"
                >
                @error('name')
                    <p class="mt-1 text-xs text-[var(--danger)]">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-[var(--text-muted)] mb-2">Email</label>
                <input
                    type="email"
                    wire:model="email"
                    placeholder="tu@email.com"
                    class="cinema-input w-full px-3.5 py-2.5 text-sm @error('email') cinema-input-error @enderror"
                >
                @error('email')
                    <p class="mt-1 text-xs text-[var(--danger)]">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-[var(--text-muted)] mb-2">Teléfono</label>
                <input
                    type="tel"
                    wire:model="phone"
                    placeholder="Opcional"
                    class="cinema-input w-full px-3.5 py-2.5 text-sm @error('phone') cinema-input-error @enderror"
                >
                @error('phone')
                    <p class="mt-1 text-xs text-[var(--danger)]">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-2.5 pt-1">
                <input
                    type="checkbox"
                    wire:model="notificationsEnabled"
                    id="notificationsEnabled"
                    class="w-4 h-4 rounded border-[var(--border-color)] bg-[var(--bg-tertiary)] text-[var(--accent)] focus:ring-[var(--accent)] focus:ring-offset-0 focus:ring-1"
                >
                <label for="notificationsEnabled" class="text-sm text-[var(--text-secondary)]">
                    Recibir recordatorios
                </label>
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="register"
                class="cinema-btn w-full py-2.5 text-sm mt-2 disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <span wire:loading.remove wire:target="register">Registrarme</span>
                <span wire:loading wire:target="register">Registrando…</span>
            </button>
        </form>
    @else
        <div>
            @error('selectedFestivals')
                <div class="mb-4 px-3 py-2 rounded-lg bg-[var(--danger-soft)] border border-[var(--danger-border)] text-xs text-[var(--danger)]">
                    {{ $message }}
                </div>
            @enderror

            <div class="flex items-center gap-3 p-3 bg-[var(--success-soft)] border border-[var(--success-border)] rounded-lg mb-5">
                <div class="w-7 h-7 bg-[var(--success-soft)] rounded-full flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5 text-[var(--success)]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-[var(--text-primary)] truncate">{{ $name }}</p>
                    <p class="text-xs text-[var(--text-muted)] truncate">{{ $email }}</p>
                </div>
            </div>

            <div class="text-xs font-medium text-[var(--text-muted)] mb-3">Tus festivales</div>

            @if(empty($subscriptions) || count($subscriptions) === 0)
                <p class="text-sm text-[var(--text-faintest)] py-8 text-center border border-dashed border-[var(--border-color)] rounded-lg">
                    Aún no has suscrito a ningún festival.
                </p>
            @else
                <ul class="space-y-2">
                    @foreach($subscriptions as $subscription)
                        <li class="flex items-center justify-between p-3 bg-[var(--bg-tertiary)] rounded-lg border border-[var(--border-color)]">
                            <span class="text-sm truncate">{{ $subscription->festival->name }}</span>
                            <button
                                wire:click="unsubscribeFromFestival({{ $subscription->festival->api_id }})"
                                wire:loading.attr="disabled"
                                wire:target="unsubscribeFromFestival"
                                class="text-xs text-[var(--text-muted)] hover:text-[var(--danger)] transition-colors ml-2 shrink-0 disabled:opacity-50"
                            >
                                Eliminar
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            <button
                wire:click="logout"
                wire:loading.attr="disabled"
                wire:target="logout"
                class="mt-5 text-xs text-[var(--text-faintest)] hover:text-[var(--text-muted)] transition-colors disabled:opacity-50"
            >
                Cerrar sesión
            </button>
        </div>
    @endif
</div>
