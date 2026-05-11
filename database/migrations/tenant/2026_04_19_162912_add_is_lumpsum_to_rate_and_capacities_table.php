<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rate_and_capacities', function (Blueprint $table) {
            $table->boolean('is_lumpsum')->default(0)->after('other_charge');
        });
    }

    public function down(): void
    {
        Schema::table('rate_and_capacities', function (Blueprint $table) {
            $table->dropColumn('is_lumpsum');
        });
    }
};