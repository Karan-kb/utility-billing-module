<?php

namespace App\Http\Requests\OtherIncomeReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use App\Models\OtherIncomeSetup;
use App\Services\PaymentValidationService;
use App\Services\VoucherValidationService;
use App\Helpers\NepaliCalendar;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_in_bs' => [
                'required',
                'string',
                'max:10',
                'regex:/^\d{4}-\d{2}-\d{2}$/',
                function ($attribute, $value, $fail) {
                    try {
                        $adDate = NepaliCalendar::bsToAd($value);
                        if ($adDate > date('Y-m-d')) {
                            $fail("The {$attribute} cannot be a future date.");
                        }
                    } catch (\Exception $e) {
                        $fail("The {$attribute} is not a valid BS date.");
                    }
                },
            ],
            'date_in_ad' => [
                'required',
                'string',
                'date',
                function ($attribute, $value, $fail) {
                    if ($value > date('Y-m-d')) {
                        $fail("The {$attribute} cannot be a future date.");
                    }
                },
            ],

            'voucher_no' => [
                'required',
                'string',
                'max:20',
                VoucherValidationService::validate('O', \App\Models\OtherIncomeReceipt::class),
            ],

            'meter_issue_id' => [
                'required',
                'integer',
                'exists:tenant.meter_issues,id,deleted_at,NULL,is_active,1',
            ],
           'payment_by_bank' => ['required', 'boolean'],

            'cash_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99',
                function ($attr, $val, $fail) {
                    PaymentValidationService::validateCashAmount($this, $val, $fail);
                }
            ],

            'bank_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99',
                function ($attr, $val, $fail) {
                    PaymentValidationService::validateBankAmount($this, $val, $fail);
                }
            ],

            'cheque_no' => ['nullable', 'string', 'max:20', 'required_if:payment_by_bank,1'],

            'bank_id' => [
                'nullable',
                'integer',
                'required_if:payment_by_bank,1',
                function ($attr, $val, $fail) {
                    PaymentValidationService::validateBank($this, $attr, $val, $fail);
                }
            ],

 
            'income_heads' => 'required|array',
            'income_heads.*.id' => 'required|exists:tenant.account_heads,id,deleted_at,NULL',
            'income_heads.*.amount' => [
                'required', 
                'numeric',
                'min:0',
                'max:999999999999.99',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1] ?? null;
                    if ($index !== null) {
                        $incomeHeadId = request()->input("income_heads.$index.id");
                        $charge = OtherIncomeSetup::withoutTrashed()->find($incomeHeadId);
                        if ($charge && $value != 0 && bccomp($value, $charge->charge_amount, 4) !== 0) {
                            $fail("Amount for income head ID {$incomeHeadId} must be 0 or equal to charge amount ({$charge->charge_amount}).");
                        }
                    }
                }
            ],
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:999999999999.99',
                function ($attribute, $value, $fail) {
                    $sum = collect(request()->input('income_heads', []))->sum(fn($head) => (float) $head['amount']);
                    if (bccomp($sum, (float) $value, 4) !== 0) {
                        $fail("The {$attribute} must be equal to the sum of all income head amounts ({$sum}).");
                    }
                }
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'meter_issue_id.exists' => 'The selected meter issue is invalid or inactive.',
            'income_heads.required' => 'At least one income head must be selected.',
        ];
    }

    /**
     * Return JSON response on validation failure
     */
    protected function failedValidation(Validator $validator)
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
