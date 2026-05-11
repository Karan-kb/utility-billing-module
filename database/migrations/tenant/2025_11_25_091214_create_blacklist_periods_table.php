<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blacklist_periods', function (Blueprint $table) {
           $table->id();
            $table->unsignedInteger('days');
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->boolean('is_applied')->default(1);
            $table->auditFields();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blacklist_periods');
    }
};
