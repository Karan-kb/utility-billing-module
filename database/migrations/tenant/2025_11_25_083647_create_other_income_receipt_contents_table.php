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
        Schema::create('other_income_receipt_contents', function (Blueprint $table) {
           $table->id(); // integer UNSIGNED AUTO_INCREMENT

            $table->unsignedBigInteger('other_income_receipt_id'); // FK to other_income_receipts
            $table->unsignedBigInteger('account_head_id');   // FK to other_income_setups

            $table->decimal('charge_amount', 15, 2)->default(0.00);
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

        Schema::dropIfExists('other_income_receipt_contents');
    }
};
