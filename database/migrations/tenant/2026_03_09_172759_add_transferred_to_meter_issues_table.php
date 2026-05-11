<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meter_issues', function (Blueprint $table) {
            $table->unsignedBigInteger('transferred_to')->after('id')->nullable()
                ->comment('Reference to meter_issues table id for transfer');

            $table->foreign('transferred_to')
                ->references('id')
                ->on('meter_issues')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('meter_issues', function (Blueprint $table) {
            $table->dropForeign(['transferred_to']);
            $table->dropColumn('transferred_to');
        });
    }
};