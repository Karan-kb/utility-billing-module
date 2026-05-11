<?php

namespace App\Services\Accounting;

use App\Models\OtherIncomeReceipt;
use App\Models\OtherIncomeReceiptContent;
use App\Models\OtherIncomeSetup;
use App\Models\Payment;
use App\Services\VoucherSummaryService;
use App\Services\MemberResolverService;

class OtherIncomeReceiptAccountingService
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
     * Create voucher for Other Income Receipt
     */
    public function createVoucher(OtherIncomeReceipt $receipt): void
    {
        $memberEntryId = $this->memberResolver
            ->getMemberEntryIdFromMeterIssue($receipt->meter_issue_id) ?? 0;

        // Fetch payments for this receipt
        $payments = Payment::where('reference_id', $receipt->id)
            ->where('type', 6) // Other Income payment type
            ->get();

        $debitEntries = [];
        $totalReceived = 0;

        foreach ($payments as $payment) {
            $totalReceived += $payment->amount;

            // Cash
            if ($payment->payment_mode == 1) {
                $debitEntries[] = [
                    'account_head_id' => 1,
                    'particulars' => "Cash Received (Other Income {$receipt->voucher_no})",
                    'amount' => $payment->amount,
                ];
            }

            // Bank
            if ($payment->payment_mode == 2) {
                $debitEntries[] = [
                    'account_head_id' => 2,
                    'particulars' => "Bank Received (Other Income {$receipt->voucher_no})",
                    'amount' => $payment->amount,
                ];
            }
        }

        if ($totalReceived <= 0) {
            // No payment → no voucher
            return;
        }

        /**
         * CREDIT ENTRIES
         * Each income head from receipt contents
         */
        $creditEntries = [];

        $contents = OtherIncomeReceiptContent::with('otherIncomeSetup')
            ->where('other_income_receipt_id', $receipt->id)
            ->get();

        foreach ($contents as $content) {
            $setup = OtherIncomeSetup::find($content->other_income_setup_id);

            if (!$setup || !$setup->account_head_id) {
                continue;
            }

            $creditEntries[] = [
                'account_head_id' => $setup->account_head_id,
                'particulars' => "{$setup->income_head_en} ({$receipt->voucher_no})",
                'amount' => $content->amount,
            ];
        }

        if (empty($creditEntries)) {
            return;
        }

        /**
         * Create Voucher
         */
        $this->voucherService->createVoucher(
            $receipt->voucher_no,
            $receipt->date_in_ad,
            $receipt->date_in_bs,
            $debitEntries,
            $creditEntries,
            null,          // No single account head (multiple credits)
            6,             // Voucher type
            $memberEntryId
        );
    }
}
