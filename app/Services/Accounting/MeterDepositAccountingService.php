<?php

namespace App\Services\Accounting;

use App\Models\MeterDepositTransaction;
use App\Models\Payment;
use App\Services\VoucherSummaryService;
use App\Services\MemberResolverService;
use Illuminate\Support\Facades\DB;
class MeterDepositAccountingService
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

    public function createVoucher(MeterDepositTransaction $transaction): void
    {
        DB::connection('tenant')->transaction(function () use ($transaction) {

            $memberEntryId = $this->memberResolver
                ->getMemberEntryIdFromMeterIssue($transaction->meter_issue_id);

            $debitEntries = [];
            $creditEntries = [];

            /** -----------------------------
             * PAYMENTS (Cash / Bank)
             * ----------------------------- */
            $payments = Payment::where('reference_id', $transaction->id)
                ->whereIn('type', [1, 3])
                ->get();

            foreach ($payments as $payment) {
                if ($transaction->transaction_type === 0) {
                    // Deposit → Debit Cash/Bank
                    $debitEntries[] = [
                        'account_head_id' => $payment->payment_mode == 1 ? 1 : 2,
                        'particulars' => ($payment->payment_mode == 1 ? 'Cash' : 'Bank') . ' Received (Deposit)',
                        'amount' => $payment->amount,
                    ];
                } else {
                    // Return → Credit Cash/Bank
                    $creditEntries[] = [
                        'account_head_id' => $payment->payment_mode == 1 ? 1 : 2,
                        'particulars' => ($payment->payment_mode == 1 ? 'Cash' : 'Bank') . ' Returned (Deposit)',
                        'amount' => $payment->amount,
                    ];
                }
            }

            /** -----------------------------
             * METER DEPOSIT (ACCOUNT 8)
             * ----------------------------- */
            if ($transaction->transaction_type === 0) {
                // Deposit
                $creditEntries[] = [
                    'account_head_id' => 8,
                    'particulars' => 'Meter Deposit Received',
                    'amount' => $transaction->amount,
                ];
            } else {
                // Return → ONLY DEBIT ONCE
                $debitEntries[] = [
                    'account_head_id' => 8,
                    'particulars' => 'Meter Deposit Returned',
                    'amount' => $transaction->amount,
                ];
            }

            /** -----------------------------
             * SERVICE CHARGE (RETURN ONLY)
             * ----------------------------- */
            if (
                $transaction->transaction_type === 1 &&
                ($transaction->service_charge ?? 0) > 0
            ) {
                $creditEntries[] = [
                    'account_head_id' => 17,
                    'particulars' => 'Service Charge (Deposit Return)',
                    'amount' => $transaction->service_charge,
                ];
            }

            /** -----------------------------
             * CREATE VOUCHER
             * ----------------------------- */
            $this->voucherService->createVoucher(
                $transaction->voucher_no,
                $transaction->date_in_ad,
                $transaction->date_in_bs,
                $debitEntries,
                $creditEntries,
                8, // Meter Deposit main account
                $transaction->transaction_type === 0 ? 1 : 3,
                $memberEntryId
            );
        });
    }
}
