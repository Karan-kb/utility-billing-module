<?php

namespace App\Http\Requests\MeterInsurance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use App\Models\MeterIssue;
use App\Models\MeterInsurance;
use App\Services\VoucherValidationService;
use App\Services\PaymentValidationService;
use App\Helpers\NepaliCalendar;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
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
                            $fail('The ' . $attribute . ' cannot be a future date.');
                        }
                    } catch (\Exception $e) {
                        $fail('The ' . $attribute . ' is not a valid BS date.');
                    }
                },
            ],
            'date_in_ad' => ['required', 'date', function($attribute, $value, $fail) {
                if ($value > date('Y-m-d')) $fail('The ' . $attribute . ' cannot be a future date.');
            }],
            'meter_issue_id' => [
                'required', 'integer',
                function ($attr, $val, $fail) {
                    $exists = MeterIssue::where('id', $val)
                        ->where('is_active', 1)
                        ->whereNull('deleted_at')
                        ->exists();
                    if (!$exists) $fail('The selected meter issue does not exist or is inactive.');

                    $activeInsurance = MeterInsurance::where('meter_issue_id', $val)
                        ->whereNull('deleted_at')
                        ->whereHas('payments', fn($q) => $q->where('is_cancel', 0))
                        ->exists();
                    if ($activeInsurance) $fail('This meter issue already has an active meter insurance record.');
                }
            ],
            'voucher_no' => ['required', 'string', 'max:20', VoucherValidationService::validate('I', MeterInsurance::class)],
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999.99'],
            'payment_by_cash' => [
                'required','boolean',
                function($attr,$val,$fail) { PaymentValidationService::validatePaymentMethods(request(), $attr, $val, $fail); }
            ],
            'payment_by_bank' => ['required','boolean'],
            'cash_amount' => [
                'nullable','numeric','min:0','max:999999999999.99',
                function($attr,$val,$fail){ PaymentValidationService::validateCashAmount(request(), $val, $fail); }
            ],
            'bank_amount' => [
                'nullable','numeric','min:0','max:999999999999.99',
                function($attr,$val,$fail){ PaymentValidationService::validateBankAmount(request(), $val, $fail); }
            ],
            'cheque_no' => ['nullable','string','max:20','required_if:payment_by_bank,1'],
            'bank_id' => [
                'nullable','integer','required_if:payment_by_bank,1',
                function($attr,$val,$fail){ PaymentValidationService::validateBank(request(), $attr, $val, $fail); }
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'meter_issue_id.exists' => 'The selected meter issue is invalid or inactive.',
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

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }
}
