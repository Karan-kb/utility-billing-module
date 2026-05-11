<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;
use App\Models\Tenant;
use Illuminate\Support\Facades\Config;

class UpdateTenantColumnComments extends Command
{
    protected $signature = 'tenant:comments-update';
    protected $description = 'Update column comments for specific tables and columns for all tenants';

    public function handle(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found!');
            return;
        }

        // Table => [column => helperMethod]
        $updates = [
            'activity_logs' => ['module_type' => 'moduleTypeComment'],
            'payments'      => ['type' => 'paymentTypeComment'],
            'voucher_summaries' => ['reference_type' => 'voucherTypeComment'],
        ];

        foreach ($tenants as $tenant) {
            $connectionName = 'tenant_dynamic_' . $tenant->id;

            Config::set("database.connections.{$connectionName}", [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => $tenant->database,
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ]);

            foreach ($updates as $table => $columns) {
                foreach ($columns as $column => $helperMethod) {
                    try {
                        $comment = Helper::$helperMethod();
                        DB::connection($connectionName)->statement("
                            ALTER TABLE $table 
                            MODIFY $column TINYINT COMMENT '$comment'
                        ");
                        $this->info("Updated $table.$column for tenant '{$tenant->id}' ({$tenant->database})");
                    } catch (\Exception $e) {
                        $this->error("Failed $table.$column for tenant '{$tenant->id}': " . $e->getMessage());
                    }
                }
            }
        }
    }
}