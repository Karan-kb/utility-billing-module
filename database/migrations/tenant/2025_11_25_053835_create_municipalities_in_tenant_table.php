<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('district_id');
            $table->string('name_en');
            $table->string('name_np')->nullable();
            $table->timestamps();
            $table->auditFields();

        });
    }

    public function down(): void
    {

        Schema::dropIfExists('municipalities');
    }
};
