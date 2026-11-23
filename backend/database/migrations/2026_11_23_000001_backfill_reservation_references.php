<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reservations')->whereNull('reference')->select('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('reservations')->where('id', $row->id)->whereNull('reference')
                    ->update(['reference' => 'RG-'.Str::ulid()]);
            }
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->string('reference', 29)->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('reference', 29)->nullable()->change();
        });
    }
};
