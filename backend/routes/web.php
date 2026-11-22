<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingQuoteController;
use App\Http\Controllers\StaffSessionController;
use Illuminate\Support\Facades\Route;

Route::post('/api/booking-quotes', BookingQuoteController::class);
Route::post('/api/bookings', [BookingController::class, 'store']);
Route::get('/api/bookings/{reference}', [BookingController::class, 'show']);

Route::prefix('api/staff')->group(function () {
    Route::post('/sign-in', [StaffSessionController::class, 'store'])->middleware('throttle:staff-sign-in');

    Route::middleware('auth')->group(function () {
        Route::get('/session', [StaffSessionController::class, 'show'])->can('access-staff');
        Route::post('/sign-out', [StaffSessionController::class, 'destroy']);
    });
});

Route::get('/api/session', function () {
    return response()->json(['ready' => true], headers: [
        'Cache-Control' => 'private, no-store',
    ]);
});
