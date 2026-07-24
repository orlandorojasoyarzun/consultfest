<div>
    <div class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs text-[#a3a3a3] mb-1.5 uppercase tracking-wider">Inicio</label>
                <input
                    type="date"
                    wire:model.live.debounce.300ms="startDate"
                    class="cinema-input w-full px-3 py-2.5 text-sm"
                >
            </div>
            <div>
                <label class="block text-xs text-[#a3a3a3] mb-1.5 uppercase tracking-wider">Fin</label>
                <input
                    type="date"
                    wire:model.live.debounce.300ms="endDate"
                    class="cinema-input w-full px-3 py-2.5 text-sm"
                >
            </div>
        </div>

        <div>
            <label class="block text-xs text-[#a3a3a3] mb-1.5 uppercase tracking-wider">Categoría</label>
            <select
                wire:model.live.debounce.300ms="category"
                class="cinema-input w-full px-3 py-2.5 text-sm"
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
            <label class="block text-xs text-[#a3a3a3] mb-1.5 uppercase tracking-wider">Género</label>
            <input
                type="text"
                wire:model.live.debounce.300ms="genre"
                placeholder="Drama, Comedia..."
                class="cinema-input w-full px-3 py-2.5 text-sm"
            >
        </div>

        <div>
            <label class="block text-xs text-[#a3a3a3] mb-1.5 uppercase tracking-wider">País</label>
            <input
                type="text"
                wire:model.live.debounce.300ms="country"
                placeholder="United States"
                class="cinema-input w-full px-3 py-2.5 text-sm"
            >
        </div>

        <div class="pt-2">
            <button
                wire:click="search"
                class="cinema-btn w-full py-2.5 text-sm"
            >
                Buscar Festivales
            </button>
        </div>

        <div class="cinema-divider my-4"></div>

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

        <button
            wire:click="clearFilters"
            class="text-xs text-[#a3a3a3] hover:text-[#d4a853] transition-colors"
        >
            Limpiar filtros
        </button>
    </div>
</div>
