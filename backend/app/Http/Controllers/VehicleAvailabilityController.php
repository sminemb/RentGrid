<?php

namespace App\Http\Controllers;

use App\Http\Resources\VehicleResource;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleAvailabilityController extends Controller
{
    public function __invoke(Request $request, AvailabilityService $availability): JsonResponse
    {
        $dates = $request->validate([
            'pickup_date' => ['required', 'string'],
            'return_date' => ['required', 'string'],
        ]);

        $vehicles = $availability->availableVehicles($dates['pickup_date'], $dates['return_date'])
            ->orderBy('display_label')
            ->get();

        return VehicleResource::collection($vehicles)->response()->header('Cache-Control', 'no-store');
    }
}
