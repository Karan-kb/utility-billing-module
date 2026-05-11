<?php

namespace App\Services;

class VoucherBalanceService
{
    public static function validate(array $lines, float $tolerance = 0.01): void
    {
        $totalDebit = collect($lines)->sum(function ($line) {
            return (float) ($line['debit'] ?? 0);
        });

        $totalCredit = collect($lines)->sum(function ($line) {
            return (float) ($line['credit'] ?? 0);
        });

        // Round to prevent floating issues
        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        if (abs($totalDebit - $totalCredit) > $tolerance) {
            throw new \Exception(
                "Voucher entry not balanced. Debit ({$totalDebit}) must equal Credit ({$totalCredit})."
            );
        }
    }
}