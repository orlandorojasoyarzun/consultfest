<?php

namespace Tests\Unit\Models;

use App\Models\Festival;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FestivalTest extends TestCase
{
    use RefreshDatabase;

    public function test_festival_can_be_created(): void
    {
        $festival = Festival::create([
            'api_id' => 12345,
            'name' => 'Sundance Film Festival',
            'category' => 'feature',
            'country' => 'United States',
            'deadline' => Carbon::parse('2026-09-15'),
            'submission_fee' => 85.00,
            'accepting_submissions' => true,
            'festival_score' => 95,
        ]);

        $this->assertDatabaseHas('festivals', [
            'api_id' => 12345,
            'name' => 'Sundance Film Festival',
        ]);
    }

    public function test_festival_deadline_is_cast_to_date(): void
    {
        $festival = Festival::factory()->create([
            'deadline' => '2026-09-15',
        ]);

        $this->assertInstanceOf(Carbon::class, $festival->deadline);
    }

    public function test_festival_opening_date_is_cast_to_date(): void
    {
        $festival = Festival::factory()->create([
            'opening_date' => '2026-01-01',
        ]);

        $this->assertInstanceOf(Carbon::class, $festival->opening_date);
    }

    public function test_festival_details_is_cast_to_array(): void
    {
        $details = ['genres' => ['drama', 'comedy'], 'description' => 'Test festival'];
        $festival = Festival::factory()->create([
            'details' => $details,
        ]);

        $this->assertIsArray($festival->details);
    }

    public function test_festival_accepting_submissions_is_cast_to_boolean(): void
    {
        $festival = Festival::factory()->create([
            'accepting_submissions' => 1,
        ]);

        $this->assertIsBool($festival->accepting_submissions);
        $this->assertTrue($festival->accepting_submissions);
    }

    public function test_festival_can_have_null_deadline(): void
    {
        $festival = Festival::factory()->create(['deadline' => null]);

        $this->assertNull($festival->deadline);
    }

    public function test_festival_can_have_null_opening_date(): void
    {
        $festival = Festival::factory()->create(['opening_date' => null]);

        $this->assertNull($festival->opening_date);
    }

    public function test_festival_has_subscriptions_relationship(): void
    {
        $festival = Festival::factory()->create();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $festival->subscriptions);
    }
}
