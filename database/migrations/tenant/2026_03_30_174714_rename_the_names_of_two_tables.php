<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename tables only if they haven't been renamed yet
        if (Schema::hasTable('expense_trackers')) {
            Schema::rename('expense_trackers', 'expense_and_receivable_trackers');
        }

        if (Schema::hasTable('expense_items')) {
            Schema::rename('expense_items', 'expense_and_receivable_items');
        }

        // Only proceed if the target table exists
        if (Schema::hasTable('expense_and_receivable_items')) {
            Schema::table('expense_and_receivable_items', function (Blueprint $table) {
                if (Schema::hasColumn('expense_and_receivable_items', 'expense_tracker_id')) {
                    $this->dropForeignKeyIfExists('expense_and_receivable_items', 'expense_and_receivable_items_expense_tracker_id_foreign');

                    $table->renameColumn('expense_tracker_id', 'tracker_id');
                }

                if (Schema::hasColumn('expense_and_receivable_items', 'tracker_id')) {
                    $this->dropForeignKeyIfExists('expense_and_receivable_items', 'expense_and_receivable_items_tracker_id_foreign');

                    $table->foreign('tracker_id')
                        ->references('id')
                        ->on('expense_and_receivable_trackers')
                        ->onDelete('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expense_and_receivable_items')) {
            Schema::table('expense_and_receivable_items', function (Blueprint $table) {
                if (Schema::hasColumn('expense_and_receivable_items', 'tracker_id')) {
                    $this->dropForeignKeyIfExists('expense_and_receivable_items', 'expense_and_receivable_items_tracker_id_foreign');

                    $table->renameColumn('tracker_id', 'expense_tracker_id');
                }

                if (Schema::hasColumn('expense_and_receivable_items', 'expense_tracker_id')) {
                    $this->dropForeignKeyIfExists('expense_and_receivable_items', 'expense_and_receivable_items_expense_tracker_id_foreign');

                    $table->foreign('expense_tracker_id')
                        ->references('id')
                        ->on('expense_trackers')
                        ->onDelete('cascade');
                }
            });
        }

        if (Schema::hasTable('expense_and_receivable_items')) {
            Schema::rename('expense_and_receivable_items', 'expense_items');
        }

        if (Schema::hasTable('expense_and_receivable_trackers')) {
            Schema::rename('expense_and_receivable_trackers', 'expense_trackers');
        }
    }

    /**
     * Drop a foreign key only if it exists, avoiding the SQLSTATE[42000] error.
     */
    private function dropForeignKeyIfExists(string $table, string $foreignKey): void
    {
        $conn = Schema::getConnection();
        $dbName = $conn->getDatabaseName();

        $exists = $conn->select("
            SELECT COUNT(*) as count
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = ?
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$dbName, $table, $foreignKey]);

        if ($exists[0]->count > 0) {
            Schema::table($table, function (Blueprint $blueprint) use ($foreignKey) {
                $blueprint->dropForeign($foreignKey);
            });
        }
    }
};