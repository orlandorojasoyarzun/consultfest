<?php

namespace App\Livewire;

use App\Models\Festival;
use Livewire\Component;

class FestivalResults extends Component
{
    public $festivals = [];
    public int $totalCount = 0;
    public bool $isLoading = false;

    protected $listeners = [
        'search-festivals' => 'search',
    ];

    public function mount()
    {
        $this->search([
            'startDate' => now()->toDateString(),
            'endDate' => now()->addMonths(3)->toDateString(),
        ]);
    }

    public function search(array $filters)
    {
        $this->isLoading = true;

        $query = Festival::query();

        $startDate = $filters['startDate'] ?? null;
        $endDate = $filters['endDate'] ?? null;

        if ($startDate && $endDate) {
            $query->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('deadline', [$startDate, $endDate])
                  ->orWhereBetween('opening_date', [$startDate, $endDate]);
            });
        } elseif ($startDate) {
            $query->where(function ($q) use ($startDate) {
                $q->where('deadline', '>=', $startDate)
                  ->orWhere('opening_date', '>=', $startDate);
            });
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['genre'])) {
            $query->where('details->genres', 'LIKE', '%' . $filters['genre'] . '%');
        }

        if (!empty($filters['country'])) {
            $query->where('country', 'LIKE', '%' . $filters['country'] . '%');
        }

        $query->orderBy('deadline');

        $this->festivals = $query->limit(50)->get();
        $this->totalCount = $this->festivals->count();

        $this->isLoading = false;
    }

    public function render()
    {
        return view('livewire.festival-results');
    }
}
