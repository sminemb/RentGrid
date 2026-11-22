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
        return $this->show($this->fleet->saveDetails(new Vehicle, $request->validated()));
    }

    public function update(SaveVehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        return $this->show($this->fleet->saveDetails($vehicle, $request->validated()));
    }

    public function publication(Request $request, Vehicle $vehicle): JsonResponse
    {
        $request->validate(['is_published' => ['required', 'boolean']]);

        return $this->show($this->fleet->setPublication($vehicle->id, $request->boolean('is_published')));
    }
}
