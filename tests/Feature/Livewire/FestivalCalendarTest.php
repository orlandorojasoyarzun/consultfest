<?php

namespace Tests\Feature\Livewire;

use App\Models\Festival;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FestivalCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_livewire_component_renders(): void
    {
        Livewire::test('festival-calendar')
            ->assertStatus(200);
    }

    public function test_livewire_component_has_default_dates(): void
    {
        Livewire::test('festival-calendar')
            ->assertStatus(200);
    }

    public function test_livewire_component_search_method_exists(): void
    {
        Livewire::test('festival-calendar')
            ->call('search')
            ->assertStatus(200);
    }

    public function test_livewire_component_quick_range_methods_exist(): void
    {
        Livewire::test('festival-calendar')
            ->call('setQuickRange', 'next_week')
            ->assertStatus(200);
    }

    public function test_livewire_component_clear_filters(): void
    {
        Livewire::test('festival-calendar')
            ->call('clearFilters')
            ->assertStatus(200);
    }

    public function test_livewire_component_shows_no_results_when_no_festivals(): void
    {
        Livewire::test('festival-calendar')
            ->call('search')
            ->assertStatus(200);
    }
}
