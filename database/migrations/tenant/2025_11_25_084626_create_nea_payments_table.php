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
        if (config('tenant.software_type') === 1) {
            return;
        }
        Schema::create('nea_payments', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('is_cancel')->default(0);
            $table->unsignedBigInteger('fiscal_year_id');
            $table->string('date_in_bs', 10);
            $table->date('date_in_ad');
            $table->string('voucher_no', 20);
            $table->tinyInteger('month');
            $table->unsignedBigInteger('transformer_id');
            $table->decimal('due_amount', 15, 2)->default(0.00);
            $table->decimal('fine_amount', 15, 2)->default(0.00);
            $table->decimal('rebate_amount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->decimal('paid_amount', 15, 2)->default(0.00);
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
        if (config('tenant.software_type') === 1) {
            return;
        }
        
        Schema::dropIfExists('nea_payments');
    }
};
