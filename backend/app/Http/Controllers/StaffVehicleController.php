<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveVehicleRequest;
use App\Http\Resources\StaffVehicleResource;
use App\Models\Vehicle;
use App\Services\FleetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StaffVehicleController extends Controller
{
    public function __construct(private readonly FleetService $fleet) {}

    public function index(): JsonResponse
    {
        return StaffVehicleResource::collection(Vehicle::query()->orderBy('display_label')->get())
            ->response()->header('Cache-Control', 'private, no-store');
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        return (new StaffVehicleResource($vehicle))->response()->header('Cache-Control', 'private, no-store');
    }

    public function store(SaveVehicleRequest $request): JsonResponse
    {
        return $this->show($this->fleet->create($request->validated()));
    }

    public function update(SaveVehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $updated = $this->fleet->updateDetails($vehicle->id, $request->safe()->except('original_details'), $request->validated('original_details'));
        if ($updated === null) {
            return response()->json([
                'code' => 'vehicle_details_conflict',
                'message' => 'Another staff member changed these details. Reload to see the latest version before saving again.',
            ], 409, ['Cache-Control' => 'private, no-store']);
        }

        return $this->show($updated);
    }

    public function publication(Request $request, Vehicle $vehicle): JsonResponse
    {
        $request->validate(['is_published' => ['required', 'boolean']]);

        return $this->show($this->fleet->setPublication($vehicle->id, $request->boolean('is_published')));
    }
}
