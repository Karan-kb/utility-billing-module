<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('expense_tracker_id');

            $table->unsignedBigInteger('account_head_id');
            $table->string('ref_bill_no')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->text('particular')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Foreign key
            $table->foreign('expense_tracker_id')
                ->references('id')
                ->on('expense_trackers')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};