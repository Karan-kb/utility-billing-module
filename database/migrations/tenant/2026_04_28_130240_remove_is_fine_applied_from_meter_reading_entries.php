<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
/**
* Run the migrations (REVERT CHANGES).
*/
public function up(): void
{
    Schema::table('meter_reading_entries', function (Blueprint $table) {
            if (Schema::hasColumn('meter_reading_entries', 'is_fine_applied')) {
            $table->dropColumn('is_fine_applied');
            }
    });
}

/**
* Reverse the migrations (RESTORE COLUMN AGAIN).
*/
public function down(): void
{
    Schema::table('meter_reading_entries', function (Blueprint $table) {
            $table->boolean('is_fine_applied')
            ->default(false)
            ->after('fine_amount');
            });
    }
};