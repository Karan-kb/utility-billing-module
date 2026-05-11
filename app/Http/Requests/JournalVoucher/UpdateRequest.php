<?php

namespace App\Http\Requests\JournalVoucher;

use App\Helpers\NepaliCalendar;
use App\Models\AccountHead;
use App\Services\VoucherValidationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\CashBankSummaryService;
    use App\Models\VoucherSummary;

class UpdateRequest extends FormRequest
{


private function getVoucherSummaryId(): ?int
{
    $currentJournalVoucherId = $this->route('id');
    $currentVoucherSummary = VoucherSummary::where('reference_type', 14) 
        ->where('reference_id', $currentJournalVoucherId)
        ->first();

    return $currentVoucherSummary ? $currentVoucherSummary->id : null;
}
    public function rules()
    {

        return [
            'voucher_no' => [
                'required',
                'string',
                Rule::exists('tenant.journal_vouchers', 'voucher_no')
                    ->whereNull('deleted_at')
                    ->where('id', $this->route('id'))
            ],

            'date_in_bs' => [
                'required',
                'regex:/^\d{4}-\d{2}-\d{2}$/',
                function ($attribute, $value, $fail) {
                    try {
                        $adDate = NepaliCalendar::bsToAd($value);
                        if ($adDate > date('Y-m-d')) {
                            $fail("Future date not allowed.");
                        }
                    } catch (\Exception $e) {
                        $fail("Invalid BS date.");
                    }
                },
            ],

            'details' => 'required|array|min:2',

            'details.*.account_head_id' => [
                'required',
                'integer',
                Rule::exists('tenant.account_heads', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', 1),
            ],

            'details.*.debit' => 'nullable|numeric|min:0',
            'details.*.credit' => 'nullable|numeric|min:0',
            'details.*.particulars' => 'nullable|string',
            'details.*.cheque_no' => 'nullable|string|max:50',
        ];
    }

  public function withValidator($validator)
{
    $validator->after(function ($validator) {
        $totalDebit  = 0;
        $totalCredit = 0;

        $service                   = new CashBankSummaryService();
        $currentVoucherSummaryId   = $this->getVoucherSummaryId(); 

        $accountIds = collect($this->details)
            ->pluck('account_head_id')
            ->toArray();

        $balances = collect(
            $service->getByAccountIds($accountIds, $currentVoucherSummaryId)
        )->keyBy('account_head_id');

        $accountHeads = AccountHead::whereIn('id', $accountIds)
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->get()
            ->keyBy('id');

        foreach ($this->details as $index => $detail) {
            $accountHeadId = $detail['account_head_id'];
            $debit         = $detail['debit'] ?? 0;
            $credit        = $detail['credit'] ?? 0;
            $chequeNo      = $detail['cheque_no'] ?? null;

            $accountHead = $accountHeads[$accountHeadId] ?? null;

            // Only one debit/credit per row
            if ($debit > 0 && $credit > 0) {
                $validator->errors()->add(
                    "details.$index",
                    "Only one of debit or credit is allowed per row."
                );
            }

            // At least one must be > 0
            if ($debit == 0 && $credit == 0) {
                $validator->errors()->add(
                    "details.$index",
                    "Either debit or credit must be provided."
                );
            }

            // Cheque no required for bank group
            if ($accountHead && $accountHead->account_group_id == 10) {
                if (empty($chequeNo)) {
                    $validator->errors()->add(
                        "details.$index.cheque_no",
                        "Cheque number is required for bank transactions."
                    );
                }
            }

            // Check credit balance for cash (id 1) and bank (group 10)
            if ($credit > 0 && (
                $accountHeadId == 1
                    || ($accountHead && $accountHead->account_group_id == 10)
            )) {
                $balance = $balances[$accountHeadId]['balance'] ?? 0;

                if ($accountHeadId == 1 && $credit > $balance) {
                    $validator->errors()->add(
                        "details.$index.credit",
                        "Cash credit exceeds available balance. Current balance: {$balance}."
                    );
                }

                if ($accountHead && $accountHead->account_group_id == 10 && $credit > $balance) {
                    $validator->errors()->add(
                        "details.$index.credit",
                        "Bank credit exceeds available balance. Current balance: {$balance}."
                    );
                }

                $expectedBalance = $balance - $credit;

                if ($expectedBalance < 0) {
                    $validator->errors()->add(
                        "details.$index.credit",
                        "This transaction would result in negative balance. Expected: {$expectedBalance}."
                    );
                }
            }

            $totalDebit  += $debit;
            $totalCredit += $credit;
        }

        if ($totalDebit != $totalCredit) {
            $validator->errors()->add(
                'balance',
                'Total debit and credit must be equal.'
            );
        }
    });
}
}