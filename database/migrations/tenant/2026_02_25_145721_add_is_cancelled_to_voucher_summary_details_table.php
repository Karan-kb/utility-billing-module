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
        Schema::table('voucher_summary_details', function (Blueprint $table) {
            $table->boolean('is_cancelled')->default(0)->after('member_entry_id')
                ->comment('Indicates if the voucher detail has been cancelled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voucher_summary_details', function (Blueprint $table) {
            $table->dropColumn('is_cancelled');
        });
    }
};