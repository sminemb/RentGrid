<?php

use App\Models\User;
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

$adminEmail = "onboarding-admin-{$run}@example.test";
$staffEmail = "onboarding-staff-{$run}@example.test";

if (($argv[2] ?? '') === 'clean') {
    DB::transaction(function () use ($adminEmail, $staffEmail): void {
        $accounts = User::query()->whereIn('email', [$adminEmail, $staffEmail]);
        DB::table('sessions')->whereIn('user_id', $accounts->pluck('id'))->delete();
        $accounts->delete();
    });

    return;
}

$password = bin2hex(random_bytes(16));
$admin = User::factory()->create([
    'name' => 'Browser Test Administrator',
    'email' => $adminEmail,
    'password' => $password,
    'is_staff' => true,
    'is_staff_admin' => true,
]);

echo json_encode([
    'admin' => $admin->only(['name', 'email']) + ['password' => $password],
    'staffEmail' => $staffEmail,
], JSON_THROW_ON_ERROR);
