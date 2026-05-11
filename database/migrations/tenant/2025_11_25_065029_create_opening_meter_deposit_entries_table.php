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
        if (config('tenant.software_type') !== null) {
            return;
        }
        Schema::create('opening_meter_deposit_entries', function (Blueprint $table) {
           $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->string('date_in_bs', 10);
            $table->date('date_in_ad');
            $table->unsignedBigInteger('meter_issue_id');
            $table->decimal('amount', 15, 2)->default(0.00);
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
        if (config('tenant.software_type') !== null) {
            return;
        }
        Schema::dropIfExists('opening_meter_deposit_entries');
    }
};
