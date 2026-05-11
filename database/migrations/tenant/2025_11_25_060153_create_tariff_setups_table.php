<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tariff_setups', function (Blueprint $table) {
            $table->id();
            $table->string('rule_name', 50);
            $table->boolean('is_active')->default(0);

            $table->softDeletes();
            $table->auditFields();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_setups');
    }
};
