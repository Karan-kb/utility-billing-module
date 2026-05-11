<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_setups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('master_setup_type_id');
            $table->string('name_en', 100);
            $table->string('name_np', 255);

            $table->unsignedInteger('order_no');

            $table->boolean('is_active')->default(1);
            $table->auditFields();

            $table->softDeletes();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        
        Schema::dropIfExists('master_setups');
    }
};
