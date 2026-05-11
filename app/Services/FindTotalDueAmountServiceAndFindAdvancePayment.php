<?php

namespace App\Services;

use App\Models\CustomerTransaction;
use App\Models\MahasulReceiptEntry;
use App\Models\MeterIssue;

class FindTotalDueAmountServiceAndFindAdvancePayment
{
    /**
     * Get total due amount for a meter_issue_id
     *
     * Logic:
     * Total Due = Total DR - Total CR
     */
    public function getTotalDueAmount(int $meterIssueId): float
    {
        $memberEntryId = $this->getMemberEntryId($meterIssueId);

        if (!$memberEntryId) {
            return 0;
        }

        $totalDr = CustomerTransaction::where('member_entry_id', $memberEntryId)
            ->where('meter_issue_id', $meterIssueId)
            ->whereNull('deleted_at')
            ->where('direction', 'DR')
            ->sum('amount');

        $totalCr = CustomerTransaction::where('member_entry_id', $memberEntryId)
            ->where('meter_issue_id', $meterIssueId)
            ->whereNull('deleted_at')
            ->where('direction', 'CR')
            ->sum('amount');

        return round(max(0, (float) $totalDr - (float) $totalCr), 2);
    }

    /**
     * Get total advance payment for a meter_issue_id
     *
     * Logic:
     * Advance = CR - DR where charge_type = 9
     */
    public function getTotalAdvancePayment(int $meterIssueId): float
    {
        $memberEntryId = $this->getMemberEntryId($meterIssueId);

        if (!$memberEntryId) {
            return 0;
        }

        $cr = CustomerTransaction::where('member_entry_id', $memberEntryId)
            ->where('meter_issue_id', $meterIssueId)
            ->where('charge_type', 9) // advance
            ->whereNull('deleted_at')
            ->where('direction', 'CR')
            ->sum('amount');

        $dr = CustomerTransaction::where('member_entry_id', $memberEntryId)
            ->where('meter_issue_id', $meterIssueId)
            ->where('charge_type', 9)
            ->whereNull('deleted_at')
            ->where('direction', 'DR')
            ->sum('amount');

        return round(max(0, (float) $cr - (float) $dr), 2);
    }

    /**
     * Get previous due from last receipt (unchanged)
     */
    public function getPreviousDueFromLastReceipt(int $meterIssueId): float
    {
        $lastReceipt = MahasulReceiptEntry::withoutTrashed()
            ->where('is_cancel', 0)
            ->where('meter_issue_id', $meterIssueId)
            ->latest('id')
            ->first(['total_due_amount']);

        return (float) ($lastReceipt->total_due_amount ?? 0);
    }

    /**
     * Helper: get member_entry_id from meter_issue_id
     */
    private function getMemberEntryId(int $meterIssueId): ?int
    {
        return MeterIssue::where('id', $meterIssueId)
            ->value('member_entry_id');
    }


    public function getTotalDueAmountByMemberEntry(int $memberEntryId): float
{
    if (!$memberEntryId) {
        return 0;
    }

    $totalDr = CustomerTransaction::where('member_entry_id', $memberEntryId)
        ->whereNull('deleted_at')
        ->where('direction', 'DR')
        ->sum('amount');

    $totalCr = CustomerTransaction::where('member_entry_id', $memberEntryId)
        ->whereNull('deleted_at')
        ->where('direction', 'CR')
        ->sum('amount');

    return round(max(0, (float) $totalDr - (float) $totalCr), 2);
}
}