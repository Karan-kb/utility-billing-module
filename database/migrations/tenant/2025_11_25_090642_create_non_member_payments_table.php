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
        Schema::create('non_member_payments', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('is_cancel')->default(0);
            $table->unsignedBigInteger('fiscal_year_id');
            $table->string('date_in_bs', 10);
            $table->date('date_in_ad');
            $table->string('voucher_no', 20)->index();
            $table->string('customer_name_en', 100)->index();
            $table->string('customer_name_np', 255)->index();
            $table->text('address')->nullable();
            $table->string('mobile_no', 20)->nullable();
            $table->unsignedInteger('consumption_unit');
            $table->decimal('demand_charge', 15, 2)->default(0.00);
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->softDeletes();
            $table->auditFields();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       
        Schema::dropIfExists('non_member_payments');
    }
};
