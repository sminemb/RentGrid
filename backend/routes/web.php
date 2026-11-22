<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingQuoteController;
use App\Http\Controllers\VehicleAvailabilityController;
use Illuminate\Support\Facades\Route;

Route::get('/api/vehicles/availability', VehicleAvailabilityController::class);
Route::post('/api/booking-quotes', BookingQuoteController::class);
Route::post('/api/bookings', [BookingController::class, 'store']);

Route::get('/api/session', function () {
    return response()->json(['ready' => true], headers: [
        'Cache-Control' => 'private, no-store',
    ]);
});
