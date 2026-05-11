<?php

namespace App\Http\Requests\AdvancePayment;

use App\Helpers\NepaliCalendar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\VoucherValidationService;
use App\Services\PaymentValidationService;
use App\Models\ActivityLog;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;



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
                'max:10',
                'regex:/^\d{4}-\d{2}-\d{2}$/',
                function ($attribute, $value, $fail) {
                    if ($value > date('Y-m-d')) {
                        $fail("The {$attribute} cannot be a future date.");
                    }
                },
            ],

            'meter_issue_id' => [
                'required',
                'integer',
                'exists:tenant.meter_issues,id,is_active,1',
            ],

            'voucher_no' => [
                'required',
                'string',
                'max:20',
                VoucherValidationService::validate('A', \App\Models\AdvancePayment::class),
            ],

            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999.99'],

            'payment_by_cash' => [
                'required',
                'boolean',
                function ($attr, $val, $fail) {
                    PaymentValidationService::validatePaymentMethods($this, $attr, $val, $fail);
                }
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
        ];
    }

//     protected function failedValidation(Validator $validator)
// {
//     throw new HttpResponseException(response()->json([
//         'status'  => false,
//         'message' => 'Validation failed',
//         'errors'  => $validator->errors(),
//     ], 422));
// }

protected function failedValidation(Validator $validator)
{
    $errors = $validator->errors();

    // Get first error message
    $firstError = $errors->first();

    throw new HttpResponseException(response()->json([
        'status'  => false,
        'message' => $firstError,
        'errors'  => $errors,
    ], 422));
}
}
