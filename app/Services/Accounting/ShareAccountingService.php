<?php

namespace App\Services\Accounting;

use App\Models\ShareTransaction;
use App\Models\Payment;
use App\Services\VoucherSummaryService;
use App\Services\MemberResolverService;
use Illuminate\Support\Facades\DB;

class ShareAccountingService
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

    public function createVoucherEntries(ShareTransaction $transaction, int $transactionType): void
    {
        DB::connection('tenant')->transaction(function () use ($transaction, $transactionType) {

            $memberEntryId = $transaction->member_entry_id;

            $debitEntries = [];
            $creditEntries = [];

            /** -----------------------------
             * PAYMENTS (Cash / Bank)
             * ----------------------------- */
            $payments = Payment::where('reference_id', $transaction->id)
                ->whereIn('type', [7, 8])
                ->get();

            foreach ($payments as $payment) {
                if ($transactionType === 0) {
                    // Share Entry → Debit Cash/Bank
                    $debitEntries[] = [
                        'account_head_id' => $payment->payment_mode == 1 ? 1 : 2,
                        'particulars' => ($payment->payment_mode == 1 ? 'Cash' : 'Bank') . ' Received (Share Entry)',
                        'amount' => $payment->amount,
                    ];
                } else {
                    // Share Return → Credit Cash/Bank
                    $creditEntries[] = [
                        'account_head_id' => $payment->payment_mode == 1 ? 1 : 2,
                        'particulars' => ($payment->payment_mode == 1 ? 'Cash' : 'Bank') . ' Returned (Share Return)',
                        'amount' => $payment->amount,
                    ];
                }
            }

            /** -----------------------------
             * SHARE ACCOUNT (ACCOUNT 7)
             * ----------------------------- */
            if ($transactionType === 0) {
                // Share Entry → Credit Share Account
                $creditEntries[] = [
                    'account_head_id' => 7,
                    'particulars' => 'Share Account (Entry)',
                    'amount' => $transaction->amount,
                ];
            } else {
                // Share Return → Debit Share Account
                $debitEntries[] = [
                    'account_head_id' => 7,
                    'particulars' => 'Share Account (Return)',
                    'amount' => $transaction->return_amount,
                ];
            }

            /** -----------------------------
             * SERVICE CHARGE (RETURN ONLY)
             * ----------------------------- */
            if ($transactionType === 1 && ($transaction->service_charge ?? 0) > 0) {
                $creditEntries[] = [
                    'account_head_id' => 17,
                    'particulars' => 'Service Charge (Share Return)',
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
                7, // Main Share Account
                $transactionType === 0 ? 7 : 8,
                $memberEntryId
            );
        });
    }
}
