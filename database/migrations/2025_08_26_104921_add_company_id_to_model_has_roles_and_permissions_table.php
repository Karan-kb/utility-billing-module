<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('model_has_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('model_has_roles', 'company_id')) {
                $table->unsignedInteger('company_id')->nullable()->after('model_id');

                $table->foreign('company_id', 'model_has_roles_company_id_foreign')
                    ->references('id')->on('companies')
                    ->onDelete('cascade');

                $table->index(['model_id', 'role_id', 'company_id'], 'model_role_company_index');
            }
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('model_has_permissions', 'company_id')) {
                $table->unsignedInteger('company_id')->nullable()->after('model_id');

                $table->foreign('company_id', 'model_has_permissions_company_id_foreign')
                    ->references('id')->on('companies')
                    ->onDelete('cascade');

                $table->index(['model_id', 'permission_id', 'company_id'], 'model_permission_company_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropForeign('model_has_roles_company_id_foreign');
            $table->dropIndex('model_role_company_index');
            $table->dropColumn('company_id');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropForeign('model_has_permissions_company_id_foreign');
            $table->dropIndex('model_permission_company_index');
            $table->dropColumn('company_id');
        });
    }
};
