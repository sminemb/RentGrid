<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->json('vehicle_details')->nullable();
        });

        // Older guest bookings only have the current listing to copy.
        DB::statement(<<<'SQL'
            UPDATE reservations
            SET vehicle_details = json_build_object(
                'display_label', vehicles.display_label,
                'make', vehicles.make,
                'model', vehicles.model
            )
            FROM vehicles
            WHERE reservations.vehicle_id = vehicles.id AND reservations.reference IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('vehicle_details');
        });
    }
};
