<?php

namespace App\Livewire;

use App\Models\Festival;
use Carbon\Carbon;
use Livewire\Component;

class FestivalCalendar extends Component
{
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?string $category = null;
    public ?string $genre = null;
    public ?string $country = null;

    protected function rules(): array
    {
        return [
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'category' => 'nullable|string',
            'genre' => 'nullable|string',
            'country' => 'nullable|string',
        ];
    }

    public function mount()
    {
        $this->startDate = now()->toDateString();
        $this->endDate = now()->addMonths(3)->toDateString();
    }

    public function search()
    {
        $this->validate();
        $this->dispatch('search-festivals', [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'category' => $this->category,
            'genre' => $this->genre,
            'country' => $this->country,
        ])->to(FestivalResults::class);
    }

    public function clearFilters()
    {
        // Reset every input to its default first so the UI reflects the
        // cleared state immediately — the dispatched search below will then
        // recompute the result list using these defaults.
        $this->reset([
            'category',
            'genre',
            'country',
        ]);
        $this->startDate = now()->toDateString();
        $this->endDate = now()->addMonths(3)->toDateString();
        // Skip validate() — we just set defaults; no user input involved.
        $this->dispatch('search-festivals', [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'category' => $this->category,
            'genre' => $this->genre,
            'country' => $this->country,
        ])->to(FestivalResults::class);
    }

    public function render()
    {
        return view('livewire.festival-calendar');
    }
}
