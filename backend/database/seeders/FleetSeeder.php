<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class FleetSeeder extends Seeder
{
    private const MODELS = [
        ['Toyota', 'Vios'],
        ['Honda', 'City'],
        ['Mitsubishi', 'Xpander'],
        ['Suzuki', 'Ertiga'],
        ['Toyota', 'Innova'],
    ];

    private const COLORS = ['Silver', 'White', 'Gray', 'Black'];

    public function run(): void
    {
        $unitsPerModel = 20;
        for ($unit = 0; $unit < count(self::MODELS) * $unitsPerModel; $unit++) {
            $modelIndex = intdiv($unit, $unitsPerModel);
            [$make, $model] = self::MODELS[$modelIndex];
            $unitNumber = ($modelIndex + 1) * 100 + ($unit % $unitsPerModel) + 1;
            $color = self::COLORS[$unit % count(self::COLORS)];
            $label = "{$model} {$unitNumber} · {$color}";

            if (Vehicle::query()->where('display_label', $label)->exists()) {
                continue;
            }

            $renamed = Vehicle::query()->where([
                'display_label' => sprintf('Demo %02d', $unit + 1),
                'make' => $make,
                'model' => $model,
            ])->update(['display_label' => $label]);

            if ($renamed > 0) {
                continue;
            }

            Vehicle::query()->firstOrCreate(
                ['display_label' => $label],
                ['make' => $make, 'model' => $model, 'is_published' => true],
            );
        }
    }
}
