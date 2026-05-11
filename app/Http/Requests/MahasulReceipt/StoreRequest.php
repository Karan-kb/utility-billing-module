<?php

namespace App\Http\Requests\MahasulReceipt;

use App\Services\FindTotalDueAmountServiceAndFindAdvancePayment;
use Illuminate\Foundation\Http\FormRequest;
use App\Services\VoucherValidationService;
use App\Services\PaymentValidationService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $meterIssueId = $this->input('meter_issue_id');
        $dueService = app(FindTotalDueAmountServiceAndFindAdvancePayment::class);

        // Fetch previous amounts from service
        $previousDue = $meterIssueId ? round((float) $dueService->getPreviousDueFromLastReceipt($meterIssueId), 2) : 0;
        $previousAdvance = $meterIssueId ? round((float) $dueService->getTotalAdvancePayment($meterIssueId), 2) : 0;
        $previousFine = $meterIssueId ? round((float) $dueService->getTotalFineAmount($meterIssueId), 2) : 0;
        $previousUnit = $meterIssueId ? round((float) $dueService->getTotalUnitAmount($meterIssueId), 2) : 0;
        $previousDemand = $meterIssueId ? round((float) $dueService->getTotalDemandChargeAmount($meterIssueId), 2) : 0;
        $previousSubsidy = $meterIssueId ? round((float) $dueService->getTotalSubsidyChargeAmount($meterIssueId), 2) : 0;
        $previousService = $meterIssueId ? round((float) $dueService->getTotalServiceChargeAmount($meterIssueId), 2) : 0;
        $previousOther = $meterIssueId ? round((float) $dueService->getTotalOtherChargeAmount($meterIssueId), 2) : 0;
        $previousBlacklist = $meterIssueId ? round((float) $dueService->getTotalBlacklistAmount($meterIssueId), 2) : 0;

        return [
            'date_in_bs' => [
                'required',
                'string',
                'max:10',
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
                },
            ],

            'date_in_ad' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    if ($value > date('Y-m-d')) {
                        $fail("The {$attribute} cannot be a future date.");
                    }
                }
            ],

            'meter_issue_id' => [
                'required',
                'integer',
                'exists:tenant.meter_issues,id,deleted_at,NULL,is_active,1',
            ],

            'voucher_no' => [
                'required',
                'string',
                'max:20',
                'unique:tenant.mahasul_receipts,voucher_no',
                VoucherValidationService::validate('M', \App\Models\MahasulReceiptEntry::class)
            ],

            'unit_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999.99',
                function ($attr, $val, $fail) use ($previousUnit) {
                    if (round((float) $val, 2) !== $previousUnit) {
                        $fail("Unit amount must be exactly {$previousUnit}.");
                    }
                }
            ],

            'demand_charge' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                function ($attr, $val, $fail) use ($previousDemand) {
                    if (round((float) $val, 2) !== $previousDemand) {
                        $fail("Demand charge must be exactly {$previousDemand}.");
                    }
                }
            ],

            'service_charge' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                function ($attr, $val, $fail) use ($previousService) {
                    if (round((float) $val, 2) !== $previousService) {
                        $fail("Service charge must be exactly {$previousService}.");
                    }
                }
            ],

            'subsidy_charge' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                function ($attr, $val, $fail) use ($previousSubsidy) {
                    if (round((float) $val, 2) !== $previousSubsidy) {
                        $fail("Subsidy charge must be exactly {$previousSubsidy}.");
                    }
                }
            ],

            'other_charge' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                function ($attr, $val, $fail) use ($previousOther) {
                    if (round((float) $val, 2) !== $previousOther) {
                        $fail("Other charge must be exactly {$previousOther}.");
                    }
                }
            ],

            'black_list_charge' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                function ($attr, $val, $fail) use ($previousBlacklist) {
                    if (round((float) $val, 2) !== $previousBlacklist) {
                        $fail("Blacklist charge must be exactly {$previousBlacklist}.");
                    }
                }
            ],

            'fine_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                function ($attr, $val, $fail) use ($previousFine) {
                    if (round((float) $val, 2) !== $previousFine) {
                        $fail("Fine amount must be exactly {$previousFine}.");
                    }
                }
            ],

            // Other numeric fields
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'rebate_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                function ($attribute, $value, $fail) {
                    $fine = $this->input('fine_amount', 0);
                    if ($fine > 0 && $value > 0) {
                        $fail("Cannot have both fine amount and rebate amount. Only one is allowed.");
                    }
                }
            ],


            'total_due_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],

            'total_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999.99',
                function ($attr, $val, $fail) use ($previousDue) {
                    $calculated = round(
                        $this->input('unit_amount', 0)
                        + $previousDue
                        + $this->input('fine_amount', 0)
                        + $this->input('demand_charge', 0)
                        + $this->input('subsidy_charge', 0)
                        + $this->input('service_charge', 0)
                        + $this->input('other_charge', 0)
                        + $this->input('black_list_charge', 0)
                        - $this->input('discount_amount', 0)
                        - $this->input('rebate_amount', 0),
                        2
                    );

                    if (round((float) $val, 2) !== $calculated) {
                        $fail("Total amount must be exactly {$calculated} including previous due of {$previousDue}.");
                    }
                }
            ],

            'advance_payment' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'paid_amount' => ['required', 'numeric', 'min:1', 'max:999999999999.99'],

            // Payment fields
            'cheque_no' => ['nullable', 'string', 'max:20', 'required_if:payment_by_bank,1'],
            'bank_id' => [
                'nullable',
                'integer',
                'required_if:payment_by_bank,1',
                function ($attr, $val, $fail) {
                    PaymentValidationService::validateBank($this, $attr, $val, $fail);
                }
            ],

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
        ];
    }

    public function messages(): array
    {
        return [
            'meter_issue_id.exists' => 'The selected meter issue is invalid or inactive.',
        ];
    }

    protected function failedValidation(Validator $validator): void
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
