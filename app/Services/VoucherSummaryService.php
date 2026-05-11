<?php

namespace App\Services;

use App\Models\VoucherSummary;
use App\Models\VoucherSummaryDetail;
use Illuminate\Support\Facades\DB;

class VoucherSummaryService
{
    /**
     * Create a voucher with explicit debit and credit entries.
     *
     * @param string $voucherNo
     * @param string $dateAd
     * @param string $dateBs
     * @param array $debitEntries [['account_head_id' => x, 'particulars' => '...', 'amount' => y], ...]
     * @param array $creditEntries [['account_head_id' => x, 'particulars' => '...', 'amount' => y], ...]
     * @param int $incomeAccountHeadId
     * @param int $voucherType
     * @param int $memberEntryId
     * @return VoucherSummary
     */
    public function createVoucher(
        string $voucherNo,
        string $dateAd,
        string $dateBs,
        array $debitEntries,
        array $creditEntries,
        ?int $incomeAccountHeadId, // nullable
        int $voucherType,
        ?int $memberEntryId // <-- allow null
        ): VoucherSummary {
        return DB::connection('tenant')->transaction(function () use (
            $voucherNo, $dateAd, $dateBs, $debitEntries, $creditEntries, $incomeAccountHeadId, $voucherType, $memberEntryId
        ) {
            $voucher = VoucherSummary::create([
                'voucher_no' => $voucherNo,
                'date_in_ad' => $dateAd,
                'date_in_bs' => $dateBs,
                'type' => $voucherType,
                'member_entry_id' => $memberEntryId,
            ]);

            $totalDebit = $this->createEntries($voucher->id, $debitEntries, 'debit');
            $totalCredit = $this->createEntries($voucher->id, $creditEntries, 'credit');

            // Balancing entry
            $balance = $totalDebit - $totalCredit;
            if ($balance != 0) {
                $particulars = match ($voucherType) {
                    1 => 'Meter Deposit',
                    3 => 'Meter Deposit Return',
                    11 => 'Mahasul Income',
                    5 => 'Meter Inurance',
                    default => 'Voucher Balance',
                };

                VoucherSummaryDetail::create([
                    'voucher_summary_id' => $voucher->id,
                    'account_head_id' => $incomeAccountHeadId,
                    'particulars' => $particulars,
                    'debit' => $balance < 0 ? abs($balance) : 0,
                    'credit' => $balance > 0 ? abs($balance) : 0,
                ]);
            }

            return $voucher;
        });
    }

    /**
     * Reusable method to create debit or credit entries.
     *
     * @param int $voucherId
     * @param array $entries
     * @param string $type 'debit' or 'credit'
     * @return float Total amount
     */
    protected function createEntries(int $voucherId, array $entries, string $type)
    {
        $total = 0;

        foreach ($entries as $entry) {
            $amount = round($entry['amount'] ?? 0, 2);
            if ($amount <= 0) continue;

            VoucherSummaryDetail::create([
                'voucher_summary_id' => $voucherId,
                'account_head_id' => $entry['account_head_id'],
                'particulars' => $entry['particulars'],
                'debit' => $type === 'debit' ? $amount : 0,
                'credit' => $type === 'credit' ? $amount : 0,
            ]);

            $total += $amount;
        }

        return $total;
    }
}
