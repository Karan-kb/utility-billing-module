<?php

namespace App\Services\VoucherPreview;

use App\Models\NonMemberPayment;
use App\Models\VoucherSummaryDetail;
use App\Models\AccountHead;
use App\Services\PaymentService;
use App\Services\DateConverterService;

class NonMemberPaymentPreviewBuilder
{
    public function build(
        $voucher,
        PaymentService $paymentService,
        DateConverterService $dateService
    ): array {

        $payment = NonMemberPayment::findOrFail($voucher->reference_id);

        $paymentDetails = $paymentService->transformPayments($payment->payments ?? []);

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

            'customer_name_en' => $payment->customer_name_en,
            'customer_name_np' => $payment->customer_name_np,
            'address' => $payment->address,
            'mobile_no' => $payment->mobile_no,

            'consumption_unit' => $payment->consumption_unit,

            'paid_amount' => $paidAmount,
            'total_paid_amount' => $totalPaidAmount,

            'payment_mode' => $paymentDetails,
        ];
    }
}