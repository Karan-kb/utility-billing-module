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
        Schema::create('role_menu_permissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('role_id')
                  ->constrained()
                  ->onDelete('cascade');

            $table->foreignId('menu_id')
                  ->constrained()
                  ->onDelete('cascade');

            $table->tinyInteger('has_view_access')->default(0);
            $table->tinyInteger('has_create_access')->default(0);
            $table->tinyInteger('has_update_access')->default(0);
            $table->tinyInteger('has_delete_access')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_menu_permissions');
    }
};
