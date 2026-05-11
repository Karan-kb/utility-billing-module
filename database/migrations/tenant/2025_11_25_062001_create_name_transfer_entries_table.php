<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('name_transfer_entries', function (Blueprint $table) {
           $table->id(); // integer UNSIGNED AUTO_INCREMENT

            $table->unsignedBigInteger('fiscal_year_id');

            $table->unsignedBigInteger('previous_member_entry_id');
            $table->unsignedBigInteger('new_member_entry_id');

            $table->softDeletes();
            $table->auditFields();
            $table->timestamps();



        });
    }

    public function down(): void
    {
       

        Schema::dropIfExists('name_transfer_entries');
    }
};
