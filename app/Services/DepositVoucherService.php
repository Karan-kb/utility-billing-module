<?php

namespace App\Services;

use App\Models\MeterDepositTransaction;
use Carbon\Carbon;
use App\Helpers\NepaliCalendar;

class DepositVoucherService
{
    /**
     * Generate voucher for deposit entry
     */
    public function generateDepositEntry(): array
    {
        return $this->generate(0);
    }

    /**
     * Generate voucher for deposit return
     */
    public function generateDepositReturn(): array
    {
        return $this->generate(1);
    }

    /**
     * Internal method to generate voucher based on type
     */
    private function generate(int $transactionType): array
    {
        $prefix = $transactionType === 0 ? 'D' : 'DR';

        $adDate = Carbon::now()->format('Y-m-d');
        $bsDate = NepaliCalendar::adToBs($adDate);
        [$bsYear, $bsMonth] = explode('-', $bsDate);

        $bsYear = (int) $bsYear;
        $bsMonth = (int) $bsMonth;

        $fiscalYear = $bsMonth >= 4 ? $bsYear : $bsYear - 1;
        $fiscalYearCode = substr($fiscalYear, 2, 2) . substr($fiscalYear + 1, 2, 2);

        $lastEntry = MeterDepositTransaction::withTrashed()
            ->where('voucher_no', 'like', "{$prefix}{$fiscalYearCode}%")
            ->orderBy('id', 'desc')
            ->first();

        $lastNumber = $lastEntry ? (int) substr($lastEntry->voucher_no, strlen($prefix . $fiscalYearCode . "-")) : 0;

        $voucherNo = "{$prefix}{$fiscalYearCode}-" . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);

        return [
            'voucher_no' => $voucherNo,
            'fiscal_year' => $fiscalYearCode
        ];
    }
}
