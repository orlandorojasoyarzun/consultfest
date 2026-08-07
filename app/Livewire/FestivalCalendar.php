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
    /**
     * Which date column to filter the range against.
     * Empty string = match either opening_date or deadline (legacy behaviour).
     * 'opening_date' = filter only by opening date.
     * 'deadline' = filter only by deadline.
     */
    public string $dateField = '';

    protected function rules(): array
    {
        return [
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date|after_or_equal:startDate',
            'category' => 'nullable|string',
            'genre' => 'nullable|string',
            'country' => 'nullable|string',
            'dateField' => 'nullable|in:,opening_date,deadline',
        ];
    }

    public function mount()
    {
        $this->startDate = now()->toDateString();
        $this->endDate = now()->addMonths(3)->toDateString();
        // No default dateField — leave it null so the results component
        // falls back to "match either opening_date or deadline in range",
        // which is the original behaviour users expect.
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
            'dateField' => $this->dateField,
        ])->to(FestivalResults::class);
    }

    public function setQuickRange(string $range)
    {
        $now = now();

        switch ($range) {
            case 'next_week':
                $this->startDate = $now->toDateString();
                $this->endDate = $now->addWeek()->toDateString();
                break;
            case 'next_month':
                $this->startDate = $now->toDateString();
                $this->endDate = $now->addMonth()->toDateString();
                break;
            case 'next_3_months':
                $this->startDate = $now->toDateString();
                $this->endDate = $now->addMonths(3)->toDateString();
                break;
            case 'next_6_months':
                $this->startDate = $now->toDateString();
                $this->endDate = $now->addMonths(6)->toDateString();
                break;
        }

        $this->search();
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
            'dateField',
        ]);
        $this->startDate = now()->toDateString();
        $this->endDate = now()->addMonths(3)->toDateString();
        $this->dateField = ''; // legacy: match either opening_date or deadline
        // Skip validate() — we just set defaults; no user input involved.
        $this->dispatch('search-festivals', [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'category' => $this->category,
            'genre' => $this->genre,
            'country' => $this->country,
            'dateField' => $this->dateField,
        ])->to(FestivalResults::class);
    }

    public function render()
    {
        return view('livewire.festival-calendar');
    }
}
