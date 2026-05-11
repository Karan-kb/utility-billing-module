<?php

namespace App\Imports;

use App\Helpers\NepaliCalendar;
use App\Models\DepositEntry;
use App\Models\MemberEntry;
use App\Models\MeterIssue;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Log;

class DepositEntryImport implements ToCollection, WithHeadingRow, WithValidation
{
    use Importable;

    public $importedCount = 0;
    public $failedRows = [];

    public function collection(Collection $rows)
    {
        set_time_limit(600);
        foreach ($rows as $row) {
            try {
                $validatedData = $this->validateRow($row);

                DepositEntry::withTrashed()->updateOrCreate(
                    ['voucher_no' => $validatedData['voucher_no']],
                    $validatedData
                );

                $this->importedCount++;
            } catch (\Exception $e) {
                $this->failedRows[] = [
                    'row' => $row->toArray(),
                    'error' => $e->getMessage()
                ];
            }
        }
    }

    protected function validateRow($row)
    {
        $customerId = $row['member_entry_id'];
        $voucherNo = $row['voucher_no'];
        $meterDeposit = $row['meter_deposit_amount'] ?? 0;
        $cash = $row['payment_by_cash'] ?? false;
        $bank = $row['payment_by_bank'] ?? false;
        $cashAmount = $row['cash_amount'] ?? 0;
        $bankAmount = $row['bank_amount'] ?? 0;

        // Check customer_id
        if (!MemberEntry::withoutTrashed()->where('member_entry_id', $customerId)->where('is_active', 1)->exists()) {
            throw new \Exception("Invalid customer_id: {$customerId}");
        }

        if (DepositEntry::withoutTrashed()->where('cancel_status', 0)->where('member_entry_id', $customerId)->exists()) {
            throw new \Exception("Customer_id {$customerId} already has an active deposit entry.");
        }

        // Validate voucher_no format
        $adDate = Carbon::now()->format('Y-m-d');
        $bsDate = NepaliCalendar::adToBs($adDate);
        $bsParts = explode('-', $bsDate);
        $currentBsYear = (int) $bsParts[0];
        $currentBsMonth = (int) $bsParts[1];

        $fiscalYear = $currentBsMonth >= 4 ? $currentBsYear : $currentBsYear - 1;
        $fiscalYearCode = substr($fiscalYear, 2, 2) . substr($fiscalYear + 1, 2, 2);

        $lastReceipt = DepositEntry::withTrashed()
            ->where('voucher_no', 'like', "D{$fiscalYearCode}%")
            ->orderBy('id', 'desc')
            ->first();

        $lastNumber = $lastReceipt ? (int) substr($lastReceipt->voucher_no, 8) : 0;
        $expectedVoucherNo = "D{$fiscalYearCode}-" . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
        $pattern = "/^D{$fiscalYearCode}-\d{6}$/";

        if (!preg_match($pattern, $voucherNo)) {
            throw new \Exception("Voucher_no {$voucherNo} must be in format D{$fiscalYearCode}-XXXXXX (expected: {$expectedVoucherNo})");
        }

        if ($voucherNo !== $expectedVoucherNo) {
            throw new \Exception("Invalid voucher_no. Expected: {$expectedVoucherNo}");
        }

        // Validate payment amounts
        if ($cash && $bank) {
            if (($cashAmount + $bankAmount) != $meterDeposit) {
                throw new \Exception("Sum of cash_amount and bank_amount must equal meter_deposit_amount ({$meterDeposit})");
            }
        } elseif ($cash) {
            if ($cashAmount != $meterDeposit) {
                throw new \Exception("cash_amount must equal meter_deposit_amount ({$meterDeposit})");
            }
        } elseif ($bank) {
            if ($bankAmount != $meterDeposit) {
                throw new \Exception("bank_amount must equal meter_deposit_amount ({$meterDeposit})");
            }
        } else {
            throw new \Exception("At least one of payment_by_cash or payment_by_bank must be true.");
        }

        if ($cashAmount == 0 && $bankAmount == 0) {
            throw new \Exception("Both cash_amount and bank_amount cannot be zero.");
        }

        return [
            'date_in_bs' => $row['date_in_bs'],
            'date_in_ad' => $row['date_in_ad'],
            'member_entry_id' => $customerId,
            'voucher_no' => $voucherNo,
            'meter_deposit_amount' => (float) $meterDeposit,
            'upgraded_deposit_amount' => isset($row['upgraded_deposit_amount']) ? (float) $row['upgraded_deposit_amount'] : null,
            'cash_amount' => $cashAmount === '' || $cashAmount === null ? 0.0 : (float) $cashAmount,
            'bank_amount' => $bankAmount === '' || $bankAmount === null ? 0.0 : (float) $bankAmount,
            'is_active' => $row['is_active'] ?? 1,
            'cancel_status' => $row['cancel_status'] ?? 0,
            'payment_by_cash' => $cash,
            'payment_by_bank' => $bank,
            'cheque_no' => $row['cheque_no'] ?? null,
            'bank_details' => $row['bank_details'] ?? null,
        ];
    }

    public function rules(): array
    {
        return [
            '*.customer_id' => 'required|string|max:10',
            '*.voucher_no' => 'required|string|max:20',
            '*.meter_deposit_amount' => 'required|numeric|min:1',
            '*.payment_by_cash' => 'required|boolean',
            '*.payment_by_bank' => 'required|boolean',
            '*.cash_amount' => 'nullable|numeric|min:0',
            '*.bank_amount' => 'nullable|numeric|min:0',
            '*.date_in_bs' => ['required', 'string', 'max:10', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            '*.date_in_ad' => ['required', 'string', 'max:10', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
