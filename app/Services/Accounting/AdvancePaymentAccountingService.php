<?php

namespace App\Services\Accounting;

use App\Models\AdvancePayment;
use App\Models\Payment;
use App\Services\VoucherSummaryService;
use App\Services\MemberResolverService;
use Illuminate\Support\Facades\DB;

class AdvancePaymentAccountingService
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
     * Create voucher for Advance Payment
     */
    public function createVoucher(AdvancePayment $advancePayment): void
    {
        $memberEntryId = $this->memberResolver
            ->getMemberEntryIdFromMeterIssue($advancePayment->meter_issue_id) ?? 0;

        $payments = Payment::where('reference_id', $advancePayment->id)
            ->where('type', 4) 
            ->get();

        $debitEntries = [];
        $totalReceived = 0;

        foreach ($payments as $payment) {
            $totalReceived += $payment->amount;

            if ($payment->payment_mode == 1) {
                // Cash
                $debitEntries[] = [
                    'account_head_id' => 1,
                    'particulars' => "Cash Received (Advance Payment {$advancePayment->voucher_no})",
                    'amount' => $payment->amount,
                ];
            }

            if ($payment->payment_mode == 2) {
                // Bank
                $debitEntries[] = [
                    'account_head_id' => 2,
                    'particulars' => "Bank Received (Advance Payment {$advancePayment->voucher_no})",
                    'amount' => $payment->amount,
                ];
            }
        }

        if ($totalReceived <= 0) {
            // No payments, skip voucher creation
            return;
        }

        $creditEntries = [[
            'account_head_id' => 9,
            'particulars' => "Advance Payment ({$advancePayment->voucher_no})",
            'amount' => $totalReceived,
        ]];

        $this->voucherService->createVoucher(
            $advancePayment->voucher_no,
            $advancePayment->date_in_ad,
            $advancePayment->date_in_bs,
            $debitEntries,
            $creditEntries,
            9,
            9, // Advance Payment voucher type
            $memberEntryId
        );
    }
}
