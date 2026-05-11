<?php

namespace App\Services\VoucherPreview;

use App\Models\ShareTransaction;
use App\Models\VoucherSummaryDetail;
use App\Services\MemberInfoFromMeterIssueService;
use App\Services\PaymentService;
use App\Services\DateConverterService;
use App\Models\AccountHead;
class SharePreviewBuilder
{
    public function build(
        $voucher,
        MemberInfoFromMeterIssueService $memberInfoService,
        PaymentService $paymentService,
        DateConverterService $dateService
    ): array {

        $share = ShareTransaction::findOrFail($voucher->reference_id);
        $memberInfo = $memberInfoService->getMemberInfoFromMemberEntryId($share->member_entry_id);
        $paymentDetails = $paymentService->transformPayments($share->payments ?? []);

        $paidAmount = [];
        $totalPaidAmount = 0;

        if ($voucher->reference_type == 7) { // Share Entry
            $shareAmount = VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)
                ->where('account_head_id', 7)
                ->sum('credit');

            $shareHead = AccountHead::find(7);

            $paidAmount = [
                ($shareHead->name ?? 'Share Amount') => $shareAmount
            ];

            $shareKey = $shareHead->name ?? 'Share Amount';
            $totalPaidAmount = $paidAmount[$shareKey] ?? 0;
        }

        if ($voucher->reference_type == 8) { // Share Return
            $shareAmount = VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)
                ->where('account_head_id', 7)
                ->sum('debit');

            $serviceCharge = VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)
                ->where('account_head_id', 17)
                ->sum('credit');

            $shareHead = AccountHead::find(7);
            $serviceHead = AccountHead::find(17);

            $paidAmount = [
                ($shareHead->name ?? 'Share Amount') => $shareAmount,
                ($serviceHead->name ?? 'Service Charge') => $serviceCharge
            ];

            $shareKey = $shareHead->name ?? 'Share Amount';
            $serviceKey = $serviceHead->name ?? 'Service Charge';

            $totalPaidAmount =
                ($paidAmount[$shareKey] ?? 0)
                - ($paidAmount[$serviceKey] ?? 0);
            $totalPaidAmount = $shareAmount - $serviceCharge;
        }

        return [
            'voucher_id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,

            'date_in_ad' => \Carbon\Carbon::parse($voucher->date)->format('Y-m-d'),
            'date_in_bs' => $dateService->adToBs($voucher->date),

            'reference_type' => config('voucher.' . $voucher->reference_type),
            'reference_id' => $voucher->reference_id,
            'is_cancel' => $voucher->is_cancel,

            'member_entry_id' => $share->member_entry_id,
            'member_no' => $memberInfo['member_no'],
            'customer_name_en' => $memberInfo['customer_name_en'],
            'customer_name_np' => $memberInfo['customer_name_np'],
            'pan_no' => $memberInfo['pan_no'],

            // Share Info from ShareTransaction
            'share_quantity' => $share->share_quantity,
            'share_value' => $share->share_value,
            'paid_amount' => $paidAmount,
            'total_paid_amount' => $totalPaidAmount,

            'payment_mode' => $paymentDetails,
        ];
    }
}