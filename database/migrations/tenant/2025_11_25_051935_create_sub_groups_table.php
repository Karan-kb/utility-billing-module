<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('main_group_id'); // FK to main_groups
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();

        });
    }

    public function down(): void
    {

        Schema::dropIfExists('sub_groups');
    }
};
