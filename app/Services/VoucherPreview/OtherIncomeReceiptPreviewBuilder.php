<?php

namespace App\Services\VoucherPreview;

use App\Models\OtherIncomeReceipt;
use App\Models\VoucherSummaryDetail;
use App\Models\AccountHead;
use App\Services\MemberInfoFromMeterIssueService;
use App\Services\PaymentService;
use App\Services\DateConverterService;
use App\Services\MemberResolverService;

class OtherIncomeReceiptPreviewBuilder
{
    public function build(
        $voucher,
        MemberInfoFromMeterIssueService $memberInfoService,
        PaymentService $paymentService,
        DateConverterService $dateService
    ): array {

        $receipt = OtherIncomeReceipt::findOrFail($voucher->reference_id);

        $memberInfo = $memberInfoService->getMemberInfo($receipt->meter_issue_id);

        $memberResolver = app(MemberResolverService::class);
        $memberId = $memberResolver->getMemberEntryIdFromMeterIssue(
            $receipt->meter_issue_id
        );

        $paymentDetails = $paymentService->transformPayments($receipt->payments);

        $creditDetails = VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)
            ->where('credit', '>', 0)
            ->get();

        $accountHeads = AccountHead::whereIn(
            'id',
            $creditDetails->pluck('account_head_id')
        )->get()->keyBy('id');

        $paidAmount = [];

        foreach ($creditDetails as $detail) {

            $accountHead = $accountHeads[$detail->account_head_id] ?? null;

            $key = $accountHead ? $accountHead->name : 'Unknown';

            // Sum if duplicate
            if (isset($paidAmount[$key])) {
                $paidAmount[$key] += $detail->credit;
            } else {
                $paidAmount[$key] = $detail->credit;
            }
        }

        $totalPaidAmount = array_sum($paidAmount);

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