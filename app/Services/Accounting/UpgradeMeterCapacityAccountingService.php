<?php

namespace App\Services\Accounting;

use App\Models\UpgradeMeterCapacity;
use App\Models\Payment;
use App\Services\VoucherSummaryService;
use App\Services\MemberResolverService;

class UpgradeMeterCapacityAccountingService
{
    protected VoucherSummaryService $voucherService;
    protected MemberResolverService $memberResolver;

    public function __construct(
        VoucherSummaryService $voucherService,
        MemberResolverService $memberResolver
    ) {
        $this->voucherService = $voucherService;
        $this->memberResolver = $memberResolver;
    }

    /**
     * Create voucher for Upgrade Meter Capacity
     */
    public function createVoucher(UpgradeMeterCapacity $upgrade): void
    {
        $memberEntryId = $this->memberResolver
            ->getMemberEntryIdFromMeterIssue($upgrade->meter_issue_id) ?? 0;

        // Fetch payments for this upgrade
        $payments = Payment::where('reference_id', $upgrade->id)
            ->where('type', 2) // Upgrade Meter Capacity payment type
            ->get();

        $debitEntries = [];
        $totalReceived = 0;

        foreach ($payments as $payment) {
            $totalReceived += $payment->amount;

            if ($payment->payment_mode == 1) {
                // Cash
                $debitEntries[] = [
                    'account_head_id' => 1, // Cash account
                    'particulars' => "Cash Received (Upgrade Meter Capacity {$upgrade->voucher_no})",
                    'amount' => $payment->amount,
                ];
            }

            if ($payment->payment_mode == 2) {
                // Bank
                $debitEntries[] = [
                    'account_head_id' => 2, // Bank account
                    'particulars' => "Bank Received (Upgrade Meter Capacity {$upgrade->voucher_no})",
                    'amount' => $payment->amount,
                ];
            }
        }

        // Always include charge_amount and deposit_amount as credit
        $creditEntries = [];

        if ((float) $upgrade->charge_amount > 0) {
            $creditEntries[] = [
                'account_head_id' => 17, // Upgrade Meter Capacity account
                'particulars' => "Upgrade Meter Capacity (Charge) {$upgrade->voucher_no}",
                'amount' => (float) $upgrade->charge_amount,
            ];
        }

        if ((float) $upgrade->deposit_amount > 0) {
            $creditEntries[] = [
                'account_head_id' => 8, // Meter Deposit account
                'particulars' => "Meter Deposit (Deposit Amount) {$upgrade->voucher_no}",
                'amount' => (float) $upgrade->deposit_amount,
            ];
        }

        // Skip voucher if nothing to post
        if (empty($debitEntries) && empty($creditEntries)) {
            return;
        }

        $this->voucherService->createVoucher(
            $upgrade->voucher_no,
            $upgrade->date_in_ad,
            $upgrade->date_in_bs,
            $debitEntries,
            $creditEntries,
            17,  // Head for Upgrade Meter Capacity (main credit head)
            2,   // Voucher type for Upgrade Meter Capacity
            $memberEntryId
        );
    }
}
