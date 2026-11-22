<?php

use App\Http\Controllers\VehicleAvailabilityController;
use Illuminate\Support\Facades\Route;

Route::get('/vehicles/availability', VehicleAvailabilityController::class);
