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
        Schema::create('other_income_receipts', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('is_cancel')->default(0);
            $table->unsignedBigInteger('fiscal_year_id');
            $table->string('date_in_bs', 10);
            $table->date('date_in_ad');

            $table->unsignedInteger('meter_issue_id');
            $table->string('voucher_no', 20)->index();
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->timestamps();
            $table->auditFields();
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('other_income_receipts');
    }
};
