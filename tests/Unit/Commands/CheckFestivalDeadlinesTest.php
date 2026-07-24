<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\CheckFestivalDeadlines;
use App\Models\Festival;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckFestivalDeadlinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_deadlines_command_exists(): void
    {
        $this->assertTrue(class_exists(CheckFestivalDeadlines::class));
    }

    public function test_check_deadlines_command_has_correct_signature(): void
    {
        $command = new CheckFestivalDeadlines();
        $this->assertEquals('festivals:check-deadlines', $command->getName());
    }

    public function test_check_deadlines_command_accepts_days_option(): void
    {
        $command = new CheckFestivalDeadlines();
        $this->assertTrue($command->getDefinition()->hasOption('days'));
    }

    public function test_check_deadlines_command_executes_successfully(): void
    {
        $this->artisan('festivals:check-deadlines')
            ->assertSuccessful();
    }

    public function test_check_deadlines_command_with_specific_days(): void
    {
        $this->artisan('festivals:check-deadlines', ['--days' => 7])
            ->assertSuccessful();
    }

    public function test_check_deadlines_finds_festival_in_7_days(): void
    {
        $festival = Festival::factory()->create([
            'deadline' => Carbon::now()->addDays(7),
            'accepting_submissions' => true,
        ]);

        $this->artisan('festivals:check-deadlines', ['--days' => 7])
            ->assertSuccessful();
    }

    public function test_check_deadlines_finds_festival_in_14_days(): void
    {
        $festival = Festival::factory()->create([
            'deadline' => Carbon::now()->addDays(14),
            'accepting_submissions' => true,
        ]);

        $this->artisan('festivals:check-deadlines', ['--days' => 14])
            ->assertSuccessful();
    }

    public function test_check_deadlines_with_no_matching_festivals(): void
    {
        Festival::factory()->create([
            'deadline' => Carbon::now()->addDays(100),
            'accepting_submissions' => true,
        ]);

        $this->artisan('festivals:check-deadlines', ['--days' => 7])
            ->assertSuccessful();
    }

    public function test_check_deadlines_ignores_non_accepting_festivals(): void
    {
        Festival::factory()->create([
            'deadline' => Carbon::now()->addDays(7),
            'accepting_submissions' => false,
        ]);

        $this->artisan('festivals:check-deadlines', ['--days' => 7])
            ->assertSuccessful();
    }
}
