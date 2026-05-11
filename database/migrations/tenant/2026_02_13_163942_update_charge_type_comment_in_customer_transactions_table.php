<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customer_transactions', function (Blueprint $table) {

            $table->tinyInteger('charge_type')
                ->comment('
                    1=demand_charge,
                    2=service_charge,
                    3=fine_charge,
                    4=subsidy_charge,
                    5=other_charge,
                    6=energy_charge,
                    7=rebate,
                    8=discount,
                    9=advance_charge,
                    10=disable_discount,
                    11=blacklist_charge
                ')
                ->change();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_transactions', function (Blueprint $table) {

            $table->tinyInteger('charge_type')
                ->comment('
                    1=demand_charge,
                    2=service_charge,
                    3=fine_charge,
                    4=subsidy_charge,
                    5=other_charge,
                    6=energy_charge,
                    7=rebate,
                    8=discount,
                    9=advance_charge,
                    10=disable_discount
                ')
                ->change();

        });
    }
};
