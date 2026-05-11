<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_trackers', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('fiscal_year_id');

            // Voucher info
            $table->string('voucher_no')->unique();
            $table->string('date_in_bs');
            $table->softDeletes();
            $table->auditFields();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_trackers');
    }
};