<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

final class VehiclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasStaffAccess();
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->hasStaffAccess();
    }

    public function create(User $user): bool
    {
        return $user->hasStaffAccess();
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->hasStaffAccess();
    }

    public function manageAvailability(User $user, Vehicle $vehicle): bool
    {
        return $user->hasStaffAccess();
    }
}
