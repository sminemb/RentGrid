<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->char('booking_attempt_key', 64)->nullable()->unique();
            $table->char('guest_session_key', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique(['booking_attempt_key']);
            $table->dropColumn(['booking_attempt_key', 'guest_session_key']);
        });
    }
};
