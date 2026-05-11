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
         if (config('tenant.software_type') === 1) {
            return;
        }
        Schema::create('upgrade_meters', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('is_cancel')->default(0);
            $table->unsignedBigInteger('fiscal_year_id');

            $table->string('date_in_bs', 10);
            $table->date('date_in_ad');
            $table->string('voucher_no', 20)->index();
            $table->unsignedBigInteger('meter_issue_id');
            $table->unsignedBigInteger('existing_capacity_id');
            $table->unsignedInteger('upgraded_capacity_id');

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
        if (config('tenant.software_type') === 1) {
            return;
        }
        Schema::dropIfExists('upgrade_meters');
    }
};
