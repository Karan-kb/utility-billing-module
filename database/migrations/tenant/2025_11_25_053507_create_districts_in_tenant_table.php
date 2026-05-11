<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('province_id');
            $table->string('name_en');
            $table->string('name_np')->nullable();
            $table->auditFields();
            $table->timestamps();
        });
    }

    public function down(): void
    {
       
        Schema::dropIfExists('districts');
    }
};
