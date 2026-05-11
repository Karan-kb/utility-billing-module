<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('expense_and_receivable_trackers', function (Blueprint $table) {
        // 0 = Expense, 1 = Receivable
        $table->tinyInteger('type')
              ->default(0)
              ->after('fiscal_year_id')
              ->comment('0=Expense, 1=Receivable');
    });
}

public function down(): void
{
    Schema::table('expense_and_receivable_trackers', function (Blueprint $table) {
        $table->dropColumn('type');
    });
}
};
