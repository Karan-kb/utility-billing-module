<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_and_fines', function (Blueprint $table) {
            $table->id(); // integer UNSIGNED AUTO_INCREMENT

            $table->tinyInteger('type')->comment('1=discount, 2=fine, 3=rebate');
            $table->tinyInteger('amount_type')->comment('1=percent, 2=amount');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->unsignedInteger('days_after');
            $table->boolean('is_active')->default(1);
            $table->auditFields();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_and_fines');
    }
};
