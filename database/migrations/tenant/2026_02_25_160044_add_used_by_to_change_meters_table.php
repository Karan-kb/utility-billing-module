<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_meters', function (Blueprint $table) {
            $table->unsignedBigInteger('used_by')
                  ->nullable()
                  ->after('meter_start_no')
                  ->comment('References id of meter_reading_entries table');

            // Foreign key constraint with cascade on delete
            $table->foreign('used_by')
                  ->references('id')
                  ->on('meter_reading_entries')
                  ->onDelete('cascade');
      
        });
    }

    public function down(): void
    {
        Schema::table('change_meters', function (Blueprint $table) {
              $table->dropForeign(['used_by']);
            $table->dropColumn('used_by');
        });
    }
};