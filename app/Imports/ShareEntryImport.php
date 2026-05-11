<?php

namespace App\Imports;

use App\Helpers\NepaliCalendar;
use App\Models\AccountHead;
use App\Models\ShareEntry;
use App\Models\MemberEntry;
use App\Models\MasterSetup;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Log;

class ShareEntryImport implements ToCollection, WithHeadingRow, WithValidation
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

                ShareEntry::withTrashed()->updateOrCreate(
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
        $shareQuantity = $row['share_quantity'] ?? 0;
        $totalAmount = $row['total_amount'] ?? 0;
        $cash = $row['payment_by_cash'] ?? false;
        $bank = $row['payment_by_bank'] ?? false;
        $cashAmount = $row['cash_amount'] ?? 0;
        $bankAmount = $row['bank_amount'] ?? 0;

        // Validate customer
        if (!MemberEntry::withoutTrashed()->where('member_entry_id', $customerId)->where('is_active', 1)->exists()) {
            throw new \Exception("Invalid customer_id: {$customerId}");
        }

        // Validate voucher_no format and sequential number
        $adDate = Carbon::now()->format('Y-m-d');
        $bsDate = NepaliCalendar::adToBs($adDate);
        $bsParts = explode('-', $bsDate);
        $currentBsYear = (int) $bsParts[0];
        $currentBsMonth = (int) $bsParts[1];

        $fiscalYear = $currentBsMonth >= 4 ? $currentBsYear : $currentBsYear - 1;
        $fiscalYearCode = substr($fiscalYear, 2, 2) . substr($fiscalYear + 1, 2, 2);

        $lastReceipt = ShareEntry::withTrashed()
            ->where('voucher_no', 'like', "SE{$fiscalYearCode}%")
            ->orderBy('id', 'desc')
            ->first();

        $lastNumber = $lastReceipt ? (int) substr($lastReceipt->voucher_no, 8) : 0;
        $expectedVoucherNo = "SE{$fiscalYearCode}-" . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
        $pattern = "/^SE{$fiscalYearCode}-\d{6}$/";

        if (!preg_match($pattern, $voucherNo)) {
            throw new \Exception("Voucher_no {$voucherNo} must be in format SE{$fiscalYearCode}-XXXXXX (expected: {$expectedVoucherNo})");
        }

        if ($voucherNo !== $expectedVoucherNo) {
            throw new \Exception("Invalid voucher_no. Expected: {$expectedVoucherNo}");
        }

        // Validate share value
        if ($row['share_value'] !== 100.00) {
            throw new \Exception("share_value must be exactly 100.00");
        }

        $expectedTotal = $shareQuantity * 100.00;
        if ($totalAmount !== $expectedTotal) {
            throw new \Exception("total_amount must equal share_quantity * 100.00 (expected: $expectedTotal)");
        }

        // Validate payment amounts
        if ($cash && $bank) {
            if (($cashAmount + $bankAmount) != $totalAmount) {
                throw new \Exception("Sum of cash_amount and bank_amount must equal total_amount ($totalAmount)");
            }
        } elseif ($cash) {
            if ($cashAmount != $totalAmount) {
                throw new \Exception("cash_amount must equal total_amount ($totalAmount)");
            }
        } elseif ($bank) {
            if ($bankAmount != $totalAmount) {
                throw new \Exception("bank_amount must equal total_amount ($totalAmount)");
            }
        } else {
            throw new \Exception("At least one of payment_by_cash or payment_by_bank must be true");
        }

        if ($cashAmount == 0 && $bankAmount == 0) {
            throw new \Exception("Both cash_amount and bank_amount cannot be zero");
        }

        // Validate bank details if bank payment
       if ($bank && !AccountHead::on('tenant')
        ->where('id', $row['bank_id'])
        ->where('account_group_id', 10) // bank accounts
        ->where('is_active', 1)
        ->whereNull('deleted_at')
        ->exists()) {
    throw new \Exception("Invalid bank_id: must be a valid bank in AccountHead with account_group_id = 10");
}

        return [
            'date_in_bs' => $row['date_in_bs'],
            'date_in_ad' => $row['date_in_ad'],
            'member_entry_id' => $customerId,
            'voucher_no' => $voucherNo,
            'share_type' => $row['share_type'],
            'share_certificate_no' => $row['share_certificate_no'],
            'share_quantity' => $shareQuantity,
            'share_value' => $row['share_value'],
            'total_amount' => $totalAmount,
            'is_active' => $row['is_active'] ?? 1,
            'payment_by_cash' => $cash,
            'payment_by_bank' => $bank,
            'cash_amount' => $cashAmount,
            'bank_amount' => $bankAmount,
            'cheque_no' => $row['cheque_no'] ?? null,
            'bank_id' => $row['bank_id'] ?? null,
        ];
    }

    public function rules(): array
    {
        return [
            '*.customer_id' => 'required|string|max:10',
            '*.voucher_no' => 'required|string|max:20',
            '*.share_type' => 'required|string|in:electricity',
            '*.share_certificate_no' => 'required|string|max:20',
            '*.share_quantity' => 'required|integer|min:1',
            '*.share_value' => 'required|numeric',
            '*.total_amount' => 'required|numeric',
            '*.payment_by_cash' => 'required|boolean',
            '*.payment_by_bank' => 'required|boolean',
            '*.cash_amount' => 'nullable|numeric|min:0',
            '*.bank_amount' => 'nullable|numeric|min:0',
            '*.date_in_bs' => ['required', 'string', 'max:10', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            '*.date_in_ad' => ['required', 'string', 'max:10', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            '*.bank_id' => 'nullable|integer',
            '*.cheque_no' => 'nullable|string|max:20',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
