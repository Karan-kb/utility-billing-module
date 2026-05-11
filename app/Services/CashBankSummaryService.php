<?php

namespace App\Services;

use App\Models\VoucherSummaryDetail;
use Illuminate\Support\Facades\DB;

class CashBankSummaryService
{
    /**
     * Get balance for given account_head_ids
     *
     * @param array $accountHeadIds
     * @param int|null $excludeVoucherSummaryId Optional voucher_summary_id to exclude
     * @return array
     */
public function getByAccountIds(array $accountHeadIds, ?int $excludeVoucherSummaryId = null, ?int $excludeReferenceId = null)
{
    $query = VoucherSummaryDetail::query()
        ->select(
            'account_head_id',
            DB::raw('SUM(debit) as total_debit'),
            DB::raw('SUM(credit) as total_credit')
        )
        ->join('voucher_summaries as vs', 'voucher_summary_details.voucher_summary_id', '=', 'vs.id')
        ->where('vs.status', '!=', 3)
        ->whereIn('account_head_id', $accountHeadIds);

    // Exclude a specific voucher_summary_id (current update)
    if ($excludeVoucherSummaryId) {
        $query->where('voucher_summary_details.voucher_summary_id', '!=', $excludeVoucherSummaryId);
    }

    // Exclude all vouchers for the same reference_id and reference_type = 13 (Bank Voucher)
    if ($excludeReferenceId) {
        $query->where(function($q) use ($excludeReferenceId) {
            $q->where('vs.reference_type', '!=', 13)
              ->orWhere('vs.reference_id', '!=', $excludeReferenceId);
        });
    }

    $rows = $query->groupBy('account_head_id')->get()->keyBy('account_head_id');

    $result = [];
    foreach ($accountHeadIds as $id) {
        $row = $rows->get($id);
        $balance = $row ? (float)$row->total_debit - (float)$row->total_credit : 0;
        $result[] = [
            'account_head_id' => $id,
            'balance' => $balance,
        ];
    }

    return $result;
}
}