<?php

use App\Models\Vehicle;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->runningUnitTests() || DB::getDefaultConnection() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'rentgrid_test') {
    throw new RuntimeException('Browser fixtures require the dedicated PostgreSQL test database.');
}

$run = $argv[1] ?? '';
if (! preg_match('/\A[0-9a-f]{12}\z/', $run)) {
    throw new InvalidArgumentException('Expected a browser test run ID.');
}

$labels = ["Recovery {$run} A", "Recovery {$run} B"];
if (($argv[2] ?? '') === 'clean') {
    DB::transaction(function () use ($labels): void {
        foreach (Vehicle::query()->whereIn('display_label', $labels)->get() as $vehicle) {
            $vehicle->reservations()->delete();
            $vehicle->delete();
        }
    });

    return;
}

$vehicles = DB::transaction(fn () => array_map(fn ($label) => Vehicle::factory()->create([
    'display_label' => $label, 'is_published' => true,
])->only(['id', 'display_label']), $labels));

echo json_encode([
    'date' => today(config('rentgrid.business_timezone'))->toDateString(),
    'vehicles' => $vehicles,
], JSON_THROW_ON_ERROR);
