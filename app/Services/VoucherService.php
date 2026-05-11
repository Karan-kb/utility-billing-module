<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class VoucherService
{
    /**
     * Generate voucher/product code for a given model and prefix
     *
     * @param string $modelClass Fully qualified model class
     * @param string $prefix Prefix for voucher (e.g., 'O', 'D', 'SE')
     * @param string $column Column to use for code (default 'voucher_no')
     * @param bool $useNepaliCalendar Whether to use Nepali calendar (default true)
     * @return string
     * @throws \Exception
     */
    public function generateCode(string $modelClass, string $prefix, string $column = 'voucher_no', bool $useNepaliCalendar = true): string
    {
        // Get current AD date
        $adDate = Carbon::now()->format('Y-m-d');

        // Determine fiscal year code
        if ($useNepaliCalendar) {
            $bsDate = \App\Helpers\NepaliCalendar::adToBs($adDate);
            $bsDateParts = explode('-', $bsDate);
            $currentBsYear = (int) $bsDateParts[0];
            $currentBsMonth = (int) $bsDateParts[1];

            $fiscalYear = $currentBsMonth >= 4 ? $currentBsYear : $currentBsYear - 1;
        } else {
            $ad = Carbon::parse($adDate);
            $adYear = (int)$ad->year;
            $adMonth = (int)$ad->month;

            $fiscalYear = $adMonth >= 4 ? $adYear + 57 : $adYear + 56;
        }

        $fiscalYearCode = substr($fiscalYear, 2, 2) . substr($fiscalYear + 1, 2, 2);

        // Check user token if using API auth
        $user = Auth::guard('api')->user();
        if (!$user) {
            throw new \Exception('Unauthorized: User not authenticated');
        }
        $token = $user->currentAccessToken();
        if (!$token) {
            throw new \Exception('No valid token found');
        }

             // Get last entry
             $query = $modelClass::withTrashed()
             ->where($column, 'like', "{$prefix}{$fiscalYearCode}%");

            // Exclude opening advance entries
            if ($modelClass === \App\Models\AdvancePayment::class) {
                $query->where('type', '!=', 1);
            }

            $lastEntry = $query
                ->orderBy('id', 'desc')
                ->first();

        // Determine next number
        $lastNumber = 0;
        if ($lastEntry) {
            $code = $lastEntry->{$column};
            $lastNumber = (int) substr($code, -6);
        }

        $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);

        return "{$prefix}{$fiscalYearCode}-{$newNumber}";
    }
}
