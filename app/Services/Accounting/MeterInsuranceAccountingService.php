<?php

namespace App\Services\Accounting;

use App\Models\MeterInsurance;
use App\Models\Payment;
use App\Services\VoucherSummaryService;
use App\Services\MemberResolverService;
use Illuminate\Support\Facades\DB;

class MeterInsuranceAccountingService
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
     * Create voucher for Meter Insurance
     */
    public function createVoucher(MeterInsurance $insurance): void
    {
        $memberEntryId = $this->memberResolver
            ->getMemberEntryIdFromMeterIssue($insurance->meter_issue_id) ?? 0;

        // Fetch actual payments for this insurance
        $payments = Payment::where('reference_id', $insurance->id)
            ->where('type', 5) // Meter Insurance payment type
            ->get();

        $debitEntries = [];
        $totalReceived = 0;

        foreach ($payments as $payment) {
            $totalReceived += $payment->amount;

            if ($payment->payment_mode == 1) {
                // Cash
                $debitEntries[] = [
                    'account_head_id' => 1,
                    'particulars' => "Cash Received (Meter Insurance {$insurance->voucher_no})",
                    'amount' => $payment->amount,
                ];
            }

            if ($payment->payment_mode == 2) {
                // Bank
                $debitEntries[] = [
                    'account_head_id' => 2,
                    'particulars' => "Bank Received (Meter Insurance {$insurance->voucher_no})",
                    'amount' => $payment->amount,
                ];
            }
        }

        if ($totalReceived <= 0) {
            // No payments, skip voucher creation
            return;
        }

        $creditEntries = [[
            'account_head_id' => 19,
            'particulars' => "Meter Insurance ({$insurance->voucher_no})",
            'amount' => $totalReceived,
        ]];

        $this->voucherService->createVoucher(
            $insurance->voucher_no,
            $insurance->date_in_ad,
            $insurance->date_in_bs,
            $debitEntries,
            $creditEntries,
            19,
            5, // Meter Insurance voucher type
            $memberEntryId
        );
    }
}
