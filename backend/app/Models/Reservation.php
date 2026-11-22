<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = ['pickup_date', 'return_date', 'status'];

    protected function casts(): array
    {
        return [
            'pickup_date' => 'immutable_date',
            'return_date' => 'immutable_date',
            'status' => ReservationStatus::class,
        ];
    }
}
