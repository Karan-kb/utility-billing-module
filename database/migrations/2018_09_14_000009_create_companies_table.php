<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('reg_number', 50)->nullable();
            $table->string('pan_number', 50)->nullable();
            $table->string('license_number', 50)->nullable();
            $table->string('email_address', 50)->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->string('website')->nullable();
            $table->text('full_address')->nullable();
            $table->unsignedInteger('province_id');
            $table->unsignedInteger('district_id');
            $table->unsignedInteger('municipality_id');
            $table->unsignedInteger('ward_no');
            $table->string('contact_person', 50);
            $table->string('contact_person_position', 50);
            $table->date('licence_issue_date');
            $table->date('license_expiry_date');
            $table->string('activation_key', 50);
            $table->string('url_link')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('province_id')
                ->references('id')->on('provinces')
                ->onDelete('cascade');

            $table->foreign('district_id')
                ->references('id')->on('districts')
                ->onDelete('cascade');

            $table->foreign('municipality_id')
                ->references('id')->on('municipalities')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['municipality_id']);
            $table->dropForeign(['district_id']);
            $table->dropForeign(['province_id']);
            
        });

        Schema::dropIfExists('companies');
    }
};
