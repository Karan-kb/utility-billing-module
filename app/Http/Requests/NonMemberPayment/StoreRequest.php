<?php

namespace App\Http\Requests\NonMemberPayment;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\VoucherValidationService;
use App\Services\PaymentValidationService;
use App\Helpers\NepaliCalendar;
use App\Models\NonMemberPayment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            // Dates
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
                'date',
                function ($attribute, $value, $fail) {
                    if ($value > date('Y-m-d')) {
                        $fail("The {$attribute} cannot be a future date.");
                    }
                },
            ],

            // Customer info
            'customer_name_en' => ['required', 'string', 'max:50'],
            'customer_name_np' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string'],
            'mobile_no' => ['required', 'numeric', 'digits:10'],

            // Voucher
            'voucher_no' => ['required', 'string', 'max:20', VoucherValidationService::validate('NM', NonMemberPayment::class)],

            // Payment info
            'consumption_unit' => ['required', 'numeric', 'min:0'],
            'demand_charge' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999.99'],

            'payment_by_cash' => ['required', 'boolean'],
            'payment_by_bank' => ['required', 'boolean'],
            'cash_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'bank_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'cheque_no' => ['nullable', 'string', 'max:20', 'required_if:payment_by_bank,1'],
            'bank_id' => ['nullable', 'integer', 'required_if:payment_by_bank,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'transformer_id.exists' => 'The selected transformer is invalid.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        $firstMessage = reset($errors)[0] ?? 'Validation error';

        $response = response()->json([
            'message' => $firstMessage,
            'errors' => $errors,
        ], 422);

        throw new ValidationException($validator, $response);
    }
}