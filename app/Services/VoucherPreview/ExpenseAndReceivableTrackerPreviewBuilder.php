<?php

namespace App\Services\VoucherPreview;

use App\Models\ExpenseAndReceivableTracker;
use App\Models\AccountHead;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\DateConverterService;

class ExpenseAndReceivableTrackerPreviewBuilder
{
    public function build(
        $voucher,
        PaymentService $paymentService,
        DateConverterService $dateService
    ): array {

        $tracker = ExpenseAndReceivableTracker::with('items')->findOrFail($voucher->reference_id);

        $accountHeadIds = $tracker->items->pluck('account_head_id')->unique();
        $bankIds = $tracker->items->pluck('bank_id')->filter()->unique();

        $accountHeads = AccountHead::whereIn('id', $accountHeadIds)
            ->get()
            ->keyBy('id');

        $banks = AccountHead::whereIn('id', $bankIds)
            ->get()
            ->keyBy('id');

        $payments = Payment::where('reference_id', $tracker->id)
            ->where('type', 12) // expense tracker type
            ->whereIn('bank_id', $bankIds)
            ->get()
            ->keyBy('bank_id'); // keyed by bank_id for matching

        $items = $tracker->items->map(function ($item) use ($accountHeads, $banks, $payments) {

            $accountHeadName = $accountHeads[$item->account_head_id]->name ?? 'Unknown';

            $itemData = [
                'id' => $item->id,
                'account_head_id' => $item->account_head_id,
                'account_head_name' => $accountHeadName,
                'ref_bill_no' => $item->ref_bill_no,
                'amount' => $item->amount,
                'particular' => $item->particular,
            ];

            if ($item->bank_id) {
                $itemData['bank_id'] = $item->bank_id;
                $itemData['bank_name'] = isset($banks[$item->bank_id]) ? $banks[$item->bank_id]->name : null;
                $itemData['cheque_no'] = isset($payments[$item->bank_id]) ? $payments[$item->bank_id]->cheque_no : null;
            }

            return $itemData;
        });

        $totalAmount = $tracker->items->sum('amount');

        return [
            'voucher_id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'date_in_ad' => $tracker->created_at->format('Y-m-d'),
            'date_in_bs' => $dateService->adToBs($tracker->created_at),
            'reference_type' => config('voucher.' . $voucher->reference_type),
            'reference_id' => $voucher->reference_id,
            'is_cancel' => $voucher->is_cancel,
            'fiscal_year_id' => $tracker->fiscal_year_id,
            'items' => $items,
            'total_amount' => $totalAmount,
        ];
    }
}