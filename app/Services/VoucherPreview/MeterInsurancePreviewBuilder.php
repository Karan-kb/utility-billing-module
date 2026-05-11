<?php

namespace App\Services\VoucherPreview;

use App\Models\MeterInsurance;
use App\Models\VoucherSummaryDetail;
use App\Services\MemberInfoFromMeterIssueService;
use App\Services\MemberResolverService;
use App\Services\PaymentService;
use App\Services\DateConverterService;
class MeterInsurancePreviewBuilder
{
    public function build(
        $voucher,
        MemberInfoFromMeterIssueService $memberInfoService,
        PaymentService $paymentService,
         DateConverterService $dateService
    ): array {

       
        $insurance = MeterInsurance::findOrFail($voucher->reference_id);

     
        $memberInfo = $memberInfoService->getMemberInfo($insurance->meter_issue_id);

        $memberResolver = app(MemberResolverService::class);
        $memberId = $memberResolver->getMemberEntryIdFromMeterIssue(
            $insurance->meter_issue_id
        );

      
        $paymentDetails = $paymentService->transformPayments($insurance->payments);

     
        $insuranceAmount = VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)
            ->where('account_head_id', 19)
            ->sum('credit');

       
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

            'paid_amount' => $insuranceAmount,

            'payment_mode' => $paymentDetails,
        ];
    }
}