<?php

namespace App\Services\VoucherPreview;

use App\Models\JournalVoucher;
use App\Models\AccountHead;
use App\Services\DateConverterService;

class JournalVoucherPreviewBuilder
{
    public function build($voucher, DateConverterService $dateService): array
    {
        $journalVoucher = JournalVoucher::with('details')
            ->findOrFail($voucher->reference_id);

        $details = $journalVoucher->details ?? collect();

        $accountHeadIds = $details->pluck('account_head_id')->unique();

        $accountHeads = AccountHead::whereIn('id', $accountHeadIds)
            ->get()
            ->keyBy('id');

        $lineItems = $details->map(function ($detail) use ($accountHeads) {

            $accountHeadName = $accountHeads[$detail->account_head_id]->name ?? 'Unknown';

            return [
                'account_head_id'   => $detail->account_head_id,
                'account_head_name' => $accountHeadName,
                'debit'             => $detail->debit ?? 0,
                'credit'            => $detail->credit ?? 0,
                'cheque_no'         => $detail->cheque_no,
                'particulars'       => $detail->particulars,
            ];
        });

        // Totals
        $totalDebit  = $lineItems->sum('debit');
        $totalCredit = $lineItems->sum('credit');

        return [
            'voucher_id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,

            'date_in_ad' => $journalVoucher->created_at->format('Y-m-d'),
            'date_in_bs' => $dateService->adToBs($journalVoucher->created_at),

            'reference_type' => config('voucher.' . $voucher->reference_type),
            'reference_id'   => $voucher->reference_id,
            'is_cancel'      => $voucher->is_cancel,

            'fiscal_year_id' => $journalVoucher->fiscal_year_id,
            'details' => $lineItems,

            'total_debit'  => $totalDebit,
            'total_credit' => $totalCredit,
        ];
    }
}