<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Services\FineService;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class ApplyOverdueFines extends Command
{
    // Add optional --date option
   // protected $signature = 'fines:apply {--date=}';
   protected $signature = 'fines:apply {--date=} {--company=}';
    protected $description = 'Apply overdue fines to all tenants';
    protected FineService $fineService;

    public function __construct(FineService $fineService)
    {
        parent::__construct();
        $this->fineService = $fineService;
    }

    // public function handle()
    // {
    //     $this->info('Starting overdue fines for all tenants...');

    //     $lock = Cache::lock('dispatch-fines-jobs-lock', 50);

    //     if (!$lock->get()) {
    //         $this->warn('Previous fines dispatch is still running. Skipping this run.');
    //         return;
    //     }

    //     try {
    //         $dateOption = $this->option('date');
    //         $todayAD = $dateOption ?? now()->toDateString();
    //         $todayBS = \App\Helpers\NepaliCalendar::adToBs($todayAD);

    //         $this->info("Applying fines assuming today is: {$todayAD} (BS: {$todayBS})");

            
    //         foreach (Tenant::all() as $tenant) {
    //             $this->fineService->applyFinesAndBlacklistToTenant($tenant, $todayAD, $todayBS);
    //         }

    //         $this->info('All tenant overdue fines processed successfully.');

    //     } catch (\Exception $e) {
    //         $this->error('Error processing fines: ' . $e->getMessage());
    //         Log::error('Error processing fines: ' . $e->getMessage());
    //     } finally {
    //         $lock->release();
    //     }
    // }
   public function handle()
{
    $this->info('Starting overdue fines process...');

    $lock = Cache::lock('dispatch-fines-jobs-lock', 50);

    if (!$lock->get()) {
        $this->warn('Previous fines dispatch is still running. Skipping this run.');
        return;
    }

    try {
        $dateOption = $this->option('date');
        $companyOption = $this->option('company');

        $todayAD = $dateOption ?? now()->toDateString();
        $todayBS = \App\Helpers\NepaliCalendar::adToBs($todayAD);

        $this->info("Applying fines for date: {$todayAD} (BS: {$todayBS})");

        // 👉 If company_id is provided
        if ($companyOption) {

            $tenants = Tenant::where('company_id', $companyOption)->get();

            if ($tenants->isEmpty()) {
                $this->error("No tenant found for company_id: {$companyOption}");
                return;
            }

            foreach ($tenants as $tenant) {
                $this->info("Processing tenant: {$tenant->id} (Company: {$tenant->company_id})");

                $this->fineService->applyFinesAndBlacklistToTenant(
                    $tenant,
                    $todayAD,
                    $todayBS
                );
            }

        } else {
            // 👉 Run for all tenants
            foreach (Tenant::all() as $tenant) {
                $this->fineService->applyFinesAndBlacklistToTenant(
                    $tenant,
                    $todayAD,
                    $todayBS
                );
            }

            $this->info('All tenant overdue fines processed successfully.');
        }

    } catch (\Exception $e) {
        $this->error('Error processing fines: ' . $e->getMessage());
        Log::error('Error processing fines: ' . $e->getMessage());
    } finally {
        $lock->release();
    }
}

}
