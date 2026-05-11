<?php

namespace App\Http\Requests\NeaPurchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use App\Helpers\NepaliCalendar;
use App\Models\MasterSetup;
use App\Models\NEAPurchase;
use App\Models\FiscalYear;

class StoreRequest extends FormRequest
{
    // public function authorize(): bool
    // {
    //     return true;
    // }

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
                            $fail("The {$attribute} cannot be a future date.");
                        }
                    } catch (\Exception $e) {
                        $fail("The {$attribute} is not a valid BS date.");
                    }
                },
            ],
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
                'month' => [
                    'required',
                    'integer',
                    'between:1,12',
                    function ($attribute, $value, $fail) {
                        $dateBs = $this->input('date_in_bs');

                        if ($dateBs) {
                            try {
                                // Extract month from BS date (YYYY-MM-DD)
                                $bsMonth = (int) explode('-', $dateBs)[1];

                                if ((int)$value !== $bsMonth) {
                                    $fail("The date must match the month.");
                                }
                            } catch (\Exception $e) {
                                $fail("Invalid date_in_bs format.");
                            }
                        }
                    }
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

            'transformer_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {

                    // Check transformer exists and is active
                    $exists = MasterSetup::on('tenant')
                        ->where('id', $value)
                        ->where('master_setup_type_id', 5)
                        ->where('is_active', 1)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (!$exists) {
                        $fail("The selected transformer does not exist or is inactive.");
                        return;
                    }

                    $bsMonth = (int) $this->month;

                    // Validate month between 1 and 12
                    if ($bsMonth < 1 || $bsMonth > 12) {
                        throw ValidationException::withMessages([
                            'month' => 'Month must be between 1 (Baishakh) and 12 (Chaitra).'
                        ]);
                    }

                    // Get current fiscal year dynamically
                    $currentFiscalYear = FiscalYear::where('status', 1)
                        ->orderByDesc('id')
                        ->first();

                    if (!$currentFiscalYear) {
                        $fail("No active fiscal year found.");
                        return;
                    }

                    $fiscalYearId = $currentFiscalYear->id;

                    // Duplicate check: transformer + month + fiscal year
                    $duplicate = NeaPurchase::where('transformer_id', $value)
                        ->where('month', $bsMonth)
                        ->where('fiscal_year_id', $fiscalYearId)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($duplicate) {
                        $fail("This transformer already has a purchase for this month in the current fiscal year.");
                    }

                    // Sequential month check
                    $lastPurchase = NeaPurchase::where('transformer_id', $value)
                        ->where('fiscal_year_id', $fiscalYearId)
                        ->whereNull('deleted_at')
                        ->orderByDesc('month')
                        ->first();

                    if ($lastPurchase) {
                        $nepaliMonths = [
                            1 => 'Baishakh',
                            2 => 'Jestha',
                            3 => 'Ashadh',
                            4 => 'Shrawan',
                            5 => 'Bhadra',
                            6 => 'Ashwin',
                            7 => 'Kartik',
                            8 => 'Mangsir',
                            9 => 'Poush',
                            10 => 'Magh',
                            11 => 'Falgun',
                            12 => 'Chaitra',
                        ];

                        $expectedMonth = $lastPurchase->month == 12 ? 1 : $lastPurchase->month + 1;

                        if ($bsMonth != $expectedMonth) {
                            $lastMonthName = $nepaliMonths[$lastPurchase->month] ?? $lastPurchase->month;
                            $expectedMonthName = $nepaliMonths[$expectedMonth] ?? $expectedMonth;

                            throw ValidationException::withMessages([
                                'month' => "Invalid purchase month sequence. After {$lastMonthName}, the next purchase must be {$expectedMonthName}."
                            ]);
                        }
                    }

                    // Merge fiscal year into request
                    $this->merge(['fiscal_year_id' => $fiscalYearId]);
                },
            ],

            'total_units' => ['required', 'numeric', 'gt:0'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
           
        ];
    }

    protected function passedValidation()
    {
        $bsDate = $this->date_in_bs;
        $adDate = $this->date_in_ad ?? NepaliCalendar::bsToAd($bsDate);

        [$bsYear, $bsMonth, $bsDay] = array_map('intval', explode('-', $bsDate));
        $todayBs = NepaliCalendar::adToBs(date('Y-m-d'));
        [$tYear, $tMonth, $tDay] = array_map('intval', explode('-', $todayBs));

        // Ensure BS date is in current BS month
        // if ($bsYear !== $tYear || $bsMonth !== $tMonth) {
        //     throw ValidationException::withMessages([
        //         'month' => ['BS date must belong to the current BS month.'],
        //     ]);
        // }

        if ($bsDay > $tDay) {
            throw ValidationException::withMessages([
                'date_in_bs' => ['Cannot create purchase for a future BS date.'],
            ]);
        }

        // Merge cleaned/converted fields
        $this->merge([
            'date_in_ad' => $adDate,
            'month' => $bsMonth,
            'total_units' => (int) $this->total_units,
            'transformer_id' => (int) $this->transformer_id,
        ]);
    }

    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors()->toArray();
        $firstMessage = collect($errors)->flatten()->first() ?? 'Validation error';

        throw new ValidationException(
            $validator,
            response()->json([
                'message' => $firstMessage,
                'errors' => $errors,
            ], 422)
        );
    }
}