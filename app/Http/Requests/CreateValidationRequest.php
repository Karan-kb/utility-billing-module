<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Services\PaymentValidationService;
use App\Helpers\NepaliCalendar;
use Illuminate\Database\Eloquent\Model;

class CreateValidationRequest extends FormRequest
{
    protected ?string $modelClass = null;
    protected array|\Closure $voucherValidationRules = [];

    public function setModelClass(string $modelClass, array|\Closure $voucherValidationRules = [])
    {
        $this->modelClass = $modelClass;
        $this->voucherValidationRules = $voucherValidationRules;

    }

    public function getModelClass(): string
    {
        if (!$this->modelClass) {
            throw new \Exception("Model class not set in the request.");
        }
        return $this->modelClass;
    }
    public function rules(): array
    {
        $modelTable = $this->modelClass ? (new $this->modelClass)->getTable() : 'non_member_payments';

        $voucherRules = is_array($this->voucherValidationRules)
            ? $this->voucherValidationRules
            : [$this->voucherValidationRules];

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
            'mobile_no' => ['required', 'numeric', 'digits:10', "unique:tenant.{$modelTable},mobile_no"],

            // Voucher
            'voucher_no' => array_merge(['required', 'string', 'max:20'], $voucherRules),

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

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            PaymentValidationService::validatePaymentMethods(
                $this,
                'payment_methods',
                null,
                function ($msg) use ($validator) {
                    $validator->errors()->add('payment_methods', $msg);
                }
            );

            // Cash & Bank validation
            PaymentValidationService::validateCashAmount(
                $this,
                $this->cash_amount ?? 0,
                function ($msg) use ($validator) {
                    $validator->errors()->add('cash_amount', $msg);
                }
            );

            PaymentValidationService::validateBankAmount(
                $this,
                $this->bank_amount ?? 0,
                function ($msg) use ($validator) {
                    $validator->errors()->add('bank_amount', $msg);
                }
            );

            if ($this->bank_id) {
                PaymentValidationService::validateBank(
                    $this,
                    'bank_id',
                    $this->bank_id,
                    function ($msg) use ($validator) {
                        $validator->errors()->add('bank_id', $msg);
                    }
                );
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}
