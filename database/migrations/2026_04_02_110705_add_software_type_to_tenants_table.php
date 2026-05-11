<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {

            $table->tinyInteger('software_type')
                ->default(0)
                ->after('company_id')
                ->comment('0 = Bidut (Electricity system), 1 = Khanepani (Water system)');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('software_type');
        });
    }
};