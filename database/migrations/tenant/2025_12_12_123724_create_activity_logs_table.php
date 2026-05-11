<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->nullable();
            
            $table->text('action');
            $table->tinyInteger('module_type')->nullable()
            ->comment(
                '1=AdvancePayment, 2=MeterInsurance, 3=OtherIncomeReceipt, 4=NEAPaymentEntry, ' .
                '5=BlacklistPeriod, 6=ChangeMeter, 7=DiscountAndFine, 8=MahasulReceiptEntry, ' .
                '9=MeterDepositTransaction, 10=MeterIssue, 11=NameTransferEntry, 12=NonMemberPayment, ' .
                '13=RateAndCapacity, 14=ShareTransaction, 15=UpgradeMeterCapacity');
            $table->unsignedInteger('module_id')->nullable();

            $table->json('old_data')->nullable(); 
            $table->json('new_data')->nullable(); 
            $table->ipAddress('ip_address')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
