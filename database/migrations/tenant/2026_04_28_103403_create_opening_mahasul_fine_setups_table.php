<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_mahasul_fine_setups', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->default(1)->primary();
            $table->boolean('is_fine_applied')->default(false);
            $table->timestamps();
        });

        DB::table('opening_mahasul_fine_setups')->insert([
            'id' => 1,
            'is_fine_applied' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_mahasul_fine_setups');
    }
};