<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        
        Schema::create('voucher_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->date('date');
            $table->string('voucher_no', 20);
            //$table->unsignedBigInteger('member_entry_id');
            $table->text('particulars');
            $table->tinyInteger('reference_type')->nullable()->comment(
                '1=meter_deposit, 2=upgrade_meter, 3=meter_deposit_return, 4=advance_payment, 5=meter_insurance, 6=other_income_receipt, 7=share_entry, 8=share_return, 9=non_member_payment, 10=nea_payment, 11=mahasul_receipt'
            );
            $table->unsignedInteger('reference_id')->comment('id of the related table');
            $table->tinyInteger('status')->comment('1=draft, 2=posted, 3=void');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->auditFields();
            $table->softDeletes();
        });




    }

    public function down(): void
    {

        Schema::dropIfExists('voucher_summaries');
    }
};
