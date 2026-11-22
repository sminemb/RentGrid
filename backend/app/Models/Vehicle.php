<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected $fillable = ['display_label', 'make', 'model', 'is_published'];

    protected $attributes = ['is_published' => false];

    /** @return array{display_label: string, make: string, model: string} */
    public function details(): array
    {
        return [
            'display_label' => $this->display_label,
            'make' => $this->make,
            'model' => $this->model,
        ];
    }

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class);
    }
}
