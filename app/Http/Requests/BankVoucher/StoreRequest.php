<?php

namespace App\Http\Requests\BankVoucher;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\CashBankSummaryService;
use App\Services\PaymentValidationService;
use App\Services\VoucherValidationService;
use App\Models\BankVoucher;

class StoreRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'option' => ['required','in:1,2'], // 1=Deposit, 2=Withdraw
            'date_in_bs' => [
                'required','string','max:10',
                'regex:/^\d{4}-\d{2}-\d{2}$/',
                function ($attribute, $value, $fail) {
                    try {
                        $adDate = \App\Helpers\NepaliCalendar::bsToAd($value);
                        if ($adDate > date('Y-m-d')) {
                            $fail("The {$attribute} cannot be a future date.");
                        }
                    } catch (\Exception $e) {
                        $fail("The {$attribute} is not a valid BS date.");
                    }
                }
            ],
            'voucher_no' => [
                'required','string','max:20',
                VoucherValidationService::validate('B', BankVoucher::class)
            ],
           'amount' => [
    'required',
    'numeric',
    'min:1',
    'max:999999999999.99',
    function($attribute, $value, $fail) {
        $service = new CashBankSummaryService();
        $bankId = $this->bank_id;
        $option = $this->option;

        $balances = collect($service->getByAccountIds([1, $bankId]))->keyBy('account_head_id');

        $cashBalance = $balances[1]['balance'] ?? 0;
        $bankBalance = $balances[$bankId]['balance'] ?? 0;

        if ($option == 1) { // Deposit → cash decreases
            if ($value > $cashBalance) {
                $fail("Amount exceeds available cash balance. Current cash balance: {$cashBalance}.");
            }
            $expectedBalanceAfter = $bankBalance + $value;
        } else { // Withdraw → bank decreases
            if ($value > $bankBalance) {
                $fail("Amount exceeds available bank balance. Current bank balance: {$bankBalance}.");
            }
            $expectedBalanceAfter = $bankBalance - $value;
        }

        if ($expectedBalanceAfter < 0) {
            $fail("Amount would make balance negative. Expected balance after: {$expectedBalanceAfter}.");
        }
    }
],
            'bank_id' => ['required','integer', function($attr,$val,$fail){
                PaymentValidationService::validateBank($this,$attr,$val,$fail);
            }],
            'cheque_no' => ['required','string','max:20'],
            'remarks' => ['nullable','string'],

            // balance_after with custom validation showing expected and current balance
            'balance_after' => [
                'required',
                'numeric',
                function($attribute, $value, $fail) {
                    $service = new CashBankSummaryService();
                    $bankId = $this->bank_id;
                    $amount = $this->amount;
                    $option = $this->option;

                    $balances = collect($service->getByAccountIds([1, $bankId]))->keyBy('account_head_id');
                    $bankBalance = $balances[$bankId]['balance'] ?? 0;

                    $expected = $option == 1 ? $bankBalance + $amount : $bankBalance - $amount;

                    if ($value != $expected) {
                        $fail("Invalid balance_after. Expected {$expected}.");
                    }
                }
            ],
        ];
    }

    // public function withValidator($validator)
    // {
    //     $validator->after(function ($validator) {
    //         $amount = $this->amount;
    //         $bankId = $this->bank_id;
    //         $option = $this->option;

    //         $service = new CashBankSummaryService();
    //         $balances = collect($service->getByAccountIds([1, $bankId]))->keyBy('account_head_id');

    //         $cashBalance = $balances[1]['balance'] ?? 0;
    //         $bankBalance = $balances[$bankId]['balance'] ?? 0;

    //         // FIRST: validate amount
    //         if ($option == 1) { // Deposit → cash decreases
    //             if ($amount > $cashBalance) {
    //                 $validator->errors()->add('amount', "Amount exceeds available cash balance. Current cash balance: {$cashBalance}.");
    //             }
    //             $balanceAfter = $bankBalance + $amount;
    //         } else { // Withdraw → bank decreases
    //             if ($amount > $bankBalance) {
    //                 $validator->errors()->add('amount', "Amount exceeds available bank balance. Current bank balance: {$bankBalance}.");
    //             }
    //             $balanceAfter = $bankBalance - $amount;
    //         }

    //         // SECOND: validate balance_after (merged for repository)
    //         if ($balanceAfter < 0) {
    //             $validator->errors()->add('balance_after', "Balance after cannot be negative. Current balance: {$bankBalance}.");
    //         }

    //         // Merge into request so repository can access it
    //         $this->merge(['balance_after' => $balanceAfter]);
    //     });
    // }

    public function messages()
    {
        return [
            'option.required' => 'Option is required.',
            'amount.required' => 'Amount is required.',
            'bank_id.required' => 'Bank is required.',
            'cheque_no.required' => 'Cheque no is required.',
            'balance_after.required' => 'Balance after is required.',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        $firstMessage = reset($errors)[0] ?? 'Validation error';

        $response = response()->json([
            'message' => $firstMessage,
            'errors' => $errors,
        ], 422);

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }
}