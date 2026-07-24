<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\SyncFestivals;
use App\Services\FestivalApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncFestivalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_festivals_command_exists(): void
    {
        $this->assertTrue(class_exists(SyncFestivals::class));
    }

    public function test_sync_festivals_command_has_correct_signature(): void
    {
        $command = new SyncFestivals();
        $this->assertEquals('festivals:sync', $command->getName());
    }

    public function test_sync_festivals_command_accepts_details_option(): void
    {
        $command = new SyncFestivals();
        $this->assertTrue($command->getDefinition()->hasOption('details'));
    }

    public function test_sync_festivals_command_can_be_executed(): void
    {
        $this->artisan('festivals:sync')
            ->assertSuccessful();
    }

    public function test_sync_festivals_command_with_details_option(): void
    {
        $this->artisan('festivals:sync', ['--details' => true])
            ->assertSuccessful();
    }
}
