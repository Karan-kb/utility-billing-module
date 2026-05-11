<?php

namespace App\Http\Requests\NEAPaymentEntry;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\NEAPurchase;
use App\Services\VoucherValidationService;
use App\Services\PaymentValidationService;
use App\Helpers\NepaliCalendar;
use App\Helpers\NepaliMonthHelper;
use App\Models\MasterSetup;
use App\Models\NEAPaymentEntry;
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
            'month' => ['required','integer','between:1,12'],
            'date_in_bs' => [
                'required','string','max:10','regex:/^\d{4}-\d{2}-\d{2}$/',
                function ($attr, $value, $fail) {
                    try {
                        $adDate = NepaliCalendar::bsToAd($value);
                        if ($adDate !== date('Y-m-d')) {
                            $fail("The {$attr} must be today's date.");
                        }
                    } catch (\Exception $e) {
                        $fail("The {$attr} is not a valid BS date.");
                    }
                }
            ],
            'date_in_ad' => [
                'required','string','max:10','regex:/^\d{4}-\d{2}-\d{2}$/',
                function ($attr, $value, $fail) {
                    if ($value !== date('Y-m-d')) {
                        $fail("The {$attr} must be today's date.");
                    }
                }
            ],
          'transformer_id' => [
                'required','integer',
                function ($attr, $value, $fail) {

                    $transformer = MasterSetup::on('tenant')
                        ->where('id', $value)
                        ->where('master_setup_type_id', 5)
                        ->where('is_active', 1)
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$transformer) {
                        $fail('The selected transformer does not exist.');
                        return;
                    }

                    $transformerName = $transformer->name_en ?? "Transformer {$value}";

                    $month = (int) $this->month;
                    $monthName = NepaliMonthHelper::getMonthName($month);

                    $purchase = NEAPurchase::on('tenant')
                        ->where('transformer_id', $value)
                        ->where('month', $month)
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$purchase) {
                        $fail("No NEA purchase found for {$transformerName} in {$monthName}.");
                        return;
                    }

                    $alreadyPaid = NEAPaymentEntry::on('tenant')
                        ->whereNull('deleted_at')
                        ->where('is_cancel', 0)
                        ->where('transformer_id', $value)
                        ->where('month', $month)
                        ->exists();

                    if ($alreadyPaid) {
                        $fail("Payment already exists for {$transformerName} in {$monthName}.");
                        return;
                    }
                }
            ],
            
            'fine_amount' => ['required','numeric','min:0'],
            'rebate_amount' => ['required','numeric','min:0'],
         'total_amount' => [
    'required', 'numeric', 'min:1',
    function ($attr, $value, $fail) {
        $data = $this->all();

        $transformerId   = (int)($data['transformer_id'] ?? 0);
        $month           = (int)($data['month'] ?? 0);
        $fineAmount      = isset($data['fine_amount']) ? (float)$data['fine_amount'] : 0;
        $rebateAmount    = isset($data['rebate_amount']) ? (float)$data['rebate_amount'] : 0;

        $calc = \App\Services\NeaPaymentCalculationService::calculateTotalAmount(
            $transformerId,
            $month,
            $fineAmount,
            $rebateAmount
        );

        $expectedTotal = round($calc['total_amount'], 2);

        if ((float) $value != $expectedTotal) {
            $fail("{$attr} must be exactly: {$expectedTotal}. Provided: {$value}.");
        }
    }
],
            'voucher_no' => ['required','string','max:20', VoucherValidationService::validate('NE', \App\Models\NEAPaymentEntry::class)],
            'cheque_no' => ['nullable','string','max:20','required_if:payment_by_bank,1'],
            'bank_id' => [
                'nullable','integer','required_if:payment_by_bank,1',
                function ($attr, $val, $fail) {
                    PaymentValidationService::validateBank(request(), $attr, $val, $fail);
                }
            ],
            'paid_amount' => [
    'required',
    'numeric',
    'min:1',
    'max:999999999999.99',
    function ($attribute, $value, $fail) {
        $totalAmount = $this->total_amount ?? 0; // amount field from request
        if ($value > $totalAmount) {
            $fail("The {$attribute} cannot be greater than the total amount ({$totalAmount}).");
        }
    },
],
            'due_amount' => [
    'required', 'numeric', 'min:0',
    function ($attr, $value, $fail) {
        $data        = $this->all();
        $transformerId = (int)($data['transformer_id'] ?? 0);
        $month         = (int)($data['month'] ?? 0);
        $fineAmount    = isset($data['fine_amount']) ? (float)$data['fine_amount'] : 0;
        $rebateAmount  = isset($data['rebate_amount']) ? (float)$data['rebate_amount'] : 0;

        $calc        = \App\Services\NeaPaymentCalculationService::calculateTotalAmount(
            $transformerId,
            $month,
            $fineAmount,
            $rebateAmount
        );

        $totalAmount   = $calc['total_amount'];
        $paidAmount    = (float)($data['paid_amount'] ?? 0);

        $expectedDue = max(0, $totalAmount - $paidAmount);

        if ((float)$value != round($expectedDue, 2)) {
            $fail("{$attr} must be (total_amount - paid_amount) = {$expectedDue}. Provided: {$value}.");
        }
    }
],
            'payment_by_cash' => [
                'required','boolean',
                function ($attr, $val, $fail) {
                    PaymentValidationService::validatePaymentMethods(request(), $attr, $val, $fail);
                }
            ],
            'payment_by_bank' => ['required','boolean'],
            'cash_amount' => [
                'nullable','numeric','min:0','max:999999999999.99',
                function ($attr, $val, $fail) {
                    PaymentValidationService::validateCashAmount(request(), $val, $fail);
                }
            ],
            'bank_amount' => [
                'nullable','numeric','min:0','max:999999999999.99',
                function ($attr, $val, $fail) {
                    PaymentValidationService::validateBankAmount(request(), $val, $fail);
                }
            ],
        ];
    }

     protected function passedValidation()
{
    $data = $this->validated();

   

    // Get all previous months purchases
    $previousPurchases = NEAPurchase::on('tenant')
        ->where('transformer_id', $data['transformer_id'])
        ->where('month', '<', $data['month'])
        ->whereNull('deleted_at')
        ->orderBy('month', 'asc')
        ->get();

    foreach ($previousPurchases as $prev) {

        $hasValidPayment = NEAPaymentEntry::on('tenant')
            ->where('transformer_id', $data['transformer_id'])
            ->where('month', $prev->month)
            ->where('is_cancel', 0)
            ->exists();

        if (!$hasValidPayment) {
            throw ValidationException::withMessages([
                'month' => "Month {$prev->month} must be paid first before paying month {$data['month']}."
            ]);
        }
    }
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