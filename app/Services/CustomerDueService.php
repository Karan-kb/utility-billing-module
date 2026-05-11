<?php

namespace App\Services;

use App\Models\CustomerTransaction;
use App\Models\MeterReadingEntry;

class CustomerDueService
{
    /**
     * Calculate previous total due for a customer
     */
    public function getPreviousTotalDue(int $memberEntryId, int $meterIssueId): float
    {
        // Get valid meter reading IDs
        $meterReadingIds = MeterReadingEntry::where('meter_issue_id', $meterIssueId)
            ->whereIn('status', [0, 1])
            ->pluck('id');

        $totalDue = CustomerTransaction::where(function ($mainQuery) use ($memberEntryId, $meterReadingIds) {

            $mainQuery->where(function ($query) use ($memberEntryId, $meterReadingIds) {

                // DR transactions (meter readings OR general bill types)
                $query->where('member_entry_id', $memberEntryId)
                    ->where(function ($q) use ($meterReadingIds) {
                        $q->whereIn('reference_id', $meterReadingIds)
                            ->orWhereIn('transaction_type', [1, 2, 5]);
                    });

            })
                ->orWhere(function ($q) use ($memberEntryId) {

                    // ALL CR payments
                    $q->where('member_entry_id', $memberEntryId)
                        ->where('direction', 'CR');
                });

        })
            ->whereNotIn('charge_type', [9, 7, 8])
            ->selectRaw("
            SUM(CASE WHEN direction = 'DR' THEN amount ELSE 0 END) -
            SUM(CASE WHEN direction = 'CR' THEN amount ELSE 0 END) AS due
        ")
            ->value('due') ?? 0;

        return max(0, (float) $totalDue);
    }



    /**
     * Get Available Advance Balance
     */
    public function getAvailableAdvance(int $memberEntryId): float
    {
        $cr = CustomerTransaction::where('member_entry_id', $memberEntryId)
            ->where('charge_type', 9)
            ->whereNull('deleted_at')
            ->where('direction', 'CR')
            ->sum('amount');

        $dr = CustomerTransaction::where('member_entry_id', $memberEntryId)
            ->where('charge_type', 9)
            ->whereNull('deleted_at')
            ->where('direction', 'DR')
            ->sum('amount');

        return max(0, round((float) $cr - (float) $dr, 2));
    }
}