<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if (! $app->runningUnitTests() || DB::getDefaultConnection() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'rentgrid_test') {
    throw new RuntimeException('Concurrent requests require the dedicated PostgreSQL test database.');
}

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
DB::selectOne("select set_config('application_name', ?, false)", [$input['worker']]);

$request = Request::create($input['path'], $input['method'], [], $input['cookies'], [], [
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
], json_encode($input['payload'], JSON_THROW_ON_ERROR));
$response = $kernel->handle($request);

echo json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
], JSON_THROW_ON_ERROR);
