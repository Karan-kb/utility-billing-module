<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MigrateTenants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Added optional {--rollback} flag
     *
     * @var string
     */
    protected $signature = 'app:migrate-tenants {--rollback : Rollback the last batch of migrations for tenants}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations or rollback for all tenant databases safely';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rollback = $this->option('rollback');

        $action = $rollback ? 'Rolling back' : 'Migrating';
        $this->info("$action tenant databases...");

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            try {
                $tenantData = json_decode($tenant->data, true);
                $databaseName = $tenantData['database'] ?? $tenant->database;

                if (!$databaseName) {
                    $this->warn("Tenant {$tenant->id} has no database configured. Skipping.");
                    continue;
                }

                $this->info("$action tenant: {$tenant->id} ({$databaseName})");

                // Ensure the database exists
                $dbExists = DB::connection('mysql')->selectOne(
                    "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?",
                    [$databaseName]
                );

                if (!$dbExists) {
                    $this->warn("Database {$databaseName} does not exist. Skipping tenant.");
                    continue;
                }

                // Switch connection to tenant
                config(['database.connections.tenant.database' => $databaseName]);
                DB::purge('tenant');
                DB::reconnect('tenant');

                // Run tenant-specific migrations or rollback
                if ($rollback) {
                    Artisan::call('migrate:rollback', [
                        '--database' => 'tenant',
                        '--path' => 'database/migrations/tenant',
                        '--force' => true,
                    ]);
                } else {
                    Artisan::call('migrate', [
                        '--database' => 'tenant',
                        '--path' => 'database/migrations/tenant',
                        '--force' => true,
                    ]);
                }

                $this->info(Artisan::output());

            } catch (\Exception $e) {
                $this->error("Failed $action tenant {$tenant->id}: " . $e->getMessage());
               
            }
        }

        $this->info("All tenant migrations/rollbacks completed (skipped errors logged).");
    }
}
