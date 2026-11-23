<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingQuoteController;
use App\Http\Controllers\StaffAvailabilityController;
use App\Http\Controllers\StaffReservationController;
use App\Http\Controllers\StaffSessionController;
use App\Http\Controllers\StaffVehicleController;
use App\Models\Reservation;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Route;

Route::post('/api/booking-quotes', BookingQuoteController::class);
Route::post('/api/bookings', [BookingController::class, 'store'])->middleware('throttle:guest-booking');
Route::post('/api/bookings/recover', [BookingController::class, 'recover']);
Route::get('/api/bookings/{reference}', [BookingController::class, 'show']);

Route::prefix('api/staff')->group(function () {
    Route::post('/sign-in', [StaffSessionController::class, 'store'])->middleware('throttle:staff-sign-in');

    Route::middleware('auth')->group(function () {
        Route::get('/session', [StaffSessionController::class, 'show'])->can('access-staff');
        Route::post('/sign-out', [StaffSessionController::class, 'destroy']);
        Route::get('/reservations/{reference}', [StaffReservationController::class, 'show'])->can('lookup', Reservation::class);
        Route::post('/reservations/{reference}/cancel', [StaffReservationController::class, 'cancel'])->can('cancel', Reservation::class);
        Route::get('/vehicles', [StaffVehicleController::class, 'index'])->can('viewAny', Vehicle::class);
        Route::post('/vehicles', [StaffVehicleController::class, 'store'])->can('create', Vehicle::class);

        Route::prefix('vehicles/{vehicle}')->where(['vehicle' => '[0-9]+'])->group(function () {
            Route::get('/', [StaffVehicleController::class, 'show'])->can('view', 'vehicle');
            Route::put('/', [StaffVehicleController::class, 'update'])->can('update', 'vehicle');
            Route::put('/publication', [StaffVehicleController::class, 'publication'])->can('update', 'vehicle');
            Route::get('/availability', [StaffAvailabilityController::class, 'index'])->can('view', 'vehicle');
            Route::post('/availability/blocks', [StaffAvailabilityController::class, 'store'])->can('manageAvailability', 'vehicle');
            Route::delete('/availability/blocks/{block}', [StaffAvailabilityController::class, 'destroy'])
                ->whereNumber('block')->can('manageAvailability', 'vehicle');
        });
    });
});

Route::get('/api/session', function () {
    return response()->json(['ready' => true], headers: [
        'Cache-Control' => 'private, no-store',
    ]);
});
