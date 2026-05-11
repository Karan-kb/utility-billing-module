<?php

namespace App\Http\Requests\ExpenseAndReceivableTracker;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\VoucherValidationService;
use App\Helpers\NepaliCalendar;
use App\Services\PaymentValidationService;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    protected function prepareForValidation()
{
    // Detect type from the URL path
    $isReceivable = str_contains($this->path(), 'receivables');
    
    $this->merge([
        'type' => $isReceivable ? 1 : 0,
    ]);
}
    public function rules()
    {
        $type = $this->input('type', 0); // default 0 = Expense
        $prefix = $type == 1 ? 'R' : 'E';

        return [
            // Voucher no must match format E8283-000001 or R8283-000001
            'voucher_no' => [
                'required',
                'string',
                VoucherValidationService::validate($prefix, \App\Models\ExpenseAndReceivableTracker::class)
            ],

            

            // BS date validation
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

            'expenses' => 'required|array|min:1',

             'expenses.*.account_head_id'  => [
                    'required',
                    'integer',
                    Rule::exists('tenant.account_heads', 'id')
                        ->where('deleted_at', null)
                        ->where('is_active', 1),
                ],
            'expenses.*.ref_bill_no' => 'nullable|string|max:50',
            'expenses.*.is_payment_by_bank' => 'required|boolean',
            
            'expenses.*.bank_id' => [
                'nullable',
                'integer',
                function ($attr, $val, $fail) {
                    PaymentValidationService::validateBank($this, $attr, $val, $fail);
                }
            ],
            'expenses.*.cheque_no' => [
                'nullable',
                'string',
                'max:20',
                'required_if:expenses.*.is_payment_by_bank,1',
            ],

            // Amount validation
            'expenses.*.amount' => ['required','numeric','min:1','max:999999999999.99'],

            // Particular
            'expenses.*.particular' => 'nullable|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'voucher_no.required' => 'Voucher number is required.',
            'voucher_no.string' => 'Voucher number must be a string.',
            'date_in_bs.required' => 'Date in BS is required.',
            'expenses.required' => 'At least one expense item is required.',
            'expenses.*.account_head_id.required' => 'Account head is required for each expense.',
            'expenses.*.amount.required' => 'Amount is required for each expense.',
            'expenses.*.amount.min' => 'Amount must be at least 1.',
            'expenses.*.bank_id.required_if' => 'Bank is required when payment is by bank.',
            'expenses.*.cheque_no.required_if' => 'Cheque number is required when payment is by bank.',
        ];
    }
}