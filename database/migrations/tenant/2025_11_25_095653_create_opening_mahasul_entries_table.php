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
        Schema::create('opening_mahasul_entries', function (Blueprint $table) {
           $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->string('date_in_bs', 10);
            $table->date('date_in_ad');
            $table->unsignedBigInteger('meter_issue_id');
            $table->decimal('mahasul_amount', 15, 2)->default(0.00);
            $table->decimal('demand_charge', 15, 2)->default(0.00);
            $table->decimal('subsidy_charge', 15, 2)->default(0.00);
            $table->decimal('service_charge', 15, 2)->default(0.00);
            $table->decimal('other_charge', 15, 2)->default(0.00);
            $table->decimal('fine_amount', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
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
         if (config('tenant.software_type') !== null) {
            return;
        }
        Schema::dropIfExists('opening_mahasul_entries');
    }
};
