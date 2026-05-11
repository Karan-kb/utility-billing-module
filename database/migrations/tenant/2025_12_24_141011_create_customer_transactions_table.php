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
        Schema::create('customer_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_entry_id');

            $table->date('transaction_date');
            $table->tinyInteger('transaction_type')->comment('1=meter_read, 2=mahasul_receipt,3=meter,4=share ,5=Opening Mahasul, 6 = Advance');
            $table->tinyInteger('charge_type')->comment('1=demand_charge, 2=service_charge, 3=fine_charge,
            4=subsidy_charge,5=other_charge,6=energy_charge,7=rebate,8=discount 9 = advance charge 10 = disable discount');
            $table->decimal('amount', 15, 2)->unsigned();
            $table->decimal('due', 15, 2)->nullable()->default(0.00);
            $table->enum('direction', ['DR', 'CR']);
            $table->unsignedBigInteger('reference_id')->nullable()->comment('ID from bills or payments table');
            $table->unsignedBigInteger('receipt_id')->nullable();
            // $table->text('remarks')->nullable()->comment('1 = bill, 2 = fine');
            $table->timestamps();
            $table->index(['reference_id', 'transaction_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_transactions');
    }
};
