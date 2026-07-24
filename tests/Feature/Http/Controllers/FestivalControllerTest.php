<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Festival;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FestivalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_festivals_index_page_loads(): void
    {
        $this->withoutMiddleware(\Illuminate\Auth\Middleware\Authenticate::class);

        Festival::factory()->count(3)->create();

        $response = $this->get('/festivals');
        $response->assertStatus(200);
    }

    public function test_festivals_show_page_loads(): void
    {
        $this->withoutMiddleware(\Illuminate\Auth\Middleware\Authenticate::class);

        $festival = Festival::factory()->create();
        $response = $this->get("/festivals/{$festival->id}");
        $response->assertStatus(200);
    }
}
