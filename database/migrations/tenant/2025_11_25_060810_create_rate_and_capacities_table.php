<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rate_and_capacities', function (Blueprint $table) {
            $table->id(); // integer UNSIGNED AUTO_INCREMENT

            $table->unsignedInteger('unit_from');
            $table->unsignedInteger('unit_to');
            $table->unsignedBigInteger('tariff_setup_id'); 
            if (config('tenant.software_type', 0) !== 1) {
            $table->unsignedBigInteger('phase_id');        
            $table->unsignedBigInteger('capacity_id');     
            $table->unsignedBigInteger('purpose_id');      
            }
            $table->decimal('rate_per_unit', 15, 2)->default(0.00);
            $table->decimal('minimum_demand', 15, 2)->unsigned();
            $table->decimal('subsidy_charge', 15, 2)->unsigned()->default(0.00);
            $table->decimal('service_charge', 15, 2)->unsigned()->default(0.00);
            $table->decimal('other_charge', 15, 2)->unsigned()->default(0.00);

            $table->softDeletes();
            $table->auditFields();
            $table->timestamps();

        });
    }

    public function down(): void
    {

        Schema::dropIfExists('rate_and_capacities');
    }
};
