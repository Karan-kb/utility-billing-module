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
        Schema::create('meter_reading_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedInteger('reader_id')->nullable();
            $table->char('uuid', 36)->nullable();
            $table->tinyInteger('is_synced')->default(0);
            $table->tinyInteger('status')->default(0)->comment('1=pending,2=billed,3=reserved');
            $table->tinyInteger('entry_type')->nullable()->comment('1=meter_reading, 2=opening_mahasul');
            $table->unsignedTinyInteger('reading_month_in_bs')->nullable();
            $table->string('reading_date_in_bs', 10);
            
            $table->date('reading_date_in_ad');

            $table->unsignedBigInteger('tariff_setup_id')->nullable();
            $table->unsignedBigInteger('meter_issue_id');

            $table->unsignedInteger('previous_unit')->default(0);
            $table->unsignedInteger('current_unit')->default(0);

            $table->unsignedInteger('discount_unit_for_disable')->default(0);
            $table->unsignedInteger('total_unit')->default(0);

            $table->decimal('unit_amount', 15, 2)->default(0.00);

            $table->decimal('fine_amount', 15, 2)->default(0.00);

            $table->decimal('total_charge', 15, 2)->default(0.00);

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


        Schema::dropIfExists('meter_reading_entries');
    }
};
