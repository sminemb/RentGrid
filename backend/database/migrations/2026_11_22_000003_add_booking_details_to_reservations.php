<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Existing calendar reservations predate customer booking details.
            $table->string('reference', 29)->nullable()->unique();
            $table->string('guest_name', 120)->nullable();
            $table->string('guest_email', 254)->nullable();
            $table->string('guest_phone', 40)->nullable();
            $table->string('pickup_location')->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedBigInteger('amount_due_minor')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'guest_name', 'guest_email', 'guest_phone', 'pickup_location', 'currency', 'amount_due_minor']);
        });
    }
};
