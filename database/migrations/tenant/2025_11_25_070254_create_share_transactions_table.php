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
        Schema::create('share_transactions', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('is_cancel')->default(0);
            $table->unsignedBigInteger('fiscal_year_id');

            $table->string('date_in_bs', 10);
            $table->date('date_in_ad');
            $table->unsignedBigInteger('member_entry_id');
            $table->enum('share_type', ['electricity'])->default('electricity');
            $table->string('share_certificate_no', 50);
            $table->unsignedInteger('share_quantity');

            $table->unsignedInteger('share_value')->default(100);
            $table->string('voucher_no', 20)->nullable()->index();
            $table->tinyInteger('transaction_type')->comment('1 = share entry, 2 = share return, 3 = opening share');
            $table->decimal('service_charge', 15, 2)->default(0.00);
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
        Schema::dropIfExists('share_transactions');
    }
};
