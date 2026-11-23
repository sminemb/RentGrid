<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use Database\Seeders\FleetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetTest extends TestCase
{
    use RefreshDatabase;

    public function test_synthetic_fleet_seeds_one_hundred_published_vehicles_without_duplicates(): void
    {
        $this->seed(FleetSeeder::class);
        $this->seed(FleetSeeder::class);

        [$first, $second] = Vehicle::query()->orderBy('id')->limit(2)->get()->all();

        $this->assertDatabaseCount('vehicles', 100);
        $this->assertSame(100, Vehicle::query()->where('is_published', true)->count());
        $this->assertSame($first->make, $second->make);
        $this->assertSame($first->model, $second->model);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('Vios 101 · Silver', $first->display_label);
        $this->assertSame('Vios 102 · White', $second->display_label);
        $this->assertSame(0, Vehicle::query()->whereLike('display_label', 'Demo %')->count());
        $this->assertTrue($first->is_published);
        $this->assertTrue($second->is_published);
    }

    public function test_seeder_renames_existing_fleet_vehicles_in_place(): void
    {
        $vehicle = Vehicle::query()->create([
            'display_label' => 'Demo 01',
            'make' => 'Toyota',
            'model' => 'Vios',
            'is_published' => false,
        ]);

        $this->seed(FleetSeeder::class);
        $this->seed(FleetSeeder::class);

        $this->assertDatabaseCount('vehicles', 100);
        $this->assertSame('Vios 101 · Silver', $vehicle->refresh()->display_label);
        $this->assertFalse($vehicle->is_published);
    }

    public function test_new_vehicles_default_to_unpublished(): void
    {
        $vehicle = Vehicle::query()->create([
            'display_label' => 'Vios 103 · Gray',
            'make' => 'Toyota',
            'model' => 'Vios',
        ]);

        $this->assertFalse($vehicle->fresh()->is_published);

        $vehicle->update(['is_published' => true]);

        $this->assertTrue($vehicle->fresh()->is_published);
    }
}
