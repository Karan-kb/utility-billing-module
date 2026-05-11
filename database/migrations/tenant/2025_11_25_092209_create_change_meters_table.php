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
        Schema::create('change_meters', function (Blueprint $table) {
           $table->id();
            $table->unsignedBigInteger('fiscal_year_id');

            $table->string('date_in_bs', 10);
            $table->date('date_in_ad');
            $table->unsignedBigInteger('meter_issue_id');
            $table->string('previous_meter_no', 50);
            $table->string('construct_company', 50)->nullable();
            $table->string('reading_seal_no', 50)->nullable();
            $table->string('terminal_seal_no', 50)->nullable();
            $table->string('meter_box_seal_no', 50)->nullable();
            $table->unsignedInteger('meter_start_no');
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
       
        Schema::dropIfExists('change_meters');
    }
};
