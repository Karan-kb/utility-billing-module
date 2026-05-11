<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('province_id');

            $table->foreign('province_id')
                  ->references('id')->on('provinces')
                  ->onDelete('cascade');

            $table->string('name_en');
            $table->string('name_np')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
        });

        Schema::dropIfExists('districts');
    }
};
