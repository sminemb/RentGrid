<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class FleetSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Demo 01', 'Demo 02'] as $label) {
            Vehicle::query()->firstOrCreate(
                ['display_label' => $label],
                ['make' => 'Toyota', 'model' => 'Vios', 'is_published' => true],
            );
        }
    }
}
