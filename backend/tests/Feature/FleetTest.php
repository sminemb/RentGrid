<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use Database\Seeders\FleetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetTest extends TestCase
{
    use RefreshDatabase;

    public function test_synthetic_fleet_distinguishes_identical_models(): void
    {
        $this->seed(FleetSeeder::class);
        $this->seed(FleetSeeder::class);

        [$first, $second] = Vehicle::query()->orderBy('id')->get()->all();

        $this->assertDatabaseCount('vehicles', 2);
        $this->assertSame($first->make, $second->make);
        $this->assertSame($first->model, $second->model);
        $this->assertNotSame($first->id, $second->id);
        $this->assertNotSame($first->display_label, $second->display_label);
        $this->assertTrue($first->is_published);
        $this->assertTrue($second->is_published);
    }

    public function test_new_vehicles_default_to_unpublished(): void
    {
        $vehicle = Vehicle::query()->create([
            'display_label' => 'Demo 03',
            'make' => 'Toyota',
            'model' => 'Vios',
        ]);

        $this->assertFalse($vehicle->fresh()->is_published);

        $vehicle->update(['is_published' => true]);

        $this->assertTrue($vehicle->fresh()->is_published);
    }
}
