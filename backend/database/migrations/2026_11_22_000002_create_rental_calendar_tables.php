<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained();
            $table->date('pickup_date');
            $table->date('return_date');
            $table->enum('status', ['confirmed', 'cancelled']);
            $table->timestamps();
            $table->index(['vehicle_id', 'status', 'pickup_date', 'return_date']);
        });

        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_dates_in_order CHECK (pickup_date <= return_date)');

        Schema::create('availability_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
            $table->index(['vehicle_id', 'starts_on', 'ends_on']);
        });

        DB::statement('ALTER TABLE availability_blocks ADD CONSTRAINT availability_blocks_dates_in_order CHECK (starts_on <= ends_on)');
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_blocks');
        Schema::dropIfExists('reservations');
    }
};
