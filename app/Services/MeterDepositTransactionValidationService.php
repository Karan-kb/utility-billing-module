<?php

namespace App\Services;

use App\Models\MeterDepositTransaction;
use App\Models\OpeningMeterDepositEntry;
use Carbon\Carbon;
use App\Helpers\NepaliCalendar;

class MeterDepositTransactionValidationService
{
    /**
     * Get existing deposit amount for a member (for transaction_type = 1)
     *
     * @param int $memberId
     * @return float
     */
    public function getExistingDepositAmount($meterIssueId)
    {
        $totalDeposits = MeterDepositTransaction::where('meter_issue_id', $meterIssueId)
            ->where('transaction_type', 1) // deposits
            ->where('is_cancel', 0)
            ->whereNull('deleted_at')
            ->sum('amount');

        $totalReturns = MeterDepositTransaction::where('meter_issue_id', $meterIssueId)
            ->where('transaction_type', 2) // returns
            ->where('is_cancel', 0)
            ->whereNull('deleted_at')
            ->sum('amount');

        $openingDeposit = MeterDepositTransaction::where('meter_issue_id', $meterIssueId)
            ->where('transaction_type', 4) // returns
            ->whereNull('deleted_at')
            ->sum('amount');

        $upgradeDeposit = MeterDepositTransaction::where('meter_issue_id', $meterIssueId)
            ->where('transaction_type', 3) // returns
            ->where('is_cancel', 0)
            ->whereNull('deleted_at')
            ->sum('amount');

        $amount =  ($totalDeposits + $openingDeposit + $upgradeDeposit) - $totalReturns;

        return $amount;
    }

    public function getExistingDepositAmountforReturn($meterIssueId ,$serviceCharge)
    {
        $totalDeposits = MeterDepositTransaction::where('meter_issue_id', $meterIssueId)
            ->where('transaction_type', 1) // deposits
            ->where('is_cancel', 0)
            ->whereNull('deleted_at')
            ->sum('amount');

        $totalReturns = MeterDepositTransaction::where('meter_issue_id', $meterIssueId)
            ->where('transaction_type', 2) // returns
            ->where('is_cancel', 0)
            ->whereNull('deleted_at')
            ->sum('amount');

        $openingDeposit = MeterDepositTransaction::where('meter_issue_id', $meterIssueId)
            ->where('transaction_type', 4) // returns
            ->whereNull('deleted_at')
            ->sum('amount');

        $upgradeDeposit = MeterDepositTransaction::where('meter_issue_id', $meterIssueId)
            ->where('transaction_type', 3) // returns
            ->where('is_cancel', 0)
            ->whereNull('deleted_at')
            ->sum('amount');

        $amount =  ($totalDeposits + $openingDeposit + $upgradeDeposit) - $totalReturns - $serviceCharge;

        return $amount;
    }

    /**
     * Validate voucher number format and sequence for deposit or return
     *
     * @param string $voucherNo
     * @param int $transactionType 0=deposit, 1=return
     * @return string|null  Error message or null if valid
     */
    public function validateVoucherNo(string $voucherNo, int $transactionType): ?string
    {
        try {
            $adDate = Carbon::now()->format('Y-m-d');
            $bsDate = NepaliCalendar::adToBs($adDate);
            [$bsYear, $bsMonth] = explode('-', $bsDate);

            $bsYear = (int) $bsYear;
            $bsMonth = (int) $bsMonth;

            $fiscalYear = $bsMonth >= 4 ? $bsYear : $bsYear - 1;
            $fiscalYearCode = substr($fiscalYear, 2, 2) . substr($fiscalYear + 1, 2, 2);

            switch ($transactionType) {
                case 1:
                    $prefix = 'D';
                    break;
            
                case 2:
                    $prefix = 'DR';
                    break;
            
                default:
                    $prefix = 'UG';
                    break;
            }           

            //$prefix = $transactionType === 1 ? 'D' : 'DR';

            $lastReceipt = MeterDepositTransaction::withTrashed()
                ->where('voucher_no', 'like', "{$prefix}{$fiscalYearCode}%")
                ->orderBy('id', 'desc')
                ->first();

            $lastNumber = $lastReceipt ? (int) substr($lastReceipt->voucher_no, strlen($prefix . $fiscalYearCode . "-")) : 0;

            
            $expectedVoucherNo = "{$prefix}{$fiscalYearCode}-" . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
            $pattern = "/^{$prefix}{$fiscalYearCode}-\d{6}$/";

            if (!preg_match($pattern, $voucherNo)) {
                return "The voucher_no must be in format {$prefix}{$fiscalYearCode}-XXXXXX (e.g., {$expectedVoucherNo}.).";
            }

            if ($voucherNo !== $expectedVoucherNo) {
                return "Invalid voucher_no. Expected: {$expectedVoucherNo}.";
            }

            return null;

        } catch (\Exception $e) {
            return "Error validating voucher_no: " . $e->getMessage();
        }
    }

    /**
     * Check if member already has non-deleted transaction of the given type
     *
     * @param int $memberId
     * @param int $transactionType
     * @return bool
     */
    public function hasExistingTransaction(int $meterIssueId, int $transactionType): bool
    {
        return MeterDepositTransaction::withoutTrashed()
            ->where('meter_issue_id', $meterIssueId)
            ->where('is_cancel', 0)
            ->where('transaction_type', $transactionType)
            ->exists();
    }

}
