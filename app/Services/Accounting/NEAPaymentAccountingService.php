<?php

namespace App\Services\Accounting;

use App\Models\NEAPaymentEntry;
use App\Models\Payment;
use App\Services\VoucherSummaryService;
use Illuminate\Support\Facades\DB;

class NEAPaymentAccountingService
{
    protected VoucherSummaryService $voucherService;

    public function __construct(VoucherSummaryService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    /**
     * Create voucher for NEA Payment
     */
    public function createVoucher(NEAPaymentEntry $neaPayment): void
    {
        $payments = Payment::where('reference_id', $neaPayment->id)
            ->where('type', 10) // payment type = 10
            ->get();

        $debitEntries = [];
        $totalDebit = 0;

        /** -------------------------
         * Debit: Cash / Bank
         * ------------------------- */
        foreach ($payments as $payment) {
            $totalDebit += $payment->amount;

            if ($payment->payment_mode == 1) {
                // Cash
                $debitEntries[] = [
                    'account_head_id' => 1,
                    'particulars' => "Cash Paid (NEA Payment {$neaPayment->voucher_no})",
                    'amount' => $payment->amount,
                ];
            }

            if ($payment->payment_mode == 2) {
                // Bank
                $debitEntries[] = [
                    'account_head_id' => 2,
                    'particulars' => "Bank Paid (NEA Payment {$neaPayment->voucher_no})",
                    'amount' => $payment->amount,
                ];
            }
        }

        /** -------------------------
         * Debit: Rebate
         * ------------------------- */
        if ($neaPayment->rebate_amount > 0) {
            $debitEntries[] = [
                'account_head_id' => 23,
                'particulars' => "Rebate Discount (NEA Payment {$neaPayment->voucher_no})",
                'amount' => $neaPayment->rebate_amount,
            ];

            $totalDebit += $neaPayment->rebate_amount;
        }

        if ($totalDebit <= 0) {
            return;
        }

        /** -------------------------
         * Credit side
         * ------------------------- */
        $creditEntries = [];

        // Credit: Fine Income
        if ($neaPayment->fine_amount > 0) {
            $creditEntries[] = [
                'account_head_id' => 13,
                'particulars' => "Fine Income (NEA Payment {$neaPayment->voucher_no})",
                'amount' => $neaPayment->fine_amount,
            ];
        }

        // Credit: NEA Payment (Balancing)
        $neaPaymentCredit = $totalDebit - ($neaPayment->fine_amount ?? 0);

        if ($neaPaymentCredit > 0) {
            $creditEntries[] = [
                'account_head_id' => 21,
                'particulars' => "NEA Payment ({$neaPayment->voucher_no})",
                'amount' => $neaPaymentCredit,
            ];
        }

        /** -------------------------
         * Create Voucher
         * ------------------------- */
        $this->voucherService->createVoucher(
            $neaPayment->voucher_no,
            $neaPayment->date_in_ad,
            $neaPayment->date_in_bs,
            $debitEntries,
            $creditEntries,
            21,    // default NEA payment head
            10,    // voucher type = 10
            null   // no member_entry_id
        );
    }
}
