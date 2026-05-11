<?php

namespace App\Services;

use App\Models\VoucherSummary;
use App\Models\VoucherSummaryDetail;
use Illuminate\Support\Facades\DB;

class VoucherEntryService
{
    /**
     * Create voucher entry with summary and lines
     */
    public function create(array $data): VoucherSummary
    {
        return DB::connection('tenant')->transaction(function () use ($data) {
            $voucher = $this->createSummary($data);
            $this->createLines(
                $voucher->id,
                $data['lines'],
                $data['member_entry_id'] ?? null
            );
            return $voucher;
        });
    }


    private function createSummary(array $data): VoucherSummary
    {
        return VoucherSummary::create([
            // 'entry_no'        => $this->generateEntryNumber(),
            'date'            => $data['date'],
            'fiscal_year_id'  => $data['fiscal_year_id']??null,
            'voucher_no'      => $data['voucher_no'] ?? null,
            'reference_type'  => $data['reference_type'] ?? null,
            'reference_id'    => $data['reference_id'] ?? null,
            'status'          => $data['status'] ?? '1',
            'particulars'     => $data['particulars'] ?? null,
        ]);
    }


    private function createLines(
        int $voucherSummaryId,
        array $lines,
        ?int $memberEntryId
    ): void 
    {
        foreach ($lines as $line) {
            VoucherSummaryDetail::create([
                'voucher_summary_id' => $voucherSummaryId,
                'account_head_id'         => $line['account_head_id'],
                'debit'              => $line['debit'] ?? 0,
                'credit'             => $line['credit'] ?? 0,
                'member_entry_id'    => $memberEntryId,
                'particulars'        => $line['particulars'] ?? null,
            ]);
        }
    }

    // private function generateEntryNumber(): string
    // {
    //     return 'JE-' . now()->format('YmdHis');
    // }
}