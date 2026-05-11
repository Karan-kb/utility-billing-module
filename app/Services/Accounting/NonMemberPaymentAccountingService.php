<?php

namespace App\Services\Accounting;

use App\Models\NonMemberPayment;
use App\Models\Payment;
use App\Services\VoucherSummaryService;
use App\Services\MemberResolverService;
use Illuminate\Support\Facades\DB;

class NonMemberPaymentAccountingService
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
     * Create voucher for Non Member Payment
     */
    public function createVoucher(NonMemberPayment $nonMemberPayment): void
    {
        $memberEntryId = $nonMemberPayment->member_entry_id ?? null;
    
        $payments = Payment::where('reference_id', $nonMemberPayment->id)
            ->where('type', 9) 
            ->get();
    
        $debitEntries = [];
        $totalDebit = 0;
    
        foreach ($payments as $payment) {
            $totalDebit += $payment->amount;
    
            if ($payment->payment_mode == 1) {
                // Cash
                $debitEntries[] = [
                    'account_head_id' => 1,
                    'particulars' => "Cash Received (Non Member Payment {$nonMemberPayment->voucher_no})",
                    'amount' => $payment->amount,
                ];
            }
    
            if ($payment->payment_mode == 2) {
                // Bank
                $debitEntries[] = [
                    'account_head_id' => 2,
                    'particulars' => "Bank Received (Non Member Payment {$nonMemberPayment->voucher_no})",
                    'amount' => $payment->amount,
                ];
            }
        }
    
        if ($totalDebit <= 0 && $nonMemberPayment->demand_charge <= 0) {
            return;
        }
    
        // Credit side
        $creditEntries = [];
    
        $demandCharge = $nonMemberPayment->demand_charge > 0 ? $nonMemberPayment->demand_charge : 0;
        if ($demandCharge > 0) {
            $creditEntries[] = [
                'account_head_id' => 14,
                'particulars' => "Demand Charge ({$nonMemberPayment->voucher_no})",
                'amount' => $demandCharge,
            ];
        }
    
        $nonMemberPaymentCredit = $totalDebit - $demandCharge;
        if ($nonMemberPaymentCredit > 0) {
            $creditEntries[] = [
                'account_head_id' => 12,
                'particulars' => "Non Member Payment ({$nonMemberPayment->voucher_no})",
                'amount' => $nonMemberPaymentCredit,
            ];
        }
    
        $this->voucherService->createVoucher(
            $nonMemberPayment->voucher_no,
            $nonMemberPayment->date_in_ad,
            $nonMemberPayment->date_in_bs,
            $debitEntries,
            $creditEntries,
            12,           // default income account head
            9,            // voucher type
            $memberEntryId
        );
    }
    
}
