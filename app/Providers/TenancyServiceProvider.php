<?php

namespace App\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\Tenant;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('tenancy.initializer', fn() => new TenantInitializer());
    }

    public function boot(): void
    {
        // nothing extra needed here
    }
}

/**
 * --------------------------------------------------------------------------
 *  TenantInitializer – handles DB creation, migration, switch, cleanup
 * --------------------------------------------------------------------------
 */
class TenantInitializer
{
    /**
     * Create tenant database (if not exists) and run tenant migrations.
     *
     * @throws \Exception
     */
    public function initializeTenant(Tenant $tenant, string $databaseName): void
    {
        $migrationPath = database_path('migrations/tenant');

        if (
            !DB::connection('mysql')->selectOne(
                'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
                [$databaseName]
            )
        ) {
            DB::connection('mysql')->statement(
                "CREATE DATABASE `$databaseName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );
            Log::info('Tenant database created', ['database' => $databaseName, 'tenant_id' => $tenant->id]);
        }

        config(['database.connections.tenant.database' => $databaseName]);
        DB::purge('tenant');
        DB::reconnect('tenant');

        Log::info('Connected to tenant database', ['database' => $databaseName, 'tenant_id' => $tenant->id]);

        $files = glob($migrationPath . '/*.php');
        if (!is_dir($migrationPath) || !$files) {
            Log::error('No migration files found', ['path' => $migrationPath, 'tenant_id' => $tenant->id]);
            throw new \Exception("No migration files found in {$migrationPath}");
        }

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        Log::info('Tenant migrations executed', ['tenant_id' => $tenant->id, 'output' => Artisan::output()]);

        foreach (['tariff_setups'] as $table) {
            if (!Schema::connection('tenant')->hasTable($table)) {
                Log::error("Table {$table} missing after migration", ['tenant_id' => $tenant->id]);
                throw new \Exception("Table {$table} missing after migration");
            }
        }
    }

    /**
     * Switch to tenant connection (without making it the default).
     */


    public static function switchTenant(Tenant $tenant)
    {
        $databaseName = $tenant->database;

        if (empty($databaseName) && !empty($tenant->data)) {
            $data = is_array($tenant->data) ? $tenant->data : json_decode($tenant->data, true);
            $databaseName = $data['database'] ?? $data['tenancy_db_name'] ?? null;
        }

        if (empty($databaseName)) {
            Log::error('Tenant switch failed: No database defined', [
                'tenant_id' => $tenant->id,
                'company_id' => $tenant->company_id,
            ]);
            throw new \Exception('Tenant database not defined for tenant ID ' . $tenant->id);
        }

        try {
            // Log before switching
            Log::info('Switching to tenant database...', [
                'tenant_id' => $tenant->id,
                'company_id' => $tenant->company_id,
                'database' => $databaseName,
                'current_default' => config('database.default'),
            ]);

            // Update tenant connection config
            config(['database.connections.tenant.database' => $databaseName]);

            DB::purge('tenant');
            DB::connection('tenant')->reconnect();

            DB::setDefaultConnection('tenant');

            $test = DB::connection('tenant')->select('SELECT DATABASE() AS db');
            $activeDb = $test[0]->db ?? 'unknown';

            Log::info('Tenant database switched successfully', [
                'tenant_id' => $tenant->id,
                'company_id' => $tenant->company_id,
                'active_database' => $activeDb,
            ]);

        } catch (\Exception $e) {
            Log::error('Error during tenant switch', [
                'tenant_id' => $tenant->id,
                'company_id' => $tenant->company_id,
                'database' => $databaseName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }


    /**
     * Switch to tenant connection AND make it the default for current request.
     */
    public static function switchTenantAndSetDefault(Tenant $tenant): void
    {
        self::switchTenant($tenant);
        config(['database.default' => 'tenant']);
        DB::purge('tenant');
        DB::reconnect('tenant');
        Log::debug('Tenant set as default connection', ['tenant_id' => $tenant->id]);
    }

    /**
     * Drop tenant database and clean up.
     */
    public function cleanupTenant(string $databaseName): void
    {
        DB::purge('tenant');

        $exists = DB::connection('mysql')->selectOne(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$databaseName]
        );

        if (!$exists) {
            return;
        }

        $attempts = 0;
        $max = 3;

        while ($attempts < $max) {
            try {
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=0');
                DB::connection('mysql')->statement("DROP DATABASE `$databaseName`");
                DB::connection('mysql')->statement('SET FOREIGN_KEY_CHECKS=1');
                Log::info('Tenant database dropped', ['database' => $databaseName]);
                return;
            } catch (\Exception $e) {
                $attempts++;
                Log::warning('Drop attempt failed', [
                    'database' => $databaseName,
                    'attempt' => $attempts,
                    'error' => $e->getMessage(),
                ]);
                if ($attempts === $max) {
                    throw new \Exception("Failed to drop tenant database after {$max} attempts: " . $e->getMessage());
                }
                sleep(1);
            }
        }
    }
}