<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meter_issues', function (Blueprint $table) {
           $table->id();
            $table->unsignedBigInteger('fiscal_year_id');

            $table->unsignedBigInteger('member_entry_id');

           // Only add these columns if software_type is not 1 (i.e., not Khanepani)
            if (config('tenant.software_type', 0) !== 1) {
                $table->unsignedBigInteger('phase_id');      
                $table->unsignedBigInteger('capacity_id');    
                $table->unsignedBigInteger('purpose_id');  
                 $table->string('issue_meter_capacity', 50)->nullable();  
            }

            

            $table->string('meter_no', 50)->index();
            $table->unsignedInteger('transformer_id')->comment('Intake ID for Khanepani'); 
            $table->unsignedInteger('meter_start_no');
            $table->string('issue_date_bs', 10);
            $table->date('issue_date_ad');
            $table->string('construct_company', 50)->nullable();
           
            $table->string('reading_seal_no', 50)->nullable();
            $table->string('terminal_seal_no', 50)->nullable();
            $table->string('meter_box_seal_no', 50)->nullable();
            $table->string('pole_no', 50)->nullable()->comment('Intake no for Khanepani'); 
            $table->string('pole_distance', 100)->nullable()->comment('Intake distance for Khanepani'); 
            $table->unsignedInteger('area_id');      
            $table->unsignedInteger('meter_issue_record')->default(0);
            $table->boolean('is_active')->default(1);
            $table->date('disconnected_date_ad')->nullable();
            $table->auditFields();

            $table->softDeletes();
            $table->timestamps();


        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_issues');
    }
};
