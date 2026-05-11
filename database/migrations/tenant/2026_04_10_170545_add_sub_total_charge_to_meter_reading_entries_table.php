<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meter_reading_entries', function (Blueprint $table) {
            $table->decimal('sub_total_charge', 15, 2)
                  ->default(0.00)
                  ->after('fine_amount');
        });

        DB::table('meter_reading_entries')->update([
            'sub_total_charge' => DB::raw('total_charge - fine_amount')
        ]);
    }

    public function down(): void
    {
        Schema::table('meter_reading_entries', function (Blueprint $table) {
            $table->dropColumn('sub_total_charge');
        });
    }
};