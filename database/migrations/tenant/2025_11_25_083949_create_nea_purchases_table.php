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
        Schema::create('nea_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->string('date_in_bs', 10);
            $table->date('date_in_ad');
            $table->tinyInteger('month');
            $table->unsignedInteger('transformer_id');
            $table->unsignedInteger('total_units');
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
        if (config('tenant.software_type') === 1) {
                    return;
                }
       
        Schema::dropIfExists('nea_purchases');
    }
};
