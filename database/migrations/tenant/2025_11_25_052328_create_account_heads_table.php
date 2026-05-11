<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('account_heads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('name_np', 255);
            $table->unsignedBigInteger('account_group_id');
            $table->tinyInteger('type')->default(0)->comment('1=Other Income');
            $table->decimal('amount', 15, 2)->nullable();
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();


        });
    }

    public function down()
    {


        Schema::dropIfExists('account_heads');
    }
};
