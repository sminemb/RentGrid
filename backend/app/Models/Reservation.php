<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'pickup_instructions',
        'cancellation_terms',
        'currency',
        'amount_due_minor',
        'booking_attempt_key',
        'guest_session_key',
    ];

    protected $hidden = ['guest_name', 'guest_email', 'guest_phone', 'booking_attempt_key', 'guest_session_key'];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

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
