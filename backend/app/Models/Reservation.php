<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'pickup_date',
        'return_date',
        'status',
        'reference',
        'guest_name',
        'guest_email',
        'guest_phone',
        'pickup_location',
        'currency',
        'amount_due_minor',
    ];

    protected $hidden = ['guest_name', 'guest_email', 'guest_phone'];

    protected function casts(): array
    {
        return [
            'pickup_date' => 'immutable_date',
            'return_date' => 'immutable_date',
            'status' => ReservationStatus::class,
            'amount_due_minor' => 'integer',
        ];
    }
}
