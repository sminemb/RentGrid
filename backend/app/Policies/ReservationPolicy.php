<?php

namespace App\Policies;

use App\Models\User;

final class ReservationPolicy
{
    public function lookup(User $user): bool
    {
        return $user->hasStaffAccess();
    }

    public function cancel(User $user): bool
    {
        return $user->hasStaffAccess();
    }
}
