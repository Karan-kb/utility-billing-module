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
       Schema::create('system_process_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('member_entry_id')->nullable();
    $table->unsignedBigInteger('meter_reading_entry_id')->nullable();
    $table->unsignedBigInteger('receipt_id')->nullable();
    $table->string('service'); // fine | priority_charge
    $table->string('stage')->nullable(); // start | process | slab | allocation | end | error
    $table->string('action')->nullable(); // short label
    $table->json('data')->nullable();
    $table->enum('level', ['info', 'warning', 'error'])->default('info');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_process_logs');
    }
};
