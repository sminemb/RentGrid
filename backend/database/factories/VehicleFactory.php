<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'display_label' => fake()->unique()->bothify('Demo ##??'),
            'make' => 'Toyota',
            'model' => 'Vios',
        ];
    }
}
