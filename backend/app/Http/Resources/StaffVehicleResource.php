<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

final class StaffVehicleResource extends VehicleResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + ['is_published' => $this->is_published];
    }
}
