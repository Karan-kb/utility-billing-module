<?php

namespace App\Services\VoucherPreview;

use App\Models\MahasulReceiptEntry;
use App\Models\VoucherSummary;
use App\Models\VoucherSummaryDetail;
use App\Services\DateConverterService;
use App\Services\MemberInfoFromMeterIssueService;
use App\Services\MemberResolverService;
use App\Services\PaymentService;

class MahasulPreviewBuilder
{
    public function build(
        VoucherSummary $voucher,
        MemberInfoFromMeterIssueService $memberInfoService,
        PaymentService $paymentService,
         DateConverterService $dateService
    ): array {

      
        $receipt = MahasulReceiptEntry::withoutTrashed()
            ->with(['payments.bank'])
            ->findOrFail($voucher->reference_id);

        $memberInfo = $memberInfoService
            ->getMemberInfo($receipt->meter_issue_id);

        $memberResolver = app(MemberResolverService::class);

        $memberId = $memberResolver
            ->getMemberEntryIdFromMeterIssue(
                $receipt->meter_issue_id
            );

      
        $paymentDetails = $paymentService
            ->transformPayments($receipt->payments);

        
        $accountHeadMap = [
            20 => ['key' => 'black_list_charge', 'type' => 'credit'],
            13 => ['key' => 'fine_amount', 'type' => 'credit'],
            14 => ['key' => 'demand_charge', 'type' => 'credit'],
            15 => ['key' => 'other_charge', 'type' => 'credit'],
            16 => ['key' => 'subsidy_charge', 'type' => 'credit'],
            17 => ['key' => 'service_charge', 'type' => 'credit'],
            11 => ['key' => 'unit_amount', 'type' => 'credit'],
            22 => ['key' => 'discount_amount', 'type' => 'debit'],
            23 => ['key' => 'rebate_amount', 'type' => 'debit'],
            9 => ['key' => 'advance_payment', 'type' => 'credit'],
        ];

        $voucherDetails = VoucherSummaryDetail::where(
            'voucher_summary_id',
            $voucher->id
        )
            ->whereIn(
                'account_head_id',
                array_keys($accountHeadMap)
            )
            ->get();

        $paidChargeTypes = [];

        foreach ($voucherDetails as $detail) {

            $map = $accountHeadMap[$detail->account_head_id];

            $amount = $map['type'] == 'credit'
                ? $detail->credit
                : $detail->debit;

            if ($amount > 0) {
                $paidChargeTypes[$map['key']] =
                    ($paidChargeTypes[$map['key']] ?? 0)
                    + $amount;
            }
        }

       
        $priorityOrder = [
            'black_list_charge',
            'fine_amount',
            'demand_charge',
            'other_charge',
            'subsidy_charge',
            'service_charge',
            'unit_amount',
        ];

        $dueAmount = [];
        $remainingPaid = array_sum($paidChargeTypes);

        foreach ($priorityOrder as $key) {

            $actual = (float) $receipt->$key;

            if ($actual <= 0)
                continue;

            if ($remainingPaid >= $actual) {

                $remainingPaid -= $actual;

            } else {

                $due = $actual - $remainingPaid;

                if ($due > 0) {
                    $dueAmount[$key] = round($due, 2);
                }

                $remainingPaid = 0;
            }
        }

        /*
        |---------------------------------------------------------
        | Response
        |---------------------------------------------------------
        */
        return [

            'voucher_id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'date_in_ad' => \Carbon\Carbon::parse($voucher->date)->format('Y-m-d'),
            'date_in_bs' => $dateService->adToBs($voucher->date),
            //'reference_type' => $voucher->reference_type,
            'reference_type' =>
                config('voucher.' . $voucher->reference_type),

            'reference_id' => $receipt->id,
            'is_cancel' => $receipt->is_cancel,

            'member_entry_id' => $memberId,
            'member_no' => $memberInfo['member_no'],
            'customer_name_en' =>
                $memberInfo['customer_name_en'],
            'customer_name_np' =>
                $memberInfo['customer_name_np'],
            'pan_no' =>
                $memberInfo['pan_no'],
            'meter_no' =>
                $memberInfo['meter_no'],

            'paid_amount' => $paidChargeTypes,
            'due_amount' => $dueAmount,

            'total_paid_amount' =>
                array_sum($paidChargeTypes),
            'total_due_amount' =>
                array_sum($dueAmount),

            'payment_mode' => $paymentDetails,
        ];
    }
}
