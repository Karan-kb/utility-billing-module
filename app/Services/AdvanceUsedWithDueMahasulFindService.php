<?php

namespace App\Services;

use App\Http\Controllers\Inventory\VoucherSummaryController;
use App\Models\MahasulReceiptEntry;
use App\Http\Controllers\VoucherController;

class AdvanceUsedWithDueMahasulFindService
{
    /**
     * Cancel previous Mahasul voucher if a previous receipt exists
     * with advance_status = 2 for the same meter_issue_id
     *
     * @param MahasulReceiptEntry $currentReceipt
     * @param string $reasonForCancellation
     * @return void
     */
    public function cancelPreviousIfAdvanceStatusTwo(MahasulReceiptEntry $currentReceipt, string $reasonForCancellation = '')
    {
        // Find previous Mahasul receipt (non-cancelled, non-soft-deleted) with advance_status = 2
        $previousReceipt = MahasulReceiptEntry::where('meter_issue_id', $currentReceipt->meter_issue_id)
            ->where('id', '<', $currentReceipt->id)
            ->where('is_cancel', 0)
            ->where('advance_status', 2)
            ->orderBy('id', 'desc')
            ->first();

        if (!$previousReceipt) {
            return; // nothing to cancel
        }

        // Find associated voucher for that previous receipt
        $prevVoucher = \App\Models\VoucherSummary::where('reference_type', 11)
            ->where('reference_id', $previousReceipt->id)
            ->where('status', '!=', 3) // not already cancelled
            ->first();

        if (!$prevVoucher) {
            return; // no voucher to cancel
        }

        // Cancel the previous voucher using the existing VoucherController logic
        $voucherController = new VoucherSummaryController();

        $voucherController->voucherCancel(new \Illuminate\Http\Request([
            'reason' => $reasonForCancellation ?: 'Cancelled due to cancellation of subsequent Mahasul receipt voucher for meter_issue_id ' . $currentReceipt->meter_issue_id,
        ]), $prevVoucher->id);
    }
}