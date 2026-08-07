<div>
    <div class="space-y-5">
        <div>
            <label class="block text-xs font-medium text-[var(--text-muted)] mb-2">Filtrar por fecha de</label>
            <select
                wire:model.live.debounce.300ms="dateField"
                class="cinema-input w-full px-3.5 py-2.5 text-sm"
            >
                <option value="">Cualquiera</option>
                <option value="opening_date">Apertura</option>
                <option value="deadline">Deadline</option>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-[var(--text-muted)] mb-2">Inicio</label>
                <input
                    type="date"
                    wire:model.live.debounce.300ms="startDate"
                    class="cinema-input w-full px-3.5 py-2.5 text-sm"
                >
            </div>
            <div>
                <label class="block text-xs font-medium text-[var(--text-muted)] mb-2">Fin</label>
                <input
                    type="date"
                    wire:model.live.debounce.300ms="endDate"
                    class="cinema-input w-full px-3.5 py-2.5 text-sm"
                >
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-[var(--text-muted)] mb-2">Categoría</label>
            <select
                wire:model.live.debounce.300ms="category"
                class="cinema-input w-full px-3.5 py-2.5 text-sm"
            >
                <option value="">Todas</option>
                <option value="short_film">Cortometraje</option>
                <option value="feature">Largometraje</option>
                <option value="documentary">Documental</option>
                <option value="animation">Animación</option>
                <option value="horror">Terror</option>
                <option value="sci_fi">Ciencia Ficción</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-[var(--text-muted)] mb-2">Género</label>
            <input
                type="text"
                wire:model.live.debounce.300ms="genre"
                placeholder="Drama, Comedia..."
                class="cinema-input w-full px-3.5 py-2.5 text-sm"
            >
        </div>

        <div>
            <label class="block text-xs font-medium text-[var(--text-muted)] mb-2">País</label>
            <input
                type="text"
                wire:model.live.debounce.300ms="country"
                placeholder="United States"
                class="cinema-input w-full px-3.5 py-2.5 text-sm"
            >
        </div>

        <div class="pt-1">
            <button
                wire:click="search"
                wire:loading.attr="disabled"
                wire:target="search"
                class="cinema-btn w-full py-2.5 text-sm disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <span wire:loading.remove wire:target="search">Buscar</span>
                <span wire:loading wire:target="search">Buscando…</span>
            </button>
        </div>

        <div class="cinema-divider my-5"></div>

        <div>
            <div class="text-xs font-medium text-[var(--text-muted)] mb-3">Rangos rápidos</div>
            <div class="flex flex-wrap gap-2">
                <button
                    wire:click="setQuickRange('next_week')"
                    class="cinema-btn-outline px-3 py-1.5 text-xs"
                >
                    7 días
                </button>
                <button
                    wire:click="setQuickRange('next_month')"
                    class="cinema-btn-outline px-3 py-1.5 text-xs"
                >
                    30 días
                </button>
                <button
                    wire:click="setQuickRange('next_3_months')"
                    class="cinema-btn-outline px-3 py-1.5 text-xs"
                >
                    3 meses
                </button>
                <button
                    wire:click="setQuickRange('next_6_months')"
                    class="cinema-btn-outline px-3 py-1.5 text-xs"
                >
                    6 meses
                </button>
            </div>
        </div>

        <button
            wire:click="clearFilters"
            class="text-xs text-[var(--text-faintest)] hover:text-[var(--text-muted)] transition-colors"
        >
            Limpiar filtros
        </button>
    </div>
</div>
