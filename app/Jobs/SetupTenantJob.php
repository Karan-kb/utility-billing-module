<?php

namespace App\Jobs;

use App\Helpers\NepaliCalendar;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Helpers\Helper;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\TenantInitializer;
use App\Stubs\MainGroupStub;
use App\Models\MasterSetup;
use App\Models\MasterSetupType;
use App\Models\TariffSetup;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SetupTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $tenantId;
    public string $databaseName;
    public int $companyId;
    public $tries = 3; // Retry 3 times
    public $timeout = 900; // 15 minutes

    public function __construct($tenantId, $databaseName, $companyId)
    {
        $this->tenantId = $tenantId;
        $this->databaseName = $databaseName;
        $this->companyId = $companyId;
    }

    public function handle()
    {
        Log::info("SetupTenantJob started for tenant: {$this->tenantId}");

        $tenant = Tenant::findOrFail($this->tenantId);
        $company = Company::findOrFail($this->companyId);
       config(['tenant.software_type' => $tenant->software_type]);
        // Initialize Tenant
        app(TenantInitializer::class)->initializeTenant($tenant, $this->databaseName);
        TenantInitializer::switchTenant($tenant);

        // Run tenant migrations
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);



        // MasterSetup Types
        // foreach (['Purpose of Use', 'Type of House', 'Phase', 'Capacity', 'Tranformer', 'Area', 'Occupation', 'Bank'] as $name) {
        //     MasterSetupType::create(['name' => $name]);
        // }
        $masterSetupTypes = [
            'Purpose of Use',
            'Type of House',
            'Phase',
            'Capacity',
           config('tenant.software_type') === 1 ? 'Intake' : 'Transformer',
            'Area',
            'Occupation',
            'Bank'
        ];

foreach ($masterSetupTypes as $name) {
    MasterSetupType::create(['name' => $name]);
}

        $purposeItems = [
            ['name_en' => 'Residential', 'name_np' => 'आवासीय'],
            ['name_en' => 'Immigration', 'name_np' => 'आप्रवासन'],
            ['name_en' => 'Mill', 'name_np' => 'मिल'],
            ['name_en' => 'Udyog', 'name_np' => 'उद्योग'],
            ['name_en' => 'School', 'name_np' => 'स्कूल'],
            ['name_en' => 'Irrigation', 'name_np' => 'सिंचाई'],
        ];

        $order = 1;
        foreach ($purposeItems as $item) {
            MasterSetup::create([
                'master_setup_type_id' => 1,
                'name_en' => $item['name_en'],
                'name_np' => $item['name_np'],
                'order_no' => $order++,
                'is_active' => true,
            ]);
        }


        $phaseItems = [
            ['name_en' => 'Single Phase', 'name_np' => 'एक फेज'],
            ['name_en' => 'Three Phase', 'name_np' => 'थ्री फेज़'],
        ];

        $order = 1;
        foreach ($phaseItems as $item) {
            MasterSetup::create([
                'master_setup_type_id' => 3,
                'name_en' => $item['name_en'],
                'name_np' => $item['name_np'],
                'order_no' => $order++,
                'is_active' => true,
            ]);
        }

        $capacityItems = [
            ['name_en' => '6 amp', 'name_np' => '६ ए यम पी'],
            ['name_en' => '10 amp', 'name_np' => '१० ए यम पी'],
            ['name_en' => '16 amp', 'name_np' => '१६ ए यम पी'],
            ['name_en' => '32 amp', 'name_np' => '३२ ए यम पी'],
            ['name_en' => '2 HP', 'name_np' => '२ एच पी'],
            ['name_en' => '5 HP', 'name_np' => '५ एच पी'],
            ['name_en' => '10 HP', 'name_np' => '१० एच पी'],
            ['name_en' => '15 HP', 'name_np' => '१५ एच पी'],
            ['name_en' => '25 HP', 'name_np' => '२५ एच पी'],
        ];

        $order = 1;
        foreach ($capacityItems as $item) {
            MasterSetup::create([
                'master_setup_type_id' => 4,
                'name_en' => $item['name_en'],
                'name_np' => $item['name_np'],
                'order_no' => $order++,
                'is_active' => true,
            ]);
        }

        $organizationItems = [
            ['name_en' => 'Agriculture', 'name_np' => 'कृषि'],
            ['name_en' => 'Business', 'name_np' => 'व्यवसाय'],
            ['name_en' => 'Organization', 'name_np' => 'संगठन'],
        ];

        $order = 1;
        foreach ($organizationItems as $item) {
            MasterSetup::create([
                'master_setup_type_id' => 7,
                'name_en' => $item['name_en'],
                'name_np' => $item['name_np'],
                'order_no' => $order++,
                'is_active' => true,
            ]);
        }


        foreach (['rule1', 'rule2', 'rule3', 'rule4'] as $ruleName) {
            TariffSetup::create(['rule_name' => $ruleName, 'is_active' => false]);
        }


        MainGroupStub::createMainGroups();

        Helper::fiscalYears();


        Artisan::call('import:nepal-states-all');

        Log::info("SetupTenantJob completed for tenant: {$this->tenantId}");
    }
}
