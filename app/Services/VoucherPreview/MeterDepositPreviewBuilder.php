<?php

namespace App\Services\VoucherPreview;

use App\Models\MeterDepositTransaction;
use App\Models\VoucherSummaryDetail;
use App\Services\MemberInfoFromMeterIssueService;
use App\Services\MemberResolverService;
use App\Services\PaymentService;
use App\Services\DateConverterService;
use App\Models\AccountHead;
class MeterDepositPreviewBuilder
{
    public function build(
        $voucher,
        MemberInfoFromMeterIssueService $memberInfoService,
        PaymentService $paymentService,
        DateConverterService $dateService
    ): array {

      
        $transaction = MeterDepositTransaction::findOrFail($voucher->reference_id);

       
        $memberInfo = $memberInfoService->getMemberInfo($transaction->meter_issue_id);

        $memberResolver = app(MemberResolverService::class);
        $memberId = $memberResolver->getMemberEntryIdFromMeterIssue(
            $transaction->meter_issue_id
        );

      
        $paymentDetails = $paymentService->transformPayments($transaction->payments ?? []);

        
        $paidAmount = [];

        // Deposit Entry (1)
        if ($voucher->reference_type == 1) {

            $deposit = VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)
                ->where('account_head_id', 8)
                ->sum('credit');
         $depositHead = AccountHead::find(8);

           $paidAmount = [
            ($depositHead->name ?? 'Meter Deposit') => $deposit
        ];
        }

        // Upgrade (2) & Return (3)
       if ($voucher->reference_type == 2) {

            $deposit = VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)
                ->where('account_head_id', 8)
                ->sum('credit');

            $serviceCharge = VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)
                ->where('account_head_id', 17)
                ->sum('credit');

           $depositHead = AccountHead::find(8);
            $serviceHead = AccountHead::find(17);

            $paidAmount = [
                ($depositHead->name ?? 'Meter Deposit') => $deposit,
                ($serviceHead->name ?? 'Service Charge') => $serviceCharge
            ];
        }
        if ($voucher->reference_type == 3) {

            $deposit = VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)
                ->where('account_head_id', 8)
                ->sum('debit');

            $serviceCharge = VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)
                ->where('account_head_id', 17)
                ->sum('credit');

                  $depositHead = AccountHead::find(8);
                $serviceHead = AccountHead::find(17);

                $paidAmount = [
                    ($depositHead->name ?? 'Meter Deposit') => $deposit,
                    ($serviceHead->name ?? 'Service Charge') => $serviceCharge
                ];
        }
$totalPaidAmount = 0;

// Deposit Entry 
if ($voucher->reference_type == 1) {
    $depositKey = $depositHead->name ?? 'Meter Deposit';
    $totalPaidAmount = $paidAmount[$depositKey] ?? 0;
}

// Return → deposit - service charge
elseif ($voucher->reference_type == 3) {
    $depositKey = $depositHead->name ?? 'Meter Deposit';
    $serviceKey = $serviceHead->name ?? 'Service Charge';

    $totalPaidAmount =
        ($paidAmount[$depositKey] ?? 0)
        - ($paidAmount[$serviceKey] ?? 0);
}

// Upgrade → sum all
elseif ($voucher->reference_type == 2) {
    $totalPaidAmount = array_sum($paidAmount);
}
       
        return [
            'voucher_id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,

            'date_in_ad' => \Carbon\Carbon::parse($voucher->date)->format('Y-m-d'),
            'date_in_bs' => $dateService->adToBs($voucher->date),

            'reference_type' => config('voucher.' . $voucher->reference_type),
            'reference_id' => $voucher->reference_id,
            'is_cancel' => $voucher->is_cancel,

            'member_entry_id' => $memberId,
            'member_no' => $memberInfo['member_no'],
            'customer_name_en' => $memberInfo['customer_name_en'],
            'customer_name_np' => $memberInfo['customer_name_np'],
            'pan_no' => $memberInfo['pan_no'],
            'meter_no' => $memberInfo['meter_no'],

            'paid_amount' => $paidAmount,
           'total_paid_amount' => $totalPaidAmount,

            'payment_mode' => $paymentDetails,
        ];
    }
}