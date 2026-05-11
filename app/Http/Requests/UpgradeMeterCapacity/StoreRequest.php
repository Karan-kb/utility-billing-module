<?php

namespace App\Http\Requests\UpgradeMeterCapacity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use App\Models\MeterIssue;
use App\Models\MasterSetup;
use App\Models\UpgradeMeterCapacity;
use App\Models\MeterDepositTransaction;
use App\Services\PaymentValidationService;
use App\Helpers\NepaliCalendar;
use App\Services\VoucherValidationService;
use Carbon\Carbon;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasOrganizationPermission('create upgrade meter capacity');
    }

    /**
     * Get the validation rules that apply to the request.
     */
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
                'exists:tenant.meter_issues,id,deleted_at,NULL,is_active,1',
            ],
            'existing_capacity_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $meterIssue = MeterIssue::withoutTrashed()
                        ->where('id', $this->input('meter_issue_id'))
                        ->orderByDesc('id')
                        ->first();
                    if (!$meterIssue) {
                        $fail('Meter issue not found.');
                        return;
                    }
                    if ($meterIssue->capacity_id != $value) {
                        $fail('The existing_meter_capacity does not match the customer’s latest MeterIssue demand_capacity.');
                    }
                },
            ],
            'upgraded_capacity_id' => [
                'required',
                'integer',
                'exists:tenant.master_setups,id',
                function ($attribute, $value, $fail) {
                    if (
                        !MasterSetup::where('id', $value)
                            ->where('master_setup_type_id', 4)
                            ->where('is_active', 1)
                            ->whereNull('deleted_at')
                            ->exists()
                    ) {
                        $fail('The selected upgraded meter capacity must have type Capacity, be active, and not deleted.');
                    }
                },
            ],
        'voucher_no' => ['required', 'string', 'max:20', VoucherValidationService::validate('UG', UpgradeMeterCapacity::class)],

         
            'amount' => [
                'required',
                'numeric',
                'min:1',
                'max:999999999999.99',
                function ($attribute, $value, $fail) {
                    $chargeAmount =  $this->input('charge_amount', 0);
                    $depositAmount =  $this->input('deposit_amount', 0);

                    if (($chargeAmount + $depositAmount) !=  $value) {
                        $fail('The amount must be equal to charge_amount + deposit_amount.');
                    }
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

    /**
     * Generate fiscal year code based on current BS date
     */
    public function getFiscalYearCode(): string
    {
        $adDate = Carbon::now()->format('Y-m-d');
        $bsDate = NepaliCalendar::adToBs($adDate);
        [$year, $month] = explode('-', $bsDate);

        $fiscalYear = ((int) $month >= 4) ? (int) $year : (int) $year - 1;
        return substr($fiscalYear, 2, 2) . substr($fiscalYear + 1, 2, 2);
    }

    /**
     * Generate next voucher number for Upgrade Meter Capacity
     */
    // public function generateUpgradeVoucherNo(): string
    // {
    //     $fiscalYearCode = $this->getFiscalYearCode();
    //     $lastUpgrade = UpgradeMeterCapacity::withTrashed()
    //         ->where('voucher_no', 'like', "UG{$fiscalYearCode}%")
    //         ->orderBy('id', 'desc')
    //         ->first();

    //     $lastNumber = $lastUpgrade ? (int) substr($lastUpgrade->voucher_no, 8) : 0;
    //     return "UG{$fiscalYearCode}-" . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
    // }

    /**
     * Generate next voucher number for Deposit Transaction
     */
    public function generateDepositVoucherNo(): string
    {
        $fiscalYearCode = $this->getFiscalYearCode();
        $lastDeposit = MeterDepositTransaction::withTrashed()
            ->where('voucher_no', 'like', "D{$fiscalYearCode}%")
            ->orderBy('id', 'desc')
            ->first();

        $lastNumber = $lastDeposit ? (int) substr($lastDeposit->voucher_no, 8) : 0;
        return "D{$fiscalYearCode}-" . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
    }
}
