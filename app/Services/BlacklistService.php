<?php

namespace App\Services;

use App\Helpers\MeterPenaltyGuard;
use App\Models\CustomerTransaction;
use App\Models\MemberEntry;
use App\Models\MeterIssue;
use App\Models\Tenant;
use App\Models\MeterReadingEntry;
use App\Models\MahasulReceiptEntry;
use App\Models\BlacklistPeriod;
use App\Enums\BlacklistContext;
use App\Models\Fine;
use App\Helpers\NepaliCalendar;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BlacklistService
{
    /**
     * Apply blacklist fines for all tenants
     */
    public function applyBlacklistToAllTenants(string $todayAD = null, string $todayBS = null): void
    {
        foreach (Tenant::all() as $tenant) {
            $this->applyBlacklistToTenant($tenant, $todayAD, $todayBS);
        }
    }

    /**
     * Apply blacklist fines for a single tenant
     */
    public function applyBlacklistToTenant(Tenant $tenant, string $todayAD = null, string $todayBS = null): void
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

            Log::info("Blacklist job started for Tenant {$tenant->id}");

            MeterReadingEntry::withoutTrashed()
                ->with('meterIssue')
                ->where('status', '!=', 2) // skip paid
                ->chunk(100, function ($entries) use ($todayAD, $todayBS) {

                    foreach ($entries as $entry) {
                        Log::info("BlacklistCheck Entry={$entry->id}");
                        $this->processMeterEntry($entry, $todayAD, $todayBS);
                    }
                });

        } catch (\Exception $e) {
            Log::error("Tenant {$tenant->id} blacklist error: {$e->getMessage()}");
        }
    }


    /**
     * Process a single meter reading entry
     */

    public function processMeterEntry(
        MeterReadingEntry $entry,
        string $todayAD,
        string $todayBS
    ): void {

        // 1. Meter issue must exist
        if (!$entry->meterIssue) {
            return;
        }

        // 2. Member must exist
        $memberEntryId = $entry->meterIssue->member_entry_id ?? null;
        if (!$memberEntryId) {
            return;
        }

        // 3. Skip if member already blacklisted
        if (MemberEntry::where('id', $memberEntryId)->value('is_blacklisted') == 1) {
            return;
        }

        // 4. Skip if reading is PAID
        if ($entry->status == 2) {
            return;
        }

        // 5. Get max applicable blacklist days
        $maxBlacklistDays = BlacklistPeriod::where('is_applied', 1)->max('days');
        if (!$maxBlacklistDays) {
            return;
        }

        // 6. Find oldest overdue unpaid reading with no blacklist fine applied
        $overdueReading = MeterReadingEntry::withoutTrashed()
            ->whereHas('meterIssue', fn($q) => $q->where('member_entry_id', $memberEntryId))
            ->where('status', '!=', 2)
            ->whereDate('reading_date_in_ad', '<=', Carbon::parse($todayAD)->subDays($maxBlacklistDays))
            ->whereDoesntHave('fines', function ($q) {
                $q->where('fine_type', 2); // blacklist fine type
            })
            ->orderBy('reading_date_in_ad')
            ->first();

        if (!$overdueReading) {
            return;
        }

        // 7. Calculate days overdue
        $daysOverdue = Carbon::parse($overdueReading->reading_date_in_ad)
            ->diffInDays(Carbon::parse($todayAD));

        // 8. Get applicable blacklist slab
        $blacklist = BlacklistPeriod::where('is_applied', 1)
            ->where('days', '<=', $daysOverdue)
            ->orderByDesc('days')
            ->first();

        if (!$blacklist) {
            return;
        }

        // 9. Create blacklist fine
        $this->createBlacklistFine(
            $overdueReading,
            $blacklist->amount,
            $todayAD,
            $todayBS
        );

        // 10. Add customer transaction
        CustomerTransaction::create([
            'reference_id' => $overdueReading->id,
            'charge_type' => 3,
            'member_entry_id' => $memberEntryId,
            'transaction_date' => now(),
            'transaction_type' => 1,
            'amount' => $blacklist->amount,
            'direction' => 'DR',
        ]);

        // 11. Mark member blacklisted
        MemberEntry::where('id', $memberEntryId)->update([
            'is_blacklisted' => 1,
        ]);

        Log::info(
            "Member {$memberEntryId} BLACKLISTED | Reading {$overdueReading->id} | Days overdue={$daysOverdue}"
        );
    }




    public function applyBlacklistToCurrentDB(string $todayAD = null, string $todayBS = null): void
    {
        $todayAD = $todayAD ?? now()->toDateString();
        $todayBS = $todayBS ?? NepaliCalendar::adToBs($todayAD);

        Log::info("Starting blacklist process on current DB", [
            'AD' => $todayAD,
            'BS' => $todayBS
        ]);

        try {
            $entries = MeterReadingEntry::withoutTrashed()->with('meterIssue')->get();

            if ($entries->isEmpty()) {
                Log::info('No meter reading entries found for blacklist process.');
                return;
            }

            foreach ($entries as $entry) {
                try {
                    Log::info('Processing meter reading entry', [
                        'entry_id' => $entry->id,
                        'status' => $entry->status,
                        'meter_issue_id' => $entry->meter_issue_id ?? null,
                    ]);

                    $this->processMeterEntryCurrentDB($entry, $todayAD, $todayBS);
                } catch (\Exception $innerEx) {
                    Log::error('Error processing entry', [
                        'entry_id' => $entry->id,
                        'message' => $innerEx->getMessage(),
                        'trace' => $innerEx->getTraceAsString()
                    ]);
                }
            }

        } catch (ModelNotFoundException $e) {
            Log::error("Blacklist process ModelNotFoundException: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString()
            ]);
        } catch (QueryException $e) {
            Log::error("Blacklist process QueryException: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString()
            ]);
        } catch (\Exception $e) {
            Log::error("Blacklist process general exception: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString()
            ]);
        }

        Log::info("Finished blacklist process on current DB");
    }


    /**
     * Process a single meter reading entry in the current DB
     */
    public function processMeterEntryCurrentDB(
        MeterReadingEntry $entry,
        string $todayAD,
        string $todayBS
    ): void {

        if (!$entry->meterIssue) {
            Log::info('No meterIssue found for entry', ['entry_id' => $entry->id]);
            return;
        }

        $memberEntryId = $entry->meterIssue->member_entry_id ?? null;
        if (!$memberEntryId) {
            Log::info('No memberEntryId found for entry', ['entry_id' => $entry->id]);
            return;
        }

        Log::info('Processing member_entry_id', ['member_entry_id' => $memberEntryId]);

        if (MemberEntry::where('id', $memberEntryId)->value('is_blacklisted') == 1) {
            Log::info('Member already blacklisted', ['member_entry_id' => $memberEntryId]);
            return;
        }

        if ($entry->status == 2) {
            Log::info('Entry status is 2, skipping', ['entry_id' => $entry->id]);
            return;
        }

        // maxBlacklistDays is an integer
        $maxBlacklistDays = BlacklistPeriod::where('is_applied', 1)->max('days');
        if (!$maxBlacklistDays) {
            Log::info('No active BlacklistPeriod found', ['member_entry_id' => $memberEntryId]);
            return;
        }
        Log::info('Max blacklist days', ['max_blacklist_days' => $maxBlacklistDays]);

        $overdueReading = MeterReadingEntry::withoutTrashed()
            ->whereHas('meterIssue', fn($q) => $q->where('member_entry_id', $memberEntryId))
            ->where('status', '!=', 2)
            ->whereDate('reading_date_in_ad', '<=', Carbon::parse($todayAD)->subDays($maxBlacklistDays))
            ->whereDoesntHave('fines', function ($q) {
                $q->where('fine_type', 2)
                    ->whereHas('meterReadingEntry', fn($qr) => $qr->where('status', '!=', 2));
            })
            ->orderBy('reading_date_in_ad')
            ->first();

        if (!$overdueReading) {
            Log::info('No overdue reading found', ['member_entry_id' => $memberEntryId]);
            return;
        }
        Log::info('Overdue reading found', ['reading_id' => $overdueReading->id, 'reading_date' => $overdueReading->reading_date_in_ad]);

        $daysOverdue = Carbon::parse($overdueReading->reading_date_in_ad)
            ->diffInDays(Carbon::parse($todayAD));
        Log::info('Days overdue', ['reading_id' => $overdueReading->id, 'days_overdue' => $daysOverdue]);

        $blacklist = BlacklistPeriod::where('is_applied', 1)
            ->where('days', '<=', $daysOverdue)
            ->orderByDesc('days')
            ->first();

        if (!$blacklist) {
            Log::info('No applicable blacklist period found', ['member_entry_id' => $memberEntryId, 'days_overdue' => $daysOverdue]);
            return;
        }
        Log::info('Applying blacklist', ['blacklist_id' => $blacklist->id, 'amount' => $blacklist->amount]);

        $this->createBlacklistFine($overdueReading, $blacklist->amount, $todayAD, $todayBS);

        $transaction = CustomerTransaction::create([
            'reference_id' => $overdueReading->id,
            'charge_type' => 3,
            'member_entry_id' => $memberEntryId,
            'transaction_date' => now(),
            'transaction_type' => 1,
            'amount' => $blacklist->amount,
            'direction' => 'DR',
        ]);

        Log::info("CustomerTransaction created for blacklisted member", [
            'transaction_id' => $transaction->id ?? null,
            'member_entry_id' => $transaction->member_entry_id ?? null,
            'reference_id' => $transaction->reference_id ?? null,
            'amount' => $transaction->amount ?? null,
            'charge_type' => $transaction->charge_type ?? null,
            'transaction_date' => $transaction->transaction_date ?? null,
        ]);

        MemberEntry::where('id', $memberEntryId)->update(['is_blacklisted' => 1]);

        Log::info("Member blacklisted successfully", [
            'member_entry_id' => $memberEntryId,
            'reading_id' => $overdueReading->id,
            'days_overdue' => $daysOverdue
        ]);
    }






    /**
     * Create blacklist fine (fine_type = 2)
     */
    protected function createBlacklistFine(
        MeterReadingEntry $entry,
        $amount,
        string $todayAD,
        string $todayBS
    ): void {
        Fine::create(
            [
                'meter_reading_entry_id' => $entry->id,
                'fine_type' => 2,

                'meter_issue_id' => $entry->meter_issue_id,
                'uuid' => $entry->uuid,
                'amount' => $amount,
                'applied_fine_percentage' => 0,
                'status' => 1,
                'date_in_ad' => $todayAD,
                'date_in_bs' => $todayBS,
            ]
        );
    }

}