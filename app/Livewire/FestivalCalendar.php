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
        $this->startDate = now()->toDateString();
        $this->endDate = now()->addMonths(3)->toDateString();
        $this->category = null;
        $this->genre = null;
        $this->country = null;
        $this->search();
    }

    public function render()
    {
        return view('livewire.festival-calendar');
    }
}
