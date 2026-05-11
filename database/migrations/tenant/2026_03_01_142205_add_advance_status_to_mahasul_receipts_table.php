<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mahasul_receipts', function (Blueprint $table) {
            $table->tinyInteger('advance_status')
                ->default(0)
                ->comment('0 = no advance, 1 = full, 2 = partial')
                ->after('paid_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mahasul_receipts', function (Blueprint $table) {
            $table->dropColumn('advance_status');
        });
    }
};