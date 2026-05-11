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
        Schema::create('voucher_summary_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voucher_summary_id');
            $table->string('particulars', 255);
            $table->decimal('debit', 15, 2)->default(0.00);
            $table->decimal('credit', 15, 2)->default(0.00);
            $table->unsignedBigInteger('account_head_id')
                ->nullable();
            $table->unsignedInteger('member_entry_id')->nullable();
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
        Schema::dropIfExists('voucher_summary_details');
    }
};
