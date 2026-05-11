<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_entries', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_blacklisted')->default(false);
            $table->string('member_no', 50)->index(); // indexed
            $table->string('customer_name_en', 255)->index(); // indexed
            $table->string('customer_name_np', 255)->index(); // indexed
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->boolean('is_disable')->default(0);
            $table->string('citizenship_no', 50);
            $table->tinyInteger('gender')->default(0)->comment('0 = Male, 1 = Female, 2 = Others');            
            $table->unsignedBigInteger('occupation_id'); // FK to master_setups
            $table->string('pan_no', 50)->nullable();
            $table->string('father_or_husband_name', 50)->nullable();
            $table->string('grandfather_or_father_in_law_name', 50)->nullable();
            $table->string('house_owner_name', 50)->nullable();
            $table->string('contact_no', 20)->nullable();
            $table->unsignedBigInteger('province_id')
                ->nullable();
          
            $table->unsignedBigInteger('district_id')->nullable(); // FK to districts
            $table->unsignedBigInteger('municipality_id')->nullable(); // FK to municipalities
            $table->unsignedInteger('ward_no');
            $table->unsignedBigInteger('area_id'); // FK to master_setups
            $table->string('house_no', 50)->nullable();
            $table->text('location_description')->nullable();
            $table->string('floor', 50)->nullable();
            $table->unsignedBigInteger('wiring_person_id')->nullable(); // FK to wiring_persons
            $table->string('customer_photo', 255)->nullable();
            $table->string('citizenship_front', 255)->nullable();
            $table->string('citizenship_back', 255)->nullable();
            $table->boolean('is_active')->default(1);
            $table->auditFields();

            $table->softDeletes();
            $table->timestamps();


        });
    }

    public function down(): void
    {
       

        Schema::dropIfExists('member_entries');
    }
};
