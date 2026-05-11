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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reference_id')
                ->comment('ID of the related record in another table');
            $table->tinyInteger('type')
                ->comment('1=meter_deposit, 2=upgrade_meter, 3=meter_deposit_return, 4=advance_payment, 5=meter_insurance, 6=other_income_receipt, 7=share_entry, 8=share_return, 9=non_member_payment, 10=nea_payment, 11=mahasul_receipt');

            $table->decimal('amount', 15, 2);

            $table->tinyInteger('payment_mode')
                ->comment('1=cash, 2=bank');
            $table->string('cheque_no')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedInteger('collector_id')->nullable();

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

        Schema::dropIfExists('payments');
    }
};
