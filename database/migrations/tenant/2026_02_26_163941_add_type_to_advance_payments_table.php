<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('advance_payments', function (Blueprint $table) {
            // Make voucher_no nullable
            $table->string('voucher_no', 20)->nullable()->change();

            // Add 'type' column only if it doesn't exist
            if (!Schema::hasColumn('advance_payments', 'type')) {
                $table->tinyInteger('type')
                    ->default(0)
                    ->after('amount')
                    ->comment('0 = normal advance, 1 = advance from opening');
            }
        });
    }

    public function down(): void
    {
        Schema::table('advance_payments', function (Blueprint $table) {
            // Revert voucher_no to non-nullable
            $table->string('voucher_no', 20)->nullable(false)->change();

            // Drop 'type' column if exists
            if (Schema::hasColumn('advance_payments', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};