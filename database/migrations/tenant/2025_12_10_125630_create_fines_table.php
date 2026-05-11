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
        Schema::create('fines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');

            $table->string('date_in_bs', 10);
            $table->date('date_in_ad');
            $table->uuid('uuid')->nullable();
            $table->unsignedBigInteger('meter_issue_id');
            $table->unsignedBigInteger('meter_reading_entry_id');
            $table->tinyInteger('fine_type')->comment('1=Late Payment, 2=Black List');
            $table->decimal('applied_fine_percentage', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->integer('fine_reapplied')->default(0);
            $table->tinyInteger('status')->default(1)->comment('1=Active, 2=Cancelled, 3=Adjusted, 4=Pending');
            $table->string('voucher_no')->nullable();
            $table->auditFields();

            $table->timestamps();
            $table->softDeletes();

            // Add foreign keys



        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fines');
    }
};
