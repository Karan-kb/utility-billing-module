<?php

namespace App\Services;

use App\Helpers\MeterPenaltyGuard;
use App\Models\Tenant;
use App\Models\MeterReadingEntry;
use App\Models\MemberEntry;
use App\Models\MeterIssue;
use App\Models\BlacklistPeriod;
use App\Models\MahasulReceiptEntry;
use App\Models\DiscountAndFine;
use App\Models\CustomerTransaction;
use App\Models\Fine;
use App\Helpers\NepaliCalendar;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Enums\BlacklistContext;
use App\Helpers\ProcessLogger;
use App\Services\BlacklistService;
use App\Models\OpeningMahasulFineSetup;

class FineService
{

    protected BlacklistService $blacklistService;

    public function __construct(BlacklistService $blacklistService)
    {
        $this->blacklistService = $blacklistService;
    }
    protected function isOpeningMahasulFineEnabled(): bool
{
    return (bool) OpeningMahasulFineSetup::query()
        ->value('is_fine_applied');
}
   protected function canApplyFineForEntry(MeterReadingEntry $entry): bool
{
    if ($entry->entry_type == 2) {
        return $this->isOpeningMahasulFineEnabled();
    }

    return true;
}
    /**
     * Apply fines for all tenants
     */
    public function applyFinesToAllTenants(string $todayAD = null, string $todayBS = null): void
    {
        foreach (Tenant::all() as $tenant) {
            $this->applyFinesToTenant($tenant, $todayAD, $todayBS);
        }
    }

    /**
     * Apply fines for a single tenant
     */
    protected function applyFinesToTenant(Tenant $tenant, string $todayAD = null, string $todayBS = null): void
    {
        $database = json_decode($tenant->data, true)['database'] ?? $tenant->database;

        if (!$database) {
            Log::warning("Tenant {$tenant->id} has no database.");
            return;
        }

        try {
            config(['database.connections.tenant.database' => $database]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            $todayAD = $todayAD ?? now()->toDateString();
            $todayBS = $todayBS ?? NepaliCalendar::adToBs($todayAD);

            MeterReadingEntry::withoutTrashed()
                ->with('meterIssue')
                ->each(fn($entry) => $this->processMeterEntry($entry, $todayAD, $todayBS));

        } catch (\Exception $e) {
            Log::error("Tenant {$tenant->id} fine error: {$e->getMessage()}");
        }
    }



    public function processMeterEntry(
        MeterReadingEntry $entry,
        string $todayAD,
        string $todayBS
    ) {

        if (!$entry->meterIssue) {
            return null;
        }

      
        $hasFinalizedFine = Fine::withoutTrashed()
            ->where('meter_issue_id', $entry->meter_issue_id)
            ->where('meter_reading_entry_id', $entry->id)
            ->where('status', 3)
            ->exists();

        if ($hasFinalizedFine) {
            return null;
        }

            $latestReceipt = MahasulReceiptEntry::withoutTrashed()
            ->where('is_cancel', 0)
            ->where('meter_issue_id', $entry->meter_issue_id)
            ->orderByDesc('meter_reading_entry_id')
            ->first();

            if (!$latestReceipt) {
                return;
            }

            if ($entry->id < $latestReceipt->meter_reading_entry_id) {
                return;
            }

            if ($latestReceipt->total_due_amount <= 0) {
                return;
            }


        if ($latestReceipt && $latestReceipt->total_due_amount > 0) {


            $alreadyAppliedPercent3 = optional(
                Fine::withoutTrashed()
                    ->where('meter_issue_id', $entry->meter_issue_id)
                    ->where('meter_reading_entry_id', $entry->id)
                    ->where('status', 1)
                    ->where('fine_type', 3)
                    ->orderByDesc('date_in_ad')
                    ->first()
            )->applied_fine_percentage ?? 0;


            $currentPercentDue = $this->calculateCurrentFinePercentage($entry, $todayAD, $latestReceipt, 3);
            $incrementPercent3 = $currentPercentDue - $alreadyAppliedPercent3;

            if ($incrementPercent3 > 0) {
                $fineAmountDue = round(($latestReceipt->total_due_amount * $incrementPercent3) / 100, 2);

                Fine::create([
                    'meter_issue_id' => $entry->meter_issue_id,
                    'meter_reading_entry_id' => $entry->id,
                    'uuid' => $entry->uuid,
                    'fine_type' => 3,
                    'amount' => $fineAmountDue,
                    'applied_fine_percentage' => $currentPercentDue,
                    'status' => 1,
                    'date_in_ad' => $todayAD,
                    'date_in_bs' => $todayBS,
                ]);

                //Log::info("MeterEntry {$entry->id} | Fine type 3 created | Amount: {$fineAmountDue} | Applied: {$currentPercentDue}%");
                ProcessLogger::log(
                    service: 'fine',
                    action: 'created',
                    data: [
                        'amount' => $fineAmountDue,
                        'applied_percentage' => $currentPercentDue,
                        'fine_type' => 3,
                    ],
                    level: 'info',
                    context: [
                        'member_entry_id' => $entry->meterIssue->member_entry_id ?? null,
                        'entry_id' => $entry->id,
                        'receipt_id' => $latestReceipt->id ?? null,
                        'stage' => 'fine_created',
                    ]
                );
            }
        }

        // --- Fine type 1: sub_total_charge incremental ---
        $currentPercentCharge = $this->calculateCurrentFinePercentage($entry, $todayAD, null, 1);

        if ($currentPercentCharge <= 0) {
            return null;
        }

        //$alreadyAppliedPercent = $this->getAlreadyAppliedFinePercentage($entry);
        $alreadyAppliedPercent = $this->getAlreadyAppliedFinePercentage($entry, 1);

        $incrementPercent = $this->calculateIncrementalFine($currentPercentCharge, $alreadyAppliedPercent);

        if ($incrementPercent <= 0) {
            return null;
        }

        $fineAmountCharge = $this->calculateFineAmount($entry, $incrementPercent);

        $this->createFineRecord($entry, $fineAmountCharge, $currentPercentCharge, $todayAD, $todayBS, 1);

        return $fineAmountCharge;
    }



    /**
     * Calculate current fine percentage
     * fineType: 1 = sub_total_charge, 3 = total_due_amount
     */
    protected function calculateCurrentFinePercentage(
        MeterReadingEntry $entry,
        string $todayAD,
        ?MahasulReceiptEntry $latestReceipt = null,
        int $fineType = 1
    ) {
        $today = Carbon::parse($todayAD);

        // Use receipt date for fine_type 3, reading date for fine_type 1
        if ($fineType === 3) {
            if (!$latestReceipt || $latestReceipt->total_due_amount <= 0) {
                return 0; // No fine if no receipt or fully paid
            }
            $baseDate = Carbon::parse($latestReceipt->date_in_ad);
        } else {
            $baseDate = Carbon::parse($entry->reading_date_in_ad);
        }

        $daysOverdue = $baseDate->diffInDays($today);

        $rule = DiscountAndFine::withoutTrashed()
            ->where('type', 2)
            ->where('amount_type', 1)
            ->where('is_active', 1)
            ->where('days_after', '<=', $daysOverdue)
            ->orderByDesc('days_after')
            ->first();

        Log::info("MeterEntry {$entry->id} | Fine type {$fineType} | Days overdue: {$daysOverdue} | Applicable fine slab: " . ($rule ? "{$rule->amount}%" : "None"));

        return $rule ? $rule->amount : 0;
    }


   
    protected function getAlreadyAppliedFinePercentage(MeterReadingEntry $entry, int $fineType = 1)
    {
        return optional(
            Fine::withoutTrashed()
                ->where('meter_issue_id', $entry->meter_issue_id)
                ->where('meter_reading_entry_id', $entry->id)
                ->where('status', 1)
                ->where('fine_type', $fineType)
                ->orderByDesc('date_in_ad')
                ->first()
        )->applied_fine_percentage ?? 0;
    }

    /**
     * Calculate incremental fine percentage
     */
    protected function calculateIncrementalFine($currentPercent, $alreadyAppliedPercent)
    {
        return $currentPercent - $alreadyAppliedPercent;
    }

    /**
     * Calculate fine amount from sub_total_charge
     */
    protected function calculateFineAmount(MeterReadingEntry $entry, $incrementPercent)
    {
        return round(($entry->sub_total_charge * $incrementPercent) / 100, 2);
    }

    /**
     * Create Fine record
     */
    protected function createFineRecord(MeterReadingEntry $entry, $amount, $appliedPercent, string $todayAD, string $todayBS, int $fineType = 1): void
    {
        Fine::create([
            'meter_issue_id' => $entry->meter_issue_id,
            'meter_reading_entry_id' => $entry->id,
            'uuid' => $entry->uuid,
            'fine_type' => $fineType,
            'amount' => $amount,
            'applied_fine_percentage' => $appliedPercent,
            'status' => 1,
            'date_in_ad' => $todayAD,
            'date_in_bs' => $todayBS,
        ]);

        Log::info("MeterEntry {$entry->id} | Fine record created | Type: {$fineType} | Amount: {$amount} | Applied: {$appliedPercent}%");
    }

    /**
     * Apply fines with logging
     */
    public function applyFinesAndBlacklistToTenant(Tenant $tenant, string $todayAD = null, string $todayBS = null): void
    {
        $database = json_decode($tenant->data, true)['database'] ?? $tenant->database;

        Log::info("TENANT START: ID={$tenant->id}, DB={$database}");

        if (!$database) {
            return;
        }

        try {
            config(['database.connections.tenant.database' => $database]);
            DB::purge('tenant');
            DB::reconnect('tenant');

            $todayAD = $todayAD ?? now()->toDateString();
            $todayBS = $todayBS ?? NepaliCalendar::adToBs($todayAD);
            //Log::info("START Fine + Blacklist Process | Tenant={$tenant->id} | AD={$todayAD} | BS={$todayBS}");
           
            MeterReadingEntry::withoutTrashed()
                ->with('meterIssue')
                ->whereNotIn('status', [2, 3])
                ->orderBy('id')
                ->chunk(200, function ($entries) use ($todayAD, $todayBS) {

                    foreach ($entries as $entry) {
                        try {

                            $this->processTenantMeterEntry($entry, $todayAD, $todayBS);
                           

                        } catch (\Throwable $ex) {
                            Log::error("ERROR PROCESSING ENTRY {$entry->id}: {$ex->getMessage()} at {$ex->getFile()}:{$ex->getLine()}");
                        }
                    }
                });

          

        } catch (\Throwable $e) {
            Log::error("FATAL Tenant {$tenant->id}: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
        }
    }


    protected function processTenantMeterEntry(MeterReadingEntry $entry, string $todayAD, string $todayBS)
    {

        $meterIssue = $entry->meterIssue;

        if (!$meterIssue) {
        return;
        }

        // Skip if meter issue is inactive
        if ($meterIssue->is_active == 0) {
        return;
        }

        $member = MemberEntry::find($meterIssue->member_entry_id);

        // Skip if member inactive
        if (!$member || $member->is_active == 0) {
        return;
        }

        $memberId = $entry->meterIssue->member_entry_id ?? null;

        if (!$memberId) {
        return;
        }
        // Apply Mahasul receipt fine
        $this->applyMahasulReceiptFine($entry, $todayAD, $todayBS, $memberId);
        // Apply Meter Reading entry fine
        $this->applyMeterReadingEntryFine($entry, $todayAD, $todayBS, $memberId);
    }

    protected function applyMahasulReceiptFine(MeterReadingEntry $entry, string $todayAD, string $todayBS, int $memberId)
    {
        $entryId = $entry->id;

        $latestReceipt = MahasulReceiptEntry::withoutTrashed()
            ->where('is_cancel', 0)
            ->where('meter_issue_id', $entry->meter_issue_id)
            ->orderByDesc('id') 
            ->first();

        if (!$latestReceipt) {
            return;
        }

        if ($latestReceipt->total_due_amount <= 0) {
            return;
        }
        $baseDue = $latestReceipt->total_due_amount;

        // Use the receipt's date as start date for fine calculation
        $startDateAD = Carbon::parse($latestReceipt->date_in_ad)->startOfDay();
        $startDateBS = Carbon::parse($latestReceipt->date_in_bs)->startOfDay();

        $daysOverdue = $startDateAD->diffInDays(Carbon::parse($todayAD)->startOfDay());

        Log::info("Entry {$entryId} Mahasul Receipt BaseDue={$baseDue}, StartDateAD={$startDateAD}, StartDateBS={$startDateBS}, DaysOverdue={$daysOverdue}");

        if ($daysOverdue <= 0) {
            return;
        }

        // Pass the receipt date as start date for fine reapply
        $this->applyFineSlabs(
            $entry,
            $memberId,
            $todayAD,
            $todayBS,
            $baseDue,
            $daysOverdue,
            'mahasul',
            $startDateAD // Start date for fine calculation
        );
    }

    protected function applyMeterReadingEntryFine(MeterReadingEntry $entry, string $todayAD, string $todayBS, int $memberId)
    {
         // If entry is active bill (status=1), fine must come from Mahasul receipt due
        if ($entry->status == 1) {
            return;
        }

        if ($this->isReceiptDoneUpToEntry($entry->meter_issue_id, $entry->id)) {
            return;
        }
        $entryId = $entry->id;
        
        // if ($entry->entry_type == 2 && $entry->is_fine_applied == 0) {
        //         return;
        // }
        // if ($entry->entry_type == 2) {
        //     return;
        // }
        if (!$this->canApplyFineForEntry($entry)) {
            return;
        }
       
        // if ($entry->entry_type == 2) {
        //     $hasLaterRelevantEntry = MeterReadingEntry::where('meter_issue_id', $entry->meter_issue_id)
        //         ->where('id', '>', $entry->id)
        //         ->whereIn('status', [1, 2])
        //         ->exists();

        //     if ($hasLaterRelevantEntry) {
        //         return;
        //     }
        // }
       
        $baseForFine = $entry->sub_total_charge;
       

        $startDate = Carbon::parse($entry->reading_date_in_ad)->startOfDay();
        $daysOverdue = $startDate->diffInDays(Carbon::parse($todayAD)->startOfDay());

        Log::info("Entry {$entryId} MeterReading StartDate={$startDate}, DaysOverdue={$daysOverdue}");

        if ($daysOverdue <= 0) {
            return;
        }

        // Apply fine slabs WITHOUT using due, just pass sub_total_charge
        $this->applyFineSlabs(
            $entry,
            $memberId,
            $todayAD,
            $todayBS,
            $baseForFine,
            $daysOverdue,
            'meter_reading',
            $startDate
        );
    }
    
    protected function applyFineSlabs(
        MeterReadingEntry $entry,
        int $memberId,
        string $todayAD,
        string $todayBS,
        float $baseDueForFine,
        int $daysOverdue,
        string $type,
        Carbon $startDate
    ) {
        $entryId = $entry->id;

        $fineType = $this->resolveFineType($type);

        $fineSlabs = DiscountAndFine::where('type', 2)
            ->where('is_active', 1)
            ->where('days_after', '<=', $daysOverdue)
            ->orderBy('days_after', 'asc')
            ->get();

        Log::info("Entry {$entryId} {$type} → Loaded " . count($fineSlabs) . " fine slabs");

        $highestAppliedPercentage = (float) Fine::where('meter_reading_entry_id', $entryId)
            ->where('fine_type', $fineType)
            ->max('applied_fine_percentage') ?? 0;

        $alreadyAppliedAmount = (float) Fine::where('meter_reading_entry_id', $entryId)
            ->where('fine_type', $fineType)
            ->sum('amount');


        $cumulativeAppliedFine = $alreadyAppliedAmount;
        $currentHighestPercentage = $highestAppliedPercentage;

        foreach ($fineSlabs as $slab) {

            $slabRate = (float) $slab->amount;

            if ($slabRate <= $currentHighestPercentage) {
                continue;
            }

            $requiredFine = ($slab->amount_type == 1)
                ? round(($baseDueForFine * $slabRate) / 100, 2)
                : round($slab->amount, 2);

            $incrementalFine = max(0, $requiredFine - $cumulativeAppliedFine);


            if ($incrementalFine <= 0) {
                $cumulativeAppliedFine = $requiredFine;
                $currentHighestPercentage = $slabRate;
                continue;
            }
            $exists = Fine::where('meter_reading_entry_id', $entryId)
                ->where('fine_type', $fineType)
                ->where('applied_fine_percentage', $slabRate)
                ->exists();

            if ($exists) {
                continue;
            }
   
            DB::transaction(function () use ($entry, $memberId, $incrementalFine, $slabRate, $slab, $todayAD, $todayBS, $baseDueForFine, $type, $fineType, $entryId) {
 
                Fine::create([
                    'meter_issue_id' => $entry->meter_issue_id,
                    'meter_reading_entry_id' => $entry->id,
                    'uuid' => $entry->uuid,
                    'fine_type' => $fineType,
                    'amount' => $incrementalFine,
                    'applied_fine_percentage' => $slabRate,
                    'status' => 1,
                    'date_in_ad' => $todayAD,
                    'date_in_bs' => $todayBS,
                    'remarks' => $slab->description ?? "Late fine",
                ]);
 
                CustomerTransaction::create([
                    'member_entry_id' => $memberId,
                    'reference_id' => $entry->id,
                    'transaction_type' => 1,
                    'charge_type' => 3,
                    'direction' => 'DR',
                    'amount' => $incrementalFine,
                    'due' => $type === 'mahasul' ? $baseDueForFine : null,
                    'transaction_date' => now(),
                    'created_at' => now(),
                    'remarks' => $slab->description ?? "Late fine",
                ]);
                
                           try {
                            ProcessLogger::log(
                                service: 'fine',
                                action: 'applied',
                                data: [
                                    'incremental_fine' => $incrementalFine,
                                    'slab_rate' => $slabRate,
                                    'base_due' => $baseDueForFine,
                                    'type' => $type
                                ],
                                level: 'info',
                                context: [
                                    'member_entry_id' => $memberId,
                                    'entry_id' => $entryId,
                                    'stage' => 'slab_applied',
                                    'receipt_id' => $type === 'mahasul' ? ($latestReceipt->id ?? null) : null,
                                ]
                            );
                        } catch (\Throwable $logError) {
                            Log::warning("ProcessLogger failed (non-critical)", [
                                'entry_id' => $entryId,
                                'error' => $logError->getMessage()
                            ]);
                        }
            });

                    $cumulativeAppliedFine += $incrementalFine;
                    $currentHighestPercentage = $slabRate;
                }
            }

    protected function applyBlacklistCheck(MeterReadingEntry $entry, int $daysOverdue, float $currentDue, $todayAD)
    {
        Log::info("BlacklistCheck Entry={$entry->id}, Days={$daysOverdue}, Due={$currentDue}");

        $memberId = $entry->meterIssue->member_entry_id;

        if (!$memberId) {
            Log::warning("Blacklist: NO member found for entry {$entry->id}");
            return;
        }
        if ($this->isReceiptDoneUpToEntry($entry->meter_issue_id, $entry->id)) {
            return;
        }



        if ($currentDue <= 0) {
            return;
        }

        //  if ($entry->entry_type == 2 && $entry->is_fine_applied == 0) {
        //         return;
        // }
        if (!$this->canApplyFineForEntry($entry)) {
            return;
        }
        $rule = BlacklistPeriod::where('is_applied', 1)->first();

        if (!$rule) {
            return;
        }

        Log::info("BlacklistRule: DaysRequired={$rule->days}, Amount={$rule->amount}");

        if ($daysOverdue >= $rule->days) {

            $already = MemberEntry::where('id', $memberId)->value('is_blacklisted');

            Log::info("Blacklist: Member {$memberId} is_blacklisted={$already}");

            if ($already != 1) {
                MemberEntry::where('id', $memberId)->update(['is_blacklisted' => 1]);

                Log::warning("BLACKLISTED Member {$memberId}");

                $blacklistAmount = $rule->amount ?? 0;

                Fine::create([
                    'meter_reading_entry_id' => $entry->id,
                    'fine_type' => 2,
                    'meter_issue_id' => $entry->meter_issue_id,
                    'uuid' => $entry->uuid,
                    'amount' => $blacklistAmount,
                    'applied_fine_percentage' => 0,
                    'status' => 1,
                    'created_at' => now(),
                    'date_in_ad' => now()->toDateString(),
                    'date_in_bs' => NepaliCalendar::adToBs(now()->toDateString()),
                ]);

                // FIXED: Changed charge_type from 3 to 11 for blacklist
                CustomerTransaction::create([
                    'reference_id' => $entry->id,
                    'charge_type' => 11, // Changed from 3 to 11 for blacklist
                    'member_entry_id' => $memberId,
                    'transaction_date' => now(),
                    'transaction_type' => 1,
                    'due' => NULL,
                    'amount' => $blacklistAmount,
                    'created_at' => now(),
                    'direction' => 'DR',
                ]);

                //Log::info("Blacklist charge applied: Member={$memberId}, Amount={$blacklistAmount} with charge_type=11");
                ProcessLogger::log(
                service: 'FineService',
                action: 'Blacklist charge applied',
                data: [
                    'member_id' => $memberId,
                    'amount' => $blacklistAmount,
                    'charge_type' => 11,
                    'entry_id' => $entry->id
                ],
                level: 'info',
                 context: [
                'member_entry_id' => $memberId,
                'entry_id' => $entry->id,
                'receipt_id' => null,
                'stage' => 'blacklist_charge_applied',
            ]
            );

            } 

        }
    }

    public function applyFinesToCurrentTenant(int $meterIssueId, ?string $todayAD = null, ?string $todayBS = null): void
    {
        $todayAD = $todayAD ?? now()->toDateString();
        $todayBS = $todayBS ?? NepaliCalendar::adToBs($todayAD);

        Log::info("Fine process started", [
            'meter_issue_id' => $meterIssueId,
            'date_ad' => $todayAD,
        ]);

        $entries = MeterReadingEntry::withoutTrashed()
            ->where('meter_issue_id', $meterIssueId)
            ->whereNotIn('status', [2, 3]) // not paid / cancelled
            ->orderBy('reading_date_in_ad')
            ->get();

        if ($entries->isEmpty()) {
            //Log::info("No pending bills found for meter issue {$meterIssueId}");
            return;
        }

        foreach ($entries as $entry) {
            $this->applyFineToSingleBill($entry, $todayAD, $todayBS);
        }

        // Optional: Blacklist check for this customer
        $this->checkAndApplyBlacklist($meterIssueId, $todayAD, $todayBS);

        Log::info("Fine process finished for meter issue {$meterIssueId} !");
    }

    private function applyFineToSingleBill(MeterReadingEntry $entry, string $todayAD, string $todayBS): void
    {
        $entryId = $entry->id;
        $memberId = $entry->meterIssue->member_entry_id ?? null;

        if (!$memberId) {
            return;
        }

        // Check if we already applied fine today
        if (
            Fine::where('meter_reading_entry_id', $entryId)
                ->where('fine_type', 1)
                ->whereDate('date_in_ad', $todayAD)
                ->exists()
        ) {
            return;
        }

        // 1. Get current unpaid amount (principal bill)
        $dueAmount = CustomerTransaction::where('reference_id', $entryId)
            ->where('direction', 'DR')
             ->whereIn('transaction_type', [1, 5])
            //->where('transaction_type', 1)
            ->whereIn('charge_type', [1, 2, 4, 5, 6]) // main charges
            ->sum('amount');

        $dueAmount -= CustomerTransaction::where('reference_id', $entryId)
            ->where('direction', 'CR')
            ->whereIn('transaction_type', [1, 2, 5])
            //->whereIn('transaction_type', [1, 2])
            ->whereIn('charge_type', [1, 2, 4, 5, 6])
            ->sum('amount');

        $dueAmount = max(0, round($dueAmount, 2));

        if ($dueAmount <= 0) {
            return;
        }

        // 2. Calculate days overdue
        $daysOverdue = Carbon::parse($entry->reading_date_in_ad)
            ->startOfDay()
            ->diffInDays(Carbon::parse($todayAD)->startOfDay());

        if ($daysOverdue < 1) {
            return;
        }

        // 3. Get the highest fine slab that applies
        $fineSlab = DiscountAndFine::where('type', 2) // fine
            ->where('is_active', 1)
            ->where('days_after', '<=', $daysOverdue)
            ->orderBy('days_after', 'desc')
            ->first();

        if (!$fineSlab) {
            return;
        }

        // 4. Check if we already applied this percentage
        $alreadyAppliedPercent = Fine::where('meter_reading_entry_id', $entryId)
            ->where('fine_type', 1)
            ->sum('applied_fine_percentage');

        if ($alreadyAppliedPercent >= $fineSlab->amount) {
            return;
        }

        // 5. Calculate fine amount
        $finePercent = $fineSlab->amount; // e.g. 10, 20, 30...
        $fineAmount = round(($dueAmount * $finePercent) / 100, 2);

        if ($fineAmount < 1) {
            return;
        }

        // 6. Apply fine (transaction + fine record)
        DB::transaction(function () use ($entry, $memberId, $fineAmount, $finePercent, $fineSlab, $todayAD, $todayBS, $dueAmount, $daysOverdue) {
            Fine::create([
                'meter_issue_id' => $entry->meter_issue_id,
                'meter_reading_entry_id' => $entry->id,
                'uuid' => $entry->uuid,
                'fine_type' => 1, // late fine
                'amount' => $fineAmount,
                'applied_fine_percentage' => $finePercent,
                'status' => 1,
                'date_in_ad' => $todayAD,
                'date_in_bs' => $todayBS,
                'remarks' => "Late fine ({$finePercent}%) - {$daysOverdue} days",
                'created_at' => now(),
            ]);

            CustomerTransaction::create([
                'member_entry_id' => $memberId,
                'reference_id' => $entry->id,
                'transaction_type' => 1,
                'charge_type' => 3, // fine
                'direction' => 'DR',
                'amount' => $fineAmount,
                'due' => $dueAmount,
                'transaction_date' => now(),
                'created_at' => now(),
                'remarks' => "Late fine {$finePercent}%",
            ]);

            Log::info("Fine applied", [
                'entry' => $entry->id,
                'amount' => $fineAmount,
                'percent' => $finePercent,
                'days' => $daysOverdue,
            ]);
        });
    }

    public function checkAndApplyBlacklist(int $meterIssueId, string $todayAD, string $todayBS): void
    {
        $rule = BlacklistPeriod::where('is_applied', 1)->first();
        if (!$rule) {
            return;
        }

        $member = $this->getMemberFromMeterIssue($meterIssueId);
        if (!$member || $member->is_blacklisted) {
            return;
        }

        // Get total overdue days from oldest unpaid bill
        $oldestEntry = MeterReadingEntry::where('meter_issue_id', $meterIssueId)
            ->whereNotIn('status', [2, 3])
            ->orderBy('reading_date_in_ad')
            ->first();

        if (!$oldestEntry) {
            return;
        }

        $daysOverdue = Carbon::parse($oldestEntry->reading_date_in_ad)
            ->diffInDays(Carbon::parse($todayAD));

        if ($daysOverdue >= $rule->days) {
            $member->update(['is_blacklisted' => 1]);

            // Optional: add blacklist fine/charge
            if ($rule->amount > 0) {
                CustomerTransaction::create([
                    'member_entry_id' => $member->id,
                    'reference_id' => $oldestEntry->id,
                    'charge_type' => 3,
                    'transaction_type' => 1,
                    'direction' => 'DR',
                    'amount' => $rule->amount,
                    'transaction_date' => now(),
                    'created_at' => now(),
                    'remarks' => 'Blacklist penalty',
                ]);
            }

            Log::warning("Member blacklisted", ['member_id' => $member->id, 'days' => $daysOverdue]);
        }
    }

    private function getMemberFromMeterIssue(int $meterIssueId)
    {
        return MeterIssue::find($meterIssueId)?->memberEntry;
    }



    /**
     * Process fine for a SINGLE specific meter reading entry
     * This is optimized for immediate fine application after entry creation
     * ONLY creates Fine record, NO CustomerTransaction
     */
    public function processSingleMeterEntry(
        MeterReadingEntry $entry,
        string $todayAD,
        string $todayBS
    ) {

        // 1. Check if entry already has fines applied today
        $fineAppliedToday = Fine::where('meter_reading_entry_id', $entry->id)
            ->where('fine_type', 1)
            ->whereDate('date_in_ad', $todayAD)
            ->exists();

        if ($fineAppliedToday) {
            return;
        }

        // 2. Get member ID (only for logging, not for transaction)
        $memberId = $entry->meterIssue->member_entry_id ?? null;
        if (!$memberId) {
            return;
        }
        //  if ($entry->entry_type == 2 && $entry->is_fine_applied == 0) {
        //         return;
        // }
       
        if (!$this->canApplyFineForEntry($entry)) {
            return;
        }
        // 3. Calculate current due amount (only the original bill, not including previous fines)
        $totalOriginalBill = (float) CustomerTransaction::where('reference_id', $entry->id)
            ->where('direction', 'DR')
            ->whereIn('transaction_type', [1, 5])
            //->where('transaction_type', 1)
            ->whereIn('charge_type', [6, 1, 4, 5, 2])
            ->sum('amount');

        // Subtract any payments
        $totalPaid = (float) CustomerTransaction::where('reference_id', $entry->id)
            ->where('direction', 'CR')
            ->whereIn('transaction_type', [2])
            ->whereIn('charge_type', [1, 2, 4, 5, 6])
            ->sum('amount');

        $currentDue = max(0, $totalOriginalBill - $totalPaid);

        if ($currentDue <= 0) {
            return;
        }

        // 4. Calculate days overdue from reading date
        $daysOverdue = Carbon::parse($entry->reading_date_in_ad)
            ->startOfDay()
            ->diffInDays(Carbon::parse($todayAD)->startOfDay());

        if ($daysOverdue <= 0) {
            return;
        }

        $fineSlab = DiscountAndFine::where('type', 2)
            ->where('is_active', 1)
            ->where('days_after', '<=', $daysOverdue)
            ->orderBy('days_after', 'desc')
            ->first();

        if (!$fineSlab) {
            return;
        }

        // Cast values to float
        $fineSlabAmount = (float) $fineSlab->amount;
        $fineSlabAmountType = (int) $fineSlab->amount_type;

        // 6. Check if this percentage has already been applied
        $alreadyAppliedPercent = (float) (Fine::where('meter_reading_entry_id', $entry->id)
            ->where('fine_type', 1)
            ->max('applied_fine_percentage') ?? 0);

        if ($alreadyAppliedPercent >= $fineSlabAmount) {
            return;
        }

        // 7. Calculate incremental fine amount
        $requiredFine = ($fineSlabAmountType == 1)
            ? round(($currentDue * $fineSlabAmount) / 100, 2)
            : round($fineSlabAmount, 2);

        $existingFineTotal = (float) Fine::where('meter_reading_entry_id', $entry->id)
            ->where('fine_type', 1)
            ->sum('amount');

        $incrementalFine = max(0, $requiredFine - $existingFineTotal);

        if ($incrementalFine <= 0) {
            return;
        }

        // 8. Apply ONLY the Fine record - NO CustomerTransaction
        DB::transaction(function () use ($entry, $incrementalFine, $fineSlabAmount, $fineSlabAmountType, $todayAD, $todayBS, $daysOverdue, $memberId) {

            // Create ONLY Fine record, NO CustomerTransaction
            Fine::create([
                'meter_issue_id' => $entry->meter_issue_id,
                'meter_reading_entry_id' => $entry->id,
                'uuid' => $entry->uuid,
                'fine_type' => 1,
                'amount' => $incrementalFine,
                'applied_fine_percentage' => $fineSlabAmount,
                'status' => 1,
                'date_in_ad' => $todayAD,
                'date_in_bs' => $todayBS,
                'remarks' => "Immediate fine applied on creation - {$daysOverdue} days overdue",
                'fine_reapplied' => 0,
                'created_at' => now(),
            ]);

            // Log::info("IMMEDIATE FINE APPLIED (single entry)", [
            //     'entry_id' => $entry->id,
            //     'amount' => $incrementalFine,
            //     'percent' => $fineSlabAmount,
            //     'days' => $daysOverdue,
            //     'due' => $currentDue ?? 'not_calculated'
            // ]);
            ProcessLogger::log(
            service: 'FineService',
            action: 'Immediate fine applied (single entry)',
            data: [
                'amount' => $incrementalFine,
                'percent' => $fineSlabAmount,
                'days' => $daysOverdue,
            ],
            level: 'info',
            stage: 'single_entry_processing',
            context: [
                'member_entry_id' => $memberId,
                'entry_id' => $entry->id
            ]
        );
        });

        // 9. Check if this entry should trigger blacklist
        $this->checkSingleEntryBlacklist($entry, $daysOverdue, $currentDue, $todayAD);
    }

    /**
     * Check blacklist for a single entry - NO CustomerTransaction
     */
    protected function checkSingleEntryBlacklist(
        MeterReadingEntry $entry,
        int $daysOverdue,
        float $currentDue,
        string $todayAD
    ) {
        $memberId = $entry->meterIssue->member_entry_id;
        if (!$memberId) {
            return;
        }
        //  if ($entry->entry_type == 2 && $entry->is_fine_applied == 0) {
        //         return;
        // }
        if (!$this->canApplyFineForEntry($entry)) {
            return;
        }

        // Only apply blacklist if this is the oldest unpaid bill
        $oldestUnpaid = MeterReadingEntry::withoutTrashed()
            ->where('meter_issue_id', $entry->meter_issue_id)
            ->whereNotIn('status', [2, 3])
            ->orderBy('reading_date_in_ad', 'asc')
            ->first();

        if (!$oldestUnpaid || $oldestUnpaid->id != $entry->id) {
            return;
        }

        $rule = BlacklistPeriod::where('is_applied', 1)->first();
        if (!$rule) {
            return;
        }

        if ($daysOverdue >= $rule->days && $currentDue > 0) {
            $already = MemberEntry::where('id', $memberId)->value('is_blacklisted');

            if ($already != 1) {
                MemberEntry::where('id', $memberId)->update(['is_blacklisted' => 1]);


                // If you still want to create a Fine record for blacklist
                if ($rule->amount > 0) {
                    Fine::create([
                        'meter_reading_entry_id' => $entry->id,
                        'fine_type' => 2,
                        'meter_issue_id' => $entry->meter_issue_id,
                        'uuid' => $entry->uuid,
                        'amount' => (float) $rule->amount,
                        'applied_fine_percentage' => 0,
                        'status' => 1,
                        'created_at' => now(),
                        'date_in_ad' => now()->toDateString(),
                        'date_in_bs' => NepaliCalendar::adToBs(now()->toDateString()),
                        'remarks' => 'Blacklist penalty from immediate fine',
                    ]);

                    // FIXED: Changed charge_type from 3 to 11 for blacklist in single entry
                    CustomerTransaction::create([
                        'reference_id' => $entry->id,
                        'charge_type' => 11, // Changed from 3 to 11 for blacklist
                        'member_entry_id' => $memberId,
                        'transaction_date' => now(),
                        'transaction_type' => 1,
                        'due' => NULL,
                        'amount' => (float) $rule->amount,
                        'created_at' => now(),
                        'direction' => 'DR',
                        'remarks' => 'Blacklist penalty from immediate fine',
                    ]);

                    //Log::info("Blacklist fine record and transaction created: Member={$memberId}, Amount={$rule->amount} with charge_type=11 from entry {$entry->id}");
                    ProcessLogger::log(
                    service: 'BlacklistService',
                    action: 'Blacklist fine record and transaction created',
                    data: [
                        'amount' => (float) $rule->amount,
                        'charge_type' => 11,
                        'entry_id' => $entry->id
                    ],
                    level: 'info',
                    stage: 'single_entry_check',
                    context: [
                        'member_entry_id' => $memberId,
                        'entry_id' => $entry->id
                    ]
                );
                }
            }
        }
    }

    protected function isReceiptDoneUpToEntry(int $meterIssueId, int $entryId): bool
    {
        // Get the latest Mahasul receipt for this meter_issue
        $latestReceipt = MahasulReceiptEntry::withoutTrashed()
            ->where('is_cancel', 0)
            ->where('meter_issue_id', $meterIssueId)
            ->orderByDesc('meter_reading_entry_id')
            ->orderByDesc('id') 
            ->first();

        if (!$latestReceipt) {
            return false; // No receipt → do not block fines
        }

        // If the latest receipt's entry ID >= this entry ID, we consider receipt done
        return $latestReceipt->meter_reading_entry_id >= $entryId;
    }
    /**
     * Resolve fine_type based on context
     * 1 = Meter Reading Fine
     * 3 = Mahasul Receipt Fine
     */
    protected function resolveFineType(string $type): int
    {
        return match ($type) {
            'mahasul' => 3,
            'meter_reading' => 1,
            default => 1,
        };
    }


}