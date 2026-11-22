<?php

use Illuminate\Support\Facades\Route;

Route::get('/api/session', function () {
    return response()->json(['ready' => true], headers: [
        'Cache-Control' => 'private, no-store',
    ]);
});
