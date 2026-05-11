<?php

namespace App\Services;

use App\Helpers\NepaliCalendar;
use App\Models\NonMemberPayment;
use Carbon\Carbon;

class VoucherValidationService
{
    /**
     * Validate a voucher number.
     *
     * @param string $voucherNo
     * @param string $prefix e.g., 'NM'
     * @param string $modelClass e.g., NonMemberPayment::class
     * @param callable|null $extraCheck optional callback for extra validation
     * @return \Closure
     */
    public static function validate(string $prefix, string $modelClass)
    {
        return function ($attribute, $value, $fail) use ($prefix, $modelClass) {
            if (!$value) return;

            try {
                $adDate = Carbon::now()->format('Y-m-d');
                $bsDate = NepaliCalendar::adToBs($adDate);
                [$bsYear, $bsMonth] = explode('-', $bsDate);

                $bsYear = (int)$bsYear;
                $bsMonth = (int)$bsMonth;

                $fiscalYear = $bsMonth >= 4 ? $bsYear : $bsYear - 1;
                $fiscalYearCode = substr($fiscalYear, 2, 2) . substr($fiscalYear + 1, 2, 2);

                // $lastVoucher = $modelClass::withTrashed()
                //     ->where('voucher_no', 'like', "{$prefix}{$fiscalYearCode}%")
                //     ->orderBy('id', 'desc')
                //     ->first();
                $query = $modelClass::withTrashed()
                 ->where('voucher_no', 'like', "{$prefix}{$fiscalYearCode}%");

                // Exclude type = 1 for AdvancePayment model
                if ($modelClass === \App\Models\AdvancePayment::class) {
                    $query->where('type', '!=', 1);
                }

                $lastVoucher = $query
                    ->orderBy('id', 'desc')
                    ->first();

                $lastNumber = $lastVoucher ? (int) substr($lastVoucher->voucher_no, 8) : 0;
                $expectedVoucherNo = "{$prefix}{$fiscalYearCode}-" . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);

                $pattern = "/^{$prefix}{$fiscalYearCode}-\d{6}$/";

                if (!preg_match($pattern, $value)) {
                    $fail("The {$attribute} must be in format {$prefix}{$fiscalYearCode}-XXXXXX (e.g., {$expectedVoucherNo}).");
                    return;
                }

                if ($value !== $expectedVoucherNo) {
                    $fail("Invalid {$attribute}. Expected: {$expectedVoucherNo}.");
                }
            } catch (\Exception $e) {
                $fail("Error validating {$attribute}: " . $e->getMessage());
            }
        };
    }
}
