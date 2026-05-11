<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mahasul_receipts', function (Blueprint $table) {

            $table->id();
            $table->tinyInteger('is_cancel')->default(0);
            $table->unsignedBigInteger('fiscal_year_id');

            $table->char('uuid', 36)->nullable();
            $table->tinyInteger('is_synced')->default(0);

            $table->string('date_in_bs', 10);
            $table->date('date_in_ad');

            $table->unsignedBigInteger('meter_issue_id');
            $table->string('voucher_no', 20)->index();
            $table->string('dummy_voucher', 20)->nullable();

            $table->unsignedBigInteger('meter_reading_entry_id');

            $table->decimal('unit_amount', 15, 2)->default(0.00);
            $table->decimal('discount_amount', 15, 2)->default(0.00);
            $table->decimal('rebate_amount', 15, 2)->default(0.00);
            $table->decimal('fine_amount', 15, 2)->default(0.00);

            $table->decimal('total_due_amount', 15, 2)->default(0.00);
            $table->decimal('advance_payment', 15, 2)->default(0.00);
            $table->decimal('demand_charge', 15, 2)->default(0.00);
            $table->decimal('subsidy_charge', 15, 2)->default(0.00);
            $table->decimal('service_charge', 15, 2)->default(0.00);
            $table->decimal('other_charge', 15, 2)->default(0.00);
            $table->decimal('black_list_charge', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->decimal('paid_amount', 15, 2)->default(0.00);

            $table->auditFields();
            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys

        });
    }

    public function down(): void
    {
      

        Schema::dropIfExists('mahasul_receipts');
    }
};
