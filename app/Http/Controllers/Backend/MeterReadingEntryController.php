<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\NepaliCalendar;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DateConversionController;
use App\Models\DisabilityDiscount;
use App\Models\DiscountAndFine;
use App\Models\Fine;
use App\Models\MahasulReceiptEntry;
use App\Models\MasterSetup;
use App\Models\MemberEntry;
use App\Models\MeterIssue;
use App\Models\RateAndCapacity;
use App\Models\MeterReadingEntry;
use App\Models\TariffSetup;
use App\Models\User;
use App\Models\VoucherSummary;
use App\Models\VoucherSummaryDetail;
use App\Services\BlacklistService;
use App\Services\CustomerDueService;
use App\Services\FineService;
use App\Services\MemberResolverService;
use Doctrine\DBAL\Query\QueryException;
use Illuminate\Http\Request;
use App\Enums\BlacklistContext;
use App\Helpers\Helper;
use App\Models\CustomerTransaction;
use App\Models\FiscalYear;
use App\Models\NameTransferEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Services\PaymentAllocationService;
use App\Services\VoucherEntryService;
use App\Services\PaymentService;
use App\Services\KnowMeterStartService;
use App\Services\MemberInfoFromMeterIssueService;
use App\Helpers\NepaliDateHelper;
use App\Helpers\TenantRuntimeHelper;
use App\Services\VoucherBalanceService;
use Illuminate\Support\Facades\Validator;
class MeterReadingEntryController extends Controller
{
    protected $dateConversionController;
    protected PaymentService $paymentService;
    protected PaymentAllocationService $allocationService;
    protected $dueService;

    protected FineService $fineService;
    protected $knowMeterStartService;


    public function __construct(DateConversionController $dateConversionController, PaymentService $paymentService, protected VoucherEntryService $voucherService, FineService $fineService, PaymentAllocationService $allocationService, CustomerDueService $dueService, KnowMeterStartService $knowMeterStartService)
    {
        $this->dateConversionController = $dateConversionController;
        $this->paymentService = $paymentService;
        $this->fineService = $fineService;
        $this->allocationService = $allocationService;
        $this->voucherService = $voucherService;
        $this->dueService = $dueService;
        $this->knowMeterStartService = $knowMeterStartService;


    }


    public function createMeterReadingEntry(Request $request)
    {
        $meterStartService = new KnowMeterStartService();
        $connection = (new MeterReadingEntry)->getConnectionName() ?: config('database.default');

        try {
            return DB::connection($connection)->transaction(function () use ($request, $meterStartService) {
                $meterIssue = MeterIssue::find($request->input('meter_issue_id'));
                if (!$meterIssue) {
                    return response()->json(['message' => 'Meter issue not found !'], 422);
                }
                $previousUnit = $meterStartService->getMeterStartNo($request->input('meter_issue_id'));

                    $fiscalYearID = Helper::getActiveFiscalYearId();
                    $validationService = new \App\Services\MeterReadingValidationService();
                    $validationService->validateCreation(
                        $request->input('meter_issue_id'),
                        (int)$request->input('reading_month_in_bs'),
                        $request->input('reading_date_in_bs'),
                        $fiscalYearID
                    );
                $validated = $request->validate([
                    'status' => ['nullable', 'integer', 'in:1,2,3'],
                    'reading_month_in_bs' => [
                        'required',
                        'integer',
                        function ($attribute, $value, $fail) use ($request) {
                            $month = (int) $value;
                            try {
                                $currentBsDate = $this->dateConversionController->getCurrentBsDate();
                                // [$currentBsYear, $currentBsMonth] = explode('-', substr($currentBsDate, 0, 7));
                                // $currentBsMonth = (int) $currentBsMonth;

                                // $isValidMonth = ($month >= 4 && $month <= 12) || ($month >= 1 && $month <= 3);
                                // if (!$isValidMonth)
                                //     $fail("Reading month must be between Shrawan (4) and Ashad (3) of the fiscal year.");

                                // if ($month > $currentBsMonth)
                                //     $fail("Cannot create record for month {$month}. Cannot create future month.");
                                [$currentBsYear, $currentBsMonth] = explode('-', substr($currentBsDate, 0, 7));
                                    $currentBsYear = (int) $currentBsYear;
                                    $currentBsMonth = (int) $currentBsMonth;
                                $isValidMonth = ($month >= 4 && $month <= 12) || ($month >= 1 && $month <= 3);
                                if (!$isValidMonth)
                                    $fail("Reading month must be between Shrawan (4) and Ashad (3) of the fiscal year.");
                                    // Extract year from reading_date_in_bs
                                    $readingDate = $request->input('reading_date_in_bs');
                                    [$readingYear, $readingMonthFromDate] = explode('-', substr($readingDate, 0, 7));
                                    $readingYear = (int) $readingYear;

                                    // Compare YEAR first, then MONTH
                                    if (
                                        $readingYear > $currentBsYear ||
                                        ($readingYear == $currentBsYear && $month > $currentBsMonth)
                                    ) {
                                        $fail("Cannot create record for future month.");
                                    }
                            } catch (\Exception $e) {
                                $fail('Unable to validate reading month against current date.');
                            }

                            $meterIssueId = $request->input('meter_issue_id');
                            $isIrrigationCustomer = false;
                            if (TenantRuntimeHelper::isRuntimeBidut()) {
                            $meterIssue = MeterIssue::where('id', $meterIssueId)
                                ->where('is_active', 1)
                                ->first();

                            if ($meterIssue) {
                                $purposeSetup = MasterSetup::where('id', $meterIssue->purpose_id)
                                    ->where('master_setup_type_id', 1)
                                    ->where('name_en', 'Irrigation')
                                    ->where('is_active', true)
                                    ->first();

                                if ($purposeSetup) {
                                    $isIrrigationCustomer = true;
                                }
                            }

                            if ($isIrrigationCustomer) {
                                return;
                            }
                            }
                            $latestEntry = MeterReadingEntry::withoutTrashed()
                                ->where('meter_issue_id', $meterIssueId)
                                ->where('entry_type', 1)
                                ->orderBy('reading_month_in_bs', 'desc')
                                ->first();

                            if ($latestEntry) {
                                $lastMonth = (int) $latestEntry->reading_month_in_bs;
                                $currentMonth = (int) $value;
                                $expectedMonth = $lastMonth == 12 ? 1 : $lastMonth + 1;

                                // if ($currentMonth < $lastMonth) {
                                //     $fail('Cannot create a record for a month earlier than the latest record (' . $latestEntry->reading_month_in_bs . ').');
                                // }

                                if ($currentMonth != $expectedMonth) {
                                    $fail('The next record must be for the immediate next month (' . $expectedMonth . ') in the fiscal year.');
                                }
                            }
                        }
                    ],
                    'reading_date_in_bs' => [
                        'required',
                        'string',
                        'max:10',
                        'regex:/^\d{4}-\d{2}-\d{2}$/',
                        function ($attribute, $value, $fail) {
                            try {
                                $adDate = NepaliCalendar::bsToAd($value);
                                $today = date('Y-m-d');
                                if ($adDate > $today) {
                                    $fail('The ' . $attribute . ' cannot be a future date.');
                                }
                            } catch (\Exception $e) {
                                $fail('The ' . $attribute . ' is not a valid BS date.');
                            }
                        },
                    ],
                    'reading_date_in_ad' => [
                        'required',
                        'date',
                        'before_or_equal:today'
                    ],
                    'meter_issue_id' => [
                        'required',
                        'integer',
                        function ($attribute, $value, $fail) use ($request) {
                            $customerExists = MeterIssue::on('tenant')
                                ->where('id', $value)
                                ->where('is_active', 1)
                                ->exists();

                            if (!$customerExists) {
                                $fail('The selected member does not exist or is inactive in meter issue.');
                                return;
                            }

                            $readingMonth = $request->input('reading_month_in_bs');
                            if (
                                MeterReadingEntry::withoutTrashed()
                                    ->where('meter_issue_id', $value)
                                    ->where('entry_type', 1)
                                    ->where('reading_month_in_bs', $readingMonth)
                                    ->exists()
                            ) {
                                $fail('A meter reading entry for this member already exists for the specified month.');
                            }
                        },
                    ],
                    'current_unit' => [
                        'required',
                        'numeric',
                        'min:0',
                        'max:100000',
                        function ($attribute, $value, $fail) use ($previousUnit) {
                            $MAX = 100000;
                            $ROLLOVER_THRESHOLD = 1000;

                            if ($value >= $previousUnit)
                                return;

                            if ($previousUnit >= ($MAX - $ROLLOVER_THRESHOLD) && $value <= $ROLLOVER_THRESHOLD) {
                                return;
                            }
                            $fail("Current unit ({$value}) cannot be less than previous unit ({$previousUnit}) unless meter rolled over.");
                        },
                    ],
                    'unit_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.9999'],
                    'minimum_demand' => [
                        'required',
                        'numeric',
                        'min:0',
                        function ($attribute, $value, $fail) use ($request) {
                            self::validateCustomerCharge($fail, $request, 'minimum_demand', $value);
                        },
                    ],
                    'subsidy_charge' => [
                        'required',
                        'numeric',
                        'min:0',
                        function ($attribute, $value, $fail) use ($request) {
                            self::validateCustomerCharge($fail, $request, 'subsidy_charge', $value);
                        },
                    ],
                    'service_charge' => [
                        'required',
                        'numeric',
                        'min:0',
                        function ($attribute, $value, $fail) use ($request) {
                            self::validateCustomerCharge($fail, $request, 'service_charge', $value);
                        },
                    ],
                    'other_charge' => [
                        'required',
                        'numeric',
                        'min:0',
                        function ($attribute, $value, $fail) use ($request) {
                            self::validateCustomerCharge($fail, $request, 'other_charge', $value);
                        },
                    ],
                    'fine_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'total_charge' => ['required', 'numeric', 'min:1', 'max:999999999999.99'],
                ]);

                $unitAmount = $validated['unit_amount'] ?? 0;
                $minDemand = $validated['minimum_demand'] ?? 0;
                $subsidy = $validated['subsidy_charge'] ?? 0;
                $service = $validated['service_charge'] ?? 0;
                $other = $validated['other_charge'] ?? 0;
                $fineAmount = $validated['fine_amount'] ?? 0;
                $totalCharge = $validated['total_charge'] ?? 0;

                $expectedSubtotal = $unitAmount + $minDemand + $subsidy + $service + $other;
                $expectedTotal = $expectedSubtotal + $fineAmount;

                if (abs($totalCharge - $expectedTotal) > 0.1) {
                    return response()->json([
                        'message' => 'Invalid total_charge: must equal sub_total_charge + fine_amount.',
                        'expected_total_charge' => round($expectedTotal, 2),
                        'provided_total_charge' => round($totalCharge, 2),
                    ], 422);
                }

                $meterIssue = MeterIssue::findOrFail($validated['meter_issue_id']);
                $currentUnit = $validated['current_unit'];

                $discountUnit = 0;
                $memberEntry = MemberEntry::on('tenant')->find($meterIssue->member_entry_id);
                if ($memberEntry && $memberEntry->is_disable) {
                    $discount = DisabilityDiscount::first();
                    if ($discount && $discount->is_applied) {
                        $discountUnit = $discount->units ?? 0;
                    }
                }

                $MAX = 100000;
                $rolloverCount = 0;
                if ($currentUnit < $previousUnit) {
                    $rolloverCount = 1;
                    $consumedUnit = ($MAX - $previousUnit) + $currentUnit;
                } else {
                    $consumedUnit = $currentUnit - $previousUnit;
                }

                $unitBeforeDiscount = $consumedUnit;
                $totalUnit = max(0, $consumedUnit - $discountUnit);

                $tariff = TariffSetup::where('is_active', 1)->first();
                if (!$tariff)
                    return response()->json(['message' => 'No active tariff setup found !'], 422);

               
                //     if (\App\Helpers\TenantRuntimeHelper::isRuntimeKhanepani()) {
                //         $rateRow = RateAndCapacity::where('tariff_setup_id', $tariff->id)
                //             ->where('unit_from', '<=', $unitBeforeDiscount)
                //             ->where('unit_to', '>=', $unitBeforeDiscount)
                //             ->first();
                //     } else {
                //         // Only run this if phase_id column exists
                //         $rateRow = RateAndCapacity::where('tariff_setup_id', $tariff->id)
                //             ->where('phase_id', $meterIssue->phase_id)
                //             ->where('capacity_id', $meterIssue->capacity_id)
                //             ->where('purpose_id', $meterIssue->purpose_id)
                //             ->where('unit_from', '<=', $unitBeforeDiscount)
                //             ->where('unit_to', '>=', $unitBeforeDiscount)
                //             ->first();
                //     }
                  

                // $unitRate = $rateRow ? $rateRow->rate_per_unit : 0;

                // $discountAmount = $discountUnit * $unitRate;

                $meterReadingEntry = MeterReadingEntry::create([
                    'entry_type' => 1,
                    'reader_id' => Auth::id(),
                    'reading_month_in_bs' => $validated['reading_month_in_bs'],
                    'reading_date_in_bs' => $validated['reading_date_in_bs'],
                    'reading_date_in_ad' => $validated['reading_date_in_ad'],
                    'meter_issue_id' => $validated['meter_issue_id'],
                    'tariff_setup_id' => $tariff->id,
                    'previous_unit' => $previousUnit,
                    'current_unit' => $currentUnit,
                    'discount_unit_for_disable' => $discountUnit,
                    'total_unit' => $unitBeforeDiscount,
                    'unit_amount' => $validated['unit_amount'],
                    'fine_amount' => $validated['fine_amount'] ?? 0,
                    'total_charge' => $validated['total_charge'],
                    'sub_total_charge' => $validated['total_charge'] - $validated['fine_amount'],
                    'fiscal_year_id' => $fiscalYearID,
                    'status' => 0
                ]);
                $latestUnusedChangeMeter = \App\Models\ChangeMeter::where('meter_issue_id', $meterIssue->id)
                    ->whereNull('deleted_at')
                    ->whereNull('used_by')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($latestUnusedChangeMeter) {
                    $latestUnusedChangeMeter->used_by = $meterReadingEntry->id;
                    $latestUnusedChangeMeter->save();
                }

                $charges = [
                    'unit_amount' => $validated['unit_amount'],
                    'discount_amount' => 0,
                    'minimum_demand' => $validated['minimum_demand'],
                    'service_charge' => $validated['service_charge'],
                    'subsidy_charge' => $validated['subsidy_charge'],
                    'other_charge' => $validated['other_charge'],
                    'fine_amount' => $validated['fine_amount'] ?? 0,
                ];

                $meterIssueId = $validated['meter_issue_id'];

                // Check if advance exists before creating meter reading transactions
                $cr = CustomerTransaction::where('member_entry_id', $meterIssue->member_entry_id)
                    ->where('charge_type', 9)
                    ->where('direction', 'CR')
                    ->sum('amount');
                $dr = CustomerTransaction::where('member_entry_id', $meterIssue->member_entry_id)
                    ->where('charge_type', 9)
                    ->where('direction', 'DR')
                    ->sum('amount');

                $hasAdvance = ($cr - $dr > 0);

                if (!$hasAdvance) {
                    // Only create full meter reading transactions if no advance
                    app(\App\Services\CustomerTransactionService::class)
                        ->createForMeterReading($meterIssue->member_entry_id, $meterReadingEntry->id, $charges, $validated['reading_date_in_ad']);
                }else{
                $this->createMahasulReceiptIfAdvanceExists($validated, $meterIssue, $meterReadingEntry->id, $hasAdvance);
                }
                if ($meterReadingEntry->reading_date_in_ad < now()->toDateString()) {
                    try {
                        $todayAD = now()->toDateString();
                        $todayBS = NepaliCalendar::adToBs($todayAD);

                        // ONLY process this specific entry, not all entries
                        $this->fineService->processSingleMeterEntry(
                            $meterReadingEntry,
                            $todayAD,
                            $todayBS
                        );

                        //Log::info("Immediate fine applied for entry {$meterReadingEntry->id}");

                    } catch (\Exception $e) {
                        Log::error("Failed to apply immediate fine for entry {$meterReadingEntry->id}: " . $e->getMessage());
                    }
                }

                return response()->json([
                    'message' => 'Meter reading entry created successfully!',
                    'data' => $meterReadingEntry->toArray(),
                    'rollover_count' => $rolloverCount,
                ], 201);
            }, 5);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the meter reading entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    private function createMahasulReceiptIfAdvanceExists($validated, $meterIssue, $meterReadingEntryId, $hasAdvance)
    {
        // Calculate total available advance
        $cr = CustomerTransaction::where('member_entry_id', $meterIssue->member_entry_id)
            ->where('charge_type', 9)
            ->where('direction', 'CR')
            ->sum('amount');

        $dr = CustomerTransaction::where('member_entry_id', $meterIssue->member_entry_id)
            ->where('charge_type', 9)
            ->where('direction', 'DR')
            ->sum('amount');

        $availableAdvance = bcsub($cr, $dr, 2);
        if ($availableAdvance <= 0) {
            return null; // No advance to use
        }

        $latestReading = MeterReadingEntry::withoutTrashed()
            ->where('meter_issue_id', $validated['meter_issue_id'])
            ->orderBy('reading_date_in_ad', 'desc')
            ->first();

        $latestReadingId = $latestReading?->id;
        $fiscalYearId = Helper::getActiveFiscalYearId();

        // Generate voucher number
        $latestReceipt = MahasulReceiptEntry::withTrashed()
        ->orderBy('id', 'desc')->first();
        if ($latestReceipt && str_contains($latestReceipt->voucher_no, '-')) {
            [$prefix, $number] = explode('-', $latestReceipt->voucher_no, 2);
            $newNumber = str_pad(((int) $number + 1), strlen($number), '0', STR_PAD_LEFT);
            $voucherNo = $prefix . '-' . $newNumber;
        } else {
            $voucherNo = 'M8283-000001';
        }

        // Calculate total bill
        $totalBillAmount = $validated['unit_amount'] +
            $validated['minimum_demand'] +
            $validated['service_charge'] +
            $validated['subsidy_charge'] +
            $validated['other_charge'] +
            ($validated['fine_amount'] ?? 0);

        $amountToUse = $availableAdvance;
        $totalDueAmount = max(0, $totalBillAmount - $amountToUse);
        $advanceStatus = 0;

        if ($amountToUse > 0) {
            if ($totalDueAmount == 0) {
                $advanceStatus = 1; // Fully paid by advance
            } else {
                $advanceStatus = 2; // Partially paid by advance
            }
        }
        // Create Mahasul receipt entry
        $receipt = MahasulReceiptEntry::create([
            'date_in_bs' => $validated['reading_date_in_bs'],
            'date_in_ad' => $validated['reading_date_in_ad'],
            'meter_issue_id' => $validated['meter_issue_id'],
            'voucher_no' => $voucherNo,
            'meter_reading_entry_id' => $latestReadingId,
            'unit_amount' => $validated['unit_amount'] ?? 0,
            'demand_charge' => $validated['minimum_demand'] ?? 0,
            'subsidy_charge' => $validated['subsidy_charge'] ?? 0,
            'service_charge' => $validated['service_charge'] ?? 0,
            'other_charge' => $validated['other_charge'] ?? 0,
            'rebate_amount' => 0,
            'fine_amount' => $validated['fine_amount'] ?? 0,
            'discount_amount' => 0,
            'total_amount' => $totalBillAmount,
            'total_due_amount' => $totalDueAmount,
            'advance_status' => $advanceStatus,
            'paid_amount' => $amountToUse,
            'fiscal_year_id' => $fiscalYearId,
            'cash_amount' => 0,
            'bank_amount' => 0,
        ]);

        // Create meter reading transactions
        $charges = [
            'fine_amount' => $validated['fine_amount'] ?? 0,
            'minimum_demand' => $validated['minimum_demand'],
            'service_charge' => $validated['service_charge'],
            'subsidy_charge' => $validated['subsidy_charge'],
            'other_charge' => $validated['other_charge'],
            'unit_amount' => $validated['unit_amount'],
            'discount_amount' => 0,
        ];

        app(\App\Services\CustomerTransactionService::class)
            ->createForMeterReading($meterIssue->member_entry_id, $meterReadingEntryId, $charges, $validated['reading_date_in_ad']);

        // Allocate using PaymentAllocationService
        app(PaymentAllocationService::class)->allocate(
            $meterIssue->member_entry_id,
            $receipt->id,
            $amountToUse, // paid amount (advance)
            $validated['reading_date_in_ad'],
            0, // rebate removed
            0, // discount removed
            0, // disable discount removed
            $latestReading
        );

        // Prepare voucher lines
        $lines = [];
        $memberEntryId = $meterIssue->member_entry_id;

        usleep(100000); // optional delay

        // 1. Advance used transaction (DR)
        $advanceUsed = CustomerTransaction::where('receipt_id', $receipt->id)
            ->where('charge_type', 9)
            ->where('direction', 'DR')
            ->first();

        if ($advanceUsed && $advanceUsed->amount > 0) {
            $lines[] = [
                'account_head_id' => 9,
                'debit' => $advanceUsed->amount,
                'credit' => 0,
                'particulars' => "Advance Used for Bill - Receipt {$receipt->voucher_no}",
            ];
        }

        // 2. Map charge types to account heads and add credit lines
        $chargeTypeAccountMap = [1 => 14, 2 => 17, 3 => 13, 4 => 16, 5 => 15, 6 => 11];

        $creditTransactions = CustomerTransaction::where('receipt_id', $receipt->id)
            ->whereIn('charge_type', [1, 2, 3, 4, 5, 6])
            ->where('direction', 'CR')
            ->get();

        foreach ($creditTransactions as $tx) {
            $chargeLabel = match ($tx->charge_type) {
                1 => 'Demand Charge',
                2 => 'Service Charge',
                3 => 'Fine Charge',
                4 => 'Subsidy Charge',
                5 => 'Other Charge',
                6 => 'Energy Charge',
                default => 'Unknown Charge',
            };

            $lines[] = [
                'account_head_id' => $chargeTypeAccountMap[$tx->charge_type] ?? null,
                'debit' => 0,
                'credit' => $tx->amount,
                'particulars' => "{$chargeLabel} - Receipt {$receipt->voucher_no}",
            ];
        }

        // 3. Create voucher
        try {
                VoucherBalanceService::validate($lines);

            $memberId = MeterIssue::getMemberByMeterIssueId($validated['meter_issue_id'])->id ?? null;

            $this->voucherService->create([
                'date' => now()->toDateString(),
                'fiscal_year_id' => $fiscalYearId,
                'voucher_no' => $receipt->voucher_no,
                'particulars' => "Mahasul Receipt {$receipt->voucher_no} - Bill Payment via Advance",
                'status' => 2, // Approved
                'reference_type' => 11,
                'reference_id' => $receipt->id,
                'member_entry_id' => $memberId,
                'lines' => $lines,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create voucher', [
                'error' => $e->getMessage(),
                'receipt_id' => $receipt->id,
                'lines' => $lines
            ]);
            return response()->json([
                'message' => 'Failed to create voucher: ' . $e->getMessage(),
            ], 422);
        }

        return $receipt;
    }


    private static function validateCustomerCharge($fail, $request, $field, $value)
    {
        $meterIssue = MeterIssue::find($request->input('meter_issue_id'));
        if (!$meterIssue)
            return $fail('No active meter issue found.');

        $tariff = TariffSetup::where('is_active', 1)->first();
        if (!$tariff) {
            $fail('No active tariff setup found.');
            return;
        }
            $meterStartService = new \App\Services\KnowMeterStartService();
                $previousUnit = $meterStartService->getMeterStartNo($meterIssue->id) ?? 0;
                $currentUnit = $request->input('current_unit') ?? 0;

                $MAX = 100000; // meter max reading
                if ($currentUnit < $previousUnit) {
                    $consumedUnit = ($MAX - $previousUnit) + $currentUnit; // rollover
                } else {
                    $consumedUnit = $currentUnit - $previousUnit;
                }


      if (\App\Helpers\TenantRuntimeHelper::isRuntimeKhanepani()) {
        $rateRecord = RateAndCapacity::where('tariff_setup_id', $tariff->id)
            ->where('unit_from', '<=', $consumedUnit)
            ->where('unit_to', '>=', $consumedUnit)
            ->first();
            } else {
                $rateRecord = RateAndCapacity::where([
                    'tariff_setup_id' => $tariff->id,
                    'phase_id'        => $meterIssue->phase_id,
                    'capacity_id'     => $meterIssue->capacity_id,
                    'purpose_id'      => $meterIssue->purpose_id,
                ])->where('unit_from', '<=', $consumedUnit)
                ->where('unit_to', '>=', $consumedUnit)
                ->first();
            }

            if (!$rateRecord) {
                $fail("No rate and capacity record found for the consumed units ({$consumedUnit}).");
                return;
            }

            $expectedValue = (float) $rateRecord->{$field};
            if (abs((float) $value - $expectedValue) > 0.1) {
                $fail("The {$field} value ({$value}) must match the allowed value ({$expectedValue}) for {$consumedUnit} consumed units.");
            }
    }



   


  public function listMeterReadingEntries(Request $request)
    {


        try {
            $search = request('search');
            $entries = MeterReadingEntry::withoutTrashed()
                ->with([
                    'member:member_entries.id,member_no,customer_name_en,customer_name_np',
                    'meterIssue:id,meter_no',
                    'reader:id,name,email'
                ])
                ->where('entry_type', 1)
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->whereHas('member', function ($member) use ($search) {
                            $member->where('member_no', 'like', "%{$search}%")
                                ->orWhere('customer_name_en', 'like', "%{$search}%");
                        })
                            ->orWhereHas('meterIssue', function ($meter) use ($search) {
                                $meter->where('meter_no', 'like', "%{$search}%");
                            });
                    });
                })
                ->orderBy('id', 'desc') 
                ->paginate(10);

            $entries->getCollection()->transform(function ($entry) use ($entries) {
                $entry->customer_name_en = $entry->member->customer_name_en ?? $entry->customer_name_en;
                $entry->customer_name_np = $entry->member->customer_name_np ?? $entry->customer_name_np;
                $entry->member_no = $entry->member->member_no;
                $entry->meter_no = $entry->meterIssue->meter_no ?? $entry->meter_no;

                $entry->reader_id = $entry->reader_id;
                $entry->status = $entry->status;

                $entry->status_text = match ($entry->status) {
                    1 => 'Pending',
                    2 => 'Billed',
                    3 => 'Reserved',
                    default => 'null'
                };
                $entry->reader_name = $entry->reader->name ?? null;
                $entry->reader_email = $entry->reader->email ?? null;

                unset($entry->reader, $entry->member, $entry->meterIssue);
                return $entry;
            });

            return response()->json($entries);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving meter reading entries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function loadAllCustomerDetails()
    {
        try {
          
          $selectColumns = [
                    'id as meter_issue_id',
                    'member_entry_id',
                    'meter_no',
                    'meter_start_no',
                ];
                

                if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
                    $selectColumns = array_merge($selectColumns, [
                        'phase_id',
                        'capacity_id',
                        'purpose_id',
                    ]);
                }

                // $customers = MeterIssue::on('tenant')
                //     ->where('is_active', 1)
                //     ->select($selectColumns)
                //     ->get();
                      $customers = MeterIssue::on('tenant')
                    ->with(['readingArea:id,name_en,name_np'])
                    ->where('is_active', 1)
                    ->select(array_merge($selectColumns, ['area_id']))
                    ->get();

            if ($customers->isEmpty()) {
                return response()->json([
                    'message' => 'No active customer records found.',
                    'data' => [],
                ], 200);
            }

            $activeTariff = TariffSetup::where('is_active', 1)->firstOrFail();
            $discountRecords = DiscountAndFine::withoutTrashed()
                ->where('is_active', 1)->get();
            $rateAndCapacityRecords = RateAndCapacity::withoutTrashed()
                ->where('tariff_setup_id', $activeTariff->id)
                ->orderBy('id', 'asc')
                ->get();
           $phases = $phases_np = $capacities = $capacities_np = $purposes = $purposes_np = [];
        if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
            $phases = MasterSetup::where('master_setup_type_id', 3)->pluck('name_en', 'id')->toArray();
            $phases_np = MasterSetup::where('master_setup_type_id', 3)->pluck('name_np', 'id')->toArray();
            $capacities = MasterSetup::where('master_setup_type_id', 4)->pluck('name_en', 'id')->toArray();
            $capacities_np = MasterSetup::where('master_setup_type_id', 4)->pluck('name_np', 'id')->toArray();
            $purposes = MasterSetup::where('master_setup_type_id', 1)->pluck('name_en', 'id')->toArray();
            $purposes_np = MasterSetup::where('master_setup_type_id', 1)->pluck('name_np', 'id')->toArray();
        }

            $currentBsDate = NepaliCalendar::adToBs(now()->format('Y-m-d'));
            $currentBsMonth = (int) explode('-', $currentBsDate)[1];
            $customerData = $customers->map(function ($customer) use (
            $activeTariff,
            $phases, $phases_np,
            $capacities, $capacities_np,
            $purposes, $purposes_np,
            $currentBsMonth
        ) {
                $memberEntry = MemberEntry::on('tenant')
                    ->where('id', $customer->member_entry_id)
                    ->where('is_active', 1)
                    ->first();

                $customerNameEn = $memberEntry->customer_name_en ?? null;
                $customerNameNp = $memberEntry->customer_name_np ?? null;
                $wardNo = $memberEntry->ward_no ?? null;
                $isDisable = $memberEntry->is_disable ?? null;
                $memberNo = $memberEntry->member_no ?? null;


                $discountUnitForDisable = 0;
                if ($isDisable == 1) {
                    $disabilityRecord = DisabilityDiscount::first();
                    if ($disabilityRecord && $disabilityRecord->is_applied) {
                        $discountUnitForDisable = $disabilityRecord->units ?? 0;
                    }
                }

                $totalDue = $this->dueService->getPreviousTotalDue(
                    $customer->member_entry_id,
                    $customer->meter_issue_id
                );
                $previousAdvance = $this->dueService->getAvailableAdvance(
                    $customer->member_entry_id
                );

                $latestReceipt = MahasulReceiptEntry::on('tenant')
                    ->withoutTrashed()
                    ->where('is_cancel', 0)
                    ->where('meter_issue_id', $customer->id)
                    ->latest('id')
                    ->first(['id', 'meter_reading_entry_id']);

                $meterReadingsQuery = MeterReadingEntry::on('tenant')
                    ->withoutTrashed()
                    ->where('meter_issue_id', $customer->meter_issue_id)
                    ->when($activeTariff?->id, fn($q) => $q->where('tariff_setup_id', $activeTariff->id));

                if ($latestReceipt && $latestReceipt->meter_reading_entry_id) {
                    $meterReadingsQuery->where('id', '>', $latestReceipt->meter_reading_entry_id);
                }

                $latestReading = $meterReadingsQuery->orderBy('reading_date_in_ad', 'desc')->first();
               $readingStatus = false;
                $pendingStatus = false;

                if ($latestReading) {

                    $lastReadingDate = $latestReading->reading_date_in_bs; // YYYY-MM-DD
                    $lastReadingYear = (int) explode('-', $lastReadingDate)[0];
                    $lastReadingMonth = (int) explode('-', $lastReadingDate)[1];

                    $currentBsDate = NepaliCalendar::adToBs(now()->format('Y-m-d'));
                    [$currentYear, $currentMonth] = array_map('intval', explode('-', $currentBsDate));

                    $monthDiff = ($currentYear - $lastReadingYear) * 12 + ($currentMonth - $lastReadingMonth);
                    if ($monthDiff == 0) {
                        $readingStatus = true;
                    }
                    if ($monthDiff > 1) {
                        $pendingStatus = true;
                    }
                }
                $meterStartNo = $this->knowMeterStartService
                    ->getMeterStartNo($customer->meter_issue_id);
                
                 
               $data = [
                'member_entry_id' => $customer->member_entry_id,
                'member_no' => $memberNo,
                'customer_name_en' => $customerNameEn,
                'customer_name_np' => $customerNameNp,
                'meter_issue_id' => $customer->meter_issue_id,
                'reading_area' => $customer->readingArea->name_np ?? null,
                'ward_no' => $wardNo,
                'previous_total_due' => $totalDue,
                'previous_advance' => $previousAdvance,
                'is_disable' => $isDisable,
                'discount_unit_for_disable' => $discountUnitForDisable,
                'meter_no' => $customer->meter_no,
                'meter_start_no' => $meterStartNo,
                'last_reading_month' => $latestReading->reading_month_in_bs ?? null,
                'last_reading_date' => $latestReading->reading_date_in_bs ?? null,
                'reading_status' => $readingStatus,
                'pending_status' => $pendingStatus,
            ];

            // Add phase/capacity/purpose only for Bidut
            if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
                $data = array_merge($data, [
                    'phase_id' => $customer->phase_id,
                    'phase_name_en' => $phases[$customer->phase_id] ?? null,
                    'phase_name_np' => $phases_np[$customer->phase_id] ?? null,
                    'capacity_id' => $customer->capacity_id,
                    'capacity_name_en' => $capacities[$customer->capacity_id] ?? null,
                    'capacity_name_np' => $capacities_np[$customer->capacity_id] ?? null,
                    'purpose_id' => $customer->purpose_id,
                    'purpose_name_en' => $purposes[$customer->purpose_id] ?? null,
                    'purpose_name_np' => $purposes_np[$customer->purpose_id] ?? null,
                ]);
            }

            return $data;
        });


            return response()->json([
                'message' => 'All customer details retrieved successfully',
                'tariff_setup' => [
                    'id' => $activeTariff->id,
                    'rule_name' => $activeTariff->rule_name,
                    'is_active' => $activeTariff->is_active,
                    'created_at' => $activeTariff->created_at,
                    'updated_at' => $activeTariff->updated_at,
                    'rate_and_capacity' => $rateAndCapacityRecords,
                ],
                'discount_and_fine' => $discountRecords,
                'data' => $customerData,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'No active tariff setup found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving customer details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }







    public function loadCustomersByArea($area_id)
    {
        try {

           
              $selectColumns = [
            'meter_issues.id as meter_issue_id',
            'meter_issues.member_entry_id',
            'member_entries.customer_name_en',
            'member_entries.customer_name_np',
            'member_entries.member_no',
            'member_entries.is_disable',
            'meter_issues.meter_no',
            'meter_issues.meter_start_no',
            'member_entries.ward_no',
            'area.name_np as reading_area',
        ];

        // Only select phase/capacity/purpose for Bidut
        if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
            $selectColumns = array_merge($selectColumns, [
                'meter_issues.phase_id',
                'meter_issues.capacity_id',
                'meter_issues.purpose_id',
            ]);
        }

        // $customers = MeterIssue::on('tenant')
        //     ->join('member_entries', 'member_entries.id', '=', 'meter_issues.member_entry_id')
        //     ->where('meter_issues.is_active', 1)
        //     ->where('member_entries.area_id', $area_id)
        //     ->select($selectColumns)
        //     ->get();
        $customers = MeterIssue::on('tenant')
    ->join('member_entries', 'member_entries.id', '=', 'meter_issues.member_entry_id')
    ->leftJoin('master_setups as area', 'meter_issues.area_id', '=', 'area.id') 
    ->where('meter_issues.is_active', 1)
    ->where('member_entries.area_id', $area_id)
    ->select($selectColumns)
    ->get();

            if ($customers->isEmpty()) {
                return response()->json([
                    'message' => 'No active customer records found for this area.',
                    'data' => [],
                ], 200);
            }

            $activeTariff = TariffSetup::where('is_active', 1)->firstOrFail();
            $discountRecords = DiscountAndFine::withoutTrashed()
                ->where('is_active', 1)->get();

            $rateAndCapacityRecords = RateAndCapacity::withoutTrashed()
                ->where('tariff_setup_id', $activeTariff->id)
                ->orderBy('id', 'asc')
                ->get();

             $phases = $phases_np = $capacities = $capacities_np = $purposes = $purposes_np = [];
        if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
            $phases = MasterSetup::where('master_setup_type_id', 3)->pluck('name_en', 'id')->toArray();
            $phases_np = MasterSetup::where('master_setup_type_id', 3)->pluck('name_np', 'id')->toArray();
            $capacities = MasterSetup::where('master_setup_type_id', 4)->pluck('name_en', 'id')->toArray();
            $capacities_np = MasterSetup::where('master_setup_type_id', 4)->pluck('name_np', 'id')->toArray();
            $purposes = MasterSetup::where('master_setup_type_id', 1)->pluck('name_en', 'id')->toArray();
            $purposes_np = MasterSetup::where('master_setup_type_id', 1)->pluck('name_np', 'id')->toArray();
        }
            $currentBsDate = NepaliCalendar::adToBs(now()->format('Y-m-d'));
            $currentBsMonth = (int) explode('-', $currentBsDate)[1];
            $customerData = $customers->map(function ($customer) use ($activeTariff, $phases, $phases_np, $capacities, $capacities_np, $purposes, $purposes_np, $currentBsMonth) {
                $latestReceipt = MahasulReceiptEntry::on('tenant')
                    ->withoutTrashed()
                    ->where('is_cancel', 0)
                    ->where('meter_issue_id', $customer->id)
                    ->latest('id')
                    ->first(['id', 'meter_reading_entry_id']);

                $meterReadingsQuery = MeterReadingEntry::on('tenant')
                    ->withoutTrashed()
                    ->where('meter_issue_id', $customer->meter_issue_id)
                    ->when($activeTariff?->id, fn($q) => $q->where('tariff_setup_id', $activeTariff->id));

                if ($latestReceipt && $latestReceipt->meter_reading_entry_id) {
                    $meterReadingsQuery->where('id', '>', $latestReceipt->meter_reading_entry_id);
                }

                $latestReading = $meterReadingsQuery->orderBy('reading_date_in_ad', 'desc')->first();
               $readingStatus = false;
                $pendingStatus = false;

                if ($latestReading) {

                    $lastReadingDate = $latestReading->reading_date_in_bs; // YYYY-MM-DD
                    $lastReadingYear = (int) explode('-', $lastReadingDate)[0];
                    $lastReadingMonth = (int) explode('-', $lastReadingDate)[1];

                    $currentBsDate = NepaliCalendar::adToBs(now()->format('Y-m-d'));
                    [$currentYear, $currentMonth] = array_map('intval', explode('-', $currentBsDate));

                    $monthDiff = ($currentYear - $lastReadingYear) * 12 + ($currentMonth - $lastReadingMonth);
                    if ($monthDiff == 0) {
                        $readingStatus = true;
                    }
                    if ($monthDiff > 1) {
                        $pendingStatus = true;
                    }
                }

                $totalDue = $this->dueService->getPreviousTotalDue(
                    $customer->member_entry_id,
                    $customer->meter_issue_id
                );
                $previousAdvance = $this->dueService->getAvailableAdvance(
                    $customer->member_entry_id
                );
                $discountUnitForDisable = 0;
                if ($customer->is_disable) {
                    $disabilityRecord = DisabilityDiscount::first();
                    if ($disabilityRecord && $disabilityRecord->is_applied) {
                        $discountUnitForDisable = $disabilityRecord->units ?? 0;
                    }
                }
                $meterStartNo = $this->knowMeterStartService
                    ->getMeterStartNo($customer->meter_issue_id);
                $data = [
                'member_entry_id' => $customer->member_entry_id,
                'member_no' => $customer->member_no,
                'customer_name_en' => $customer->customer_name_en,
                'customer_name_np' => $customer->customer_name_np,
                'meter_issue_id' => $customer->meter_issue_id,
                'reading_area' => $customer->reading_area,
'ward_no' => $customer->ward_no,
                'previous_total_due' => $totalDue,
                'previous_advance' => $previousAdvance,
                'is_disable' => $customer->is_disable,
                'discount_unit_for_disable' => $discountUnitForDisable,
                'meter_no' => $customer->meter_no,
                'meter_start_no' => $meterStartNo,
                'last_reading_month' => $latestReading->reading_month_in_bs ?? null,
                'last_reading_date' => $latestReading->reading_date_in_bs ?? null,
                'reading_status' => $readingStatus,
                'pending_status' => $pendingStatus,
            ];

            if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
                $data = array_merge($data, [
                    'phase_id' => $customer->phase_id,
                    'phase_name_en' => $phases[$customer->phase_id] ?? null,
                    'phase_name_np' => $phases_np[$customer->phase_id] ?? null,
                    'capacity_id' => $customer->capacity_id,
                    'capacity_name_en' => $capacities[$customer->capacity_id] ?? null,
                    'capacity_name_np' => $capacities_np[$customer->capacity_id] ?? null,
                    'purpose_id' => $customer->purpose_id,
                    'purpose_name_en' => $purposes[$customer->purpose_id] ?? null,
                    'purpose_name_np' => $purposes_np[$customer->purpose_id] ?? null,
                ]);
            }

            return $data;
        });

            return response()->json([
                'message' => 'Customer details retrieved successfully for this area',
                'tariff_setup' => [
                    'id' => $activeTariff->id,
                    'rule_name' => $activeTariff->rule_name,
                    'is_active' => $activeTariff->is_active,
                    'created_at' => $activeTariff->created_at,
                    'updated_at' => $activeTariff->updated_at,
                    'rate_and_capacity' => $rateAndCapacityRecords,
                ],
                'discount_and_fine' => $discountRecords,
                'data' => $customerData,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'No active tariff setup found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving customer details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function searchCustomerDetails(Request $request)
    {
        try {
            $searchTerm = trim($request->input('search'));
            $readingMonth = $request->input('reading_month');

            // Validate reading month if provided
            if ($readingMonth) {
                $validator = Validator::make(
                    ['reading_month' => $readingMonth],
                    ['reading_month' => ['required', 'integer']]
                );

                if ($validator->fails()) {
                    return response()->json([
                        'message' => 'Invalid reading month provided.',
                        'errors' => $validator->errors(),
                    ], 422);
                }
            }


            $members = MemberEntry::on('tenant')
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->where(function ($query) use ($searchTerm) {
                    $normalized = preg_replace('/\s+/', ' ', $searchTerm);
                    $query->where('member_no', 'like', "%{$normalized}%")
                        ->orWhere('customer_name_en', 'like', "%{$normalized}%");
                })
                ->get();



            if ($members->isEmpty()) {
                return response()->json([
                    'message' => 'No active customer found for the provided search term.',
                    'data' => [],
                ], 200);
            }

            $activeTariff = TariffSetup::where('is_active', 1)->firstOrFail();

            $customerData = collect();

            foreach ($members as $member) {
                $meters = MeterIssue::on('tenant')
                    ->where('is_active', 1)
                    ->where('member_entry_id', $member->id)
                    ->get();

                foreach ($meters as $meter) {
                    if (
                        $readingMonth && MeterReadingEntry::on('tenant')
                            ->withoutTrashed()
                            ->where('meter_issue_id', $meter->id)
                            ->where('reading_month_in_bs', $readingMonth) // keep DB column as is
                            ->exists()
                    ) {
                        continue;
                    }

                    $rateAndCapacity = RateAndCapacity::on('tenant')
                        ->where([
                            'tariff_setup_id' => $activeTariff->id,
                            ...(
                                \App\Helpers\TenantRuntimeHelper::isRuntimeKhanepani()
                                    ? []
                                    : [
                                        'phase_id' => $meter->phase_id,
                                        'capacity_id' => $meter->capacity_id,
                                        'purpose_id' => $meter->purpose_id,
                                    ]
                            ),
                        ])
                        ->orderByRaw('CAST(unit_from AS DECIMAL(10,2)) ASC')
                        ->get([
                            'id',
                            'unit_from',
                            'unit_to',
                            'rate_per_unit',
                            'minimum_demand',
                            'subsidy_charge',
                            'service_charge',
                            'other_charge'
                        ]);

                    // Calculate disability discount if applicable
                    $discountUnit = 0;
                    if ($member->is_disable == 1) {
                        $disability = DisabilityDiscount::first();
                        if ($disability && $disability->is_applied) {
                            $discountUnit = $disability->units ?? 0;
                        }
                    }

                    $latestReadingMonth = MeterReadingEntry::on('tenant')
                        ->withoutTrashed()
                        ->where('meter_issue_id', $meter->id)
                        ->orderByDesc('id')
                        ->first();
                    $meterStartService = new KnowMeterStartService();
                    $meterStartNo = $meterStartService->getMeterStartNo($meter->id);
                    $customerData->push([
                        'id' => $member->id,
                        'member_no' => $member->member_no,
                        'customer_name_en' => $member->customer_name_en,
                        'customer_name_np' => $member->customer_name_np,
                        'is_disable' => $member->is_disable,
                        'discount_unit_for_disable' => $discountUnit,

                        'meter_issue_id' => $meter->id,
                        'meter_no' => $meter->meter_no,
                        'meter_start_no' => $meterStartNo,
                        'last_reading_month' => $latestReadingMonth->reading_month_in_bs ?? 0,
                        ...(
                                \App\Helpers\TenantRuntimeHelper::isRuntimeKhanepani()
                                    ? []
                                    : [
                                        'phase_id' => $meter->phase_id,
                                        'capacity_id' => $meter->capacity_id,
                                        'purpose_id' => $meter->purpose_id,
                                    ]
                            ),
                        'rate_and_capacity' => $rateAndCapacity
                    ]);
                }
            }

            if ($customerData->isEmpty()) {
                return response()->json([
                    'message' => 'No meter details available for the given criteria.',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'message' => 'Member details retrieved successfully',
                'tariff_setup' => $activeTariff,
                'data' => $customerData,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'No active tariff setup found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while searching customer details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getMember($memberId, Request $request)
    {

        try {
            $readingMonth = $request->input('reading_month');

            $member = MemberEntry::on('tenant')->where('id', $memberId)->first();
            $activeTariff = TariffSetup::where('is_active', 1)->firstOrFail();
            $customerData = collect();

            $meters = MeterIssue::on('tenant')
                ->where('member_entry_id', $member->id)
                ->where('is_active', 1)
                ->get();

            foreach ($meters as $meter) {
                if (
                    $readingMonth && MeterReadingEntry::on('tenant')
                        ->where('meter_issue_id', $meter->id)
                        ->where('reading_month_in_bs', $readingMonth)
                        ->exists()
                )
                    continue;

                $rateAndCapacity = RateAndCapacity::on('tenant')
                    ->where([
                        'tariff_setup_id' => $activeTariff->id,
                        // 'phase_id' => $meter->phase_id,
                        // 'capacity_id' => $meter->capacity_id,
                        // 'purpose_id' => $meter->purpose_id,
                        ...(
                            \App\Helpers\TenantRuntimeHelper::isRuntimeKhanepani()
                                ? []
                                : [
                                    'phase_id' => $meter->phase_id,
                                    'capacity_id' => $meter->capacity_id,
                                    'purpose_id' => $meter->purpose_id,
                                ]
                        ),
                    ])
                    ->orderByRaw('CAST(unit_from AS DECIMAL(10,2)) ASC')
                    ->get();

                if ($rateAndCapacity->isEmpty())
                    continue;

                $discountUnit = 0;
                if ($member->is_disable == 1) {
                    $disability = DisabilityDiscount::first();
                    if ($disability && $disability->is_applied) {
                        $discountUnit = $disability->units ?? 0;
                    }
                }

                $latestReadingMonth = MeterReadingEntry::on('tenant')
                    ->where('meter_issue_id', $meter->id)
                    ->orderByDesc('id')
                    ->first();
                $meterStartService = new KnowMeterStartService();
                $meterStartNo = $meterStartService->getMeterStartNo($meter->id);

                $customerData->push([
                    'member_no' => $member->member_no,
                    'customer_name_en' => $member->customer_name_en,
                    'customer_name_np' => $member->customer_name_np,
                    'meter_issue_id' => $meter->id,
                    'meter_no' => $meter->meter_no,
                    'meter_start_no' => $meterStartNo,
                    'tariff_setup' => [
                            'id' => $activeTariff->id,
                            'rule_name' => $activeTariff->rule_name,
                        ],
                    'last_reading_month' => $latestReadingMonth->reading_month_in_bs ?? 0,
                   ...(
                            \App\Helpers\TenantRuntimeHelper::isRuntimeKhanepani()
                                ? []
                                : [
                                    'phase_id' => $meter->phase_id,
                                    'capacity_id' => $meter->capacity_id,
                                    'purpose_id' => $meter->purpose_id,
                                ]
                        ),
                    'discount_unit_for_disable' => $discountUnit,
                    'rate_and_capacity' => $rateAndCapacity,

                ]);
            }

            if ($customerData->isEmpty()) {
                return response()->json([
                    'message' => 'No rate & capacity setup found for this member !!',
                    'data' => [],
                ], 422);
            }

            return response()->json([
                'message' => 'Member meter details retrieved successfully',
                'data' => $customerData,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'No active tariff setup found'], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database query error occurred while retrieving member details !',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving member details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function getById(Request $request, $id)
    {


        try {
            $entry = MeterReadingEntry::withoutTrashed()
                ->with([
                    'member:member_entries.id,member_no,customer_name_en,customer_name_np',
                    'meterIssue:id,meter_no',
                    'reader:id,name,email'
                ])
                ->findOrFail($id);

            $entry->customer_name_en = $entry->member->customer_name_en ?? $entry->customer_name_en;
            $entry->customer_name_np = $entry->member->customer_name_np ?? $entry->customer_name_np;
            $entry->meter_no = $entry->meterIssue->meter_no ?? $entry->meter_no;
            $entry->member_no = $entry->member->member_no;
            $entry->reader_name = $entry->reader->name ?? null;
            $entry->reader_email = $entry->reader->email ?? null;

            unset($entry->reader, $entry->member, $entry->meterIssue);

            return response()->json(['meter_reading' => $entry], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Meter Reading entry not found or already deleted',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the meter Reading entry',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }





    public function editMeterReadingEntry(Request $request, $id)
    {


        $connection = (new MeterReadingEntry)->getConnectionName() ?: config('database.default');

        try {
            return DB::connection($connection)->transaction(function () use ($request, $id) {
                $meterReadingEntry = MeterReadingEntry::withoutTrashed()->findOrFail($id);
                $meterIssue = MeterIssue::findOrFail($meterReadingEntry->meter_issue_id);



                $previousUnit = MeterReadingEntry::withoutTrashed()
                    ->where('meter_issue_id', $meterIssue->id)
                    ->where('id', '!=', $id)
                    ->orderByRaw('YEAR(reading_date_in_ad) ASC')
                    ->orderBy('reading_date_in_ad', 'DESC')
                    ->value('current_unit') ?? $meterIssue->meter_start_no;

                $validated = $request->validate([
                    //'reader_id' => ['required', 'integer', 'exists:users,id'],
                    'status' => ['nullable', 'integer', 'in:1,2,3'],

                    'reading_month_in_bs' => [
                        'required',
                        'integer',
                        function ($attribute, $value, $fail) use ($request, $id) {
                            $month = (int) $value;
                            $currentBsDate = $this->dateConversionController->getCurrentBsDate();
                            // [$currentBsYear, $currentBsMonth] = explode('-', substr($currentBsDate, 0, 7));
                            // $currentBsMonth = (int) $currentBsMonth;
                            
                            // if ($month > $currentBsMonth) {
                            //     $fail("Cannot edit record for month {$month}. Cannot set future month.");
                            //     return;
                            // }
                            [$currentBsYear, $currentBsMonth] = explode('-', substr($currentBsDate, 0, 7));
                                $currentBsYear = (int) $currentBsYear;
                                $currentBsMonth = (int) $currentBsMonth;
                                        // if (!(($month >= 4 && $month <= 12) || ($month >= 1 && $month <= 3))) {
                                        //     $fail("Reading month must be between Shrawan (4) and Ashad (3) of the fiscal year.");
                                        //     return;
                                        // }
                                // Extract year from reading_date_in_bs
                                $readingDate = $request->input('reading_date_in_bs');
                                [$readingYear, $readingMonthFromDate] = explode('-', substr($readingDate, 0, 7));
                                $readingYear = (int) $readingYear;

                                // Compare YEAR first, then MONTH
                                if (
                                    $readingYear > $currentBsYear ||
                                    ($readingYear == $currentBsYear && $month > $currentBsMonth)
                                ) {
                                    $fail("Cannot create record for future month.");
                                }
                            $isIrrigationCustomer = false;
                            $meterIssue = MeterIssue::where('id', $request->input('meter_issue_id'))
                                ->first();
                            if ($meterIssue) {
                                $purposeSetup = MasterSetup::where('id', $meterIssue->purpose_id)
                                    ->where('master_setup_type_id', 1)
                                    ->where('name_en', 'Irrigation')
                                    ->where('is_active', true)
                                    ->first();
                                $isIrrigationCustomer = $purposeSetup ? true : false;
                            }

                            if ($isIrrigationCustomer)
                                return;
                            $latestEntry = MeterReadingEntry::withoutTrashed()
                                ->where('meter_issue_id', $meterIssue->id)
                                ->where('id', '!=', $id)
                                ->orderBy('reading_month_in_bs', 'desc')
                                ->first();

                            if ($latestEntry) {
                                $lastMonth = (int) $latestEntry->reading_month_in_bs;
                                $expectedMonth = $lastMonth == 12 ? 1 : $lastMonth + 1;
                                if ($month != $expectedMonth) {
                                    $fail("Edited record must be for the immediate next month ({$expectedMonth}).");
                                }
                            }
                        }
                    ],
                    'reading_date_in_bs' => [
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
                        }
                    ],

                    'reading_date_in_ad' => [
                        'required',
                        'date',
                        'max:10',
                        'regex:/^\d{4}-\d{2}-\d{2}$/',
                        function ($attribute, $value, $fail) {
                            if ($value > date('Y-m-d')) {
                                $fail("The {$attribute} cannot be a future date.");
                            }
                        }
                    ],
                    'current_unit' => [
                        'required',
                        'numeric',
                        'min:0',
                        function ($attribute, $value, $fail) use ($previousUnit) {
                            $MAX = 100000;
                            $ROLLOVER_THRESHOLD = 1000;

                            if ($value >= $previousUnit)
                                return;
                            if ($previousUnit >= ($MAX - $ROLLOVER_THRESHOLD) && $value <= $ROLLOVER_THRESHOLD)
                                return;

                            $fail("Current unit ({$value}) cannot be less than previous unit ({$previousUnit}) unless meter rolled over.");
                        },
                    ],
                    'unit_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
                    'minimum_demand' => [
                        'required',
                        'numeric',
                        'min:0',
                        function ($attribute, $value, $fail) use ($request) {
                            self::validateCustomerCharge($fail, $request, 'minimum_demand', $value);
                        }
                    ],
                    'subsidy_charge' => [
                        'required',
                        'numeric',
                        'min:0',
                        function ($attribute, $value, $fail) use ($request) {
                            self::validateCustomerCharge($fail, $request, 'subsidy_charge', $value);
                        }
                    ],
                    'service_charge' => [
                        'required',
                        'numeric',
                        'min:0',
                        function ($attribute, $value, $fail) use ($request) {
                            self::validateCustomerCharge($fail, $request, 'service_charge', $value);
                        }
                    ],
                    'other_charge' => [
                        'required',
                        'numeric',
                        'min:0',
                        function ($attribute, $value, $fail) use ($request) {
                            self::validateCustomerCharge($fail, $request, 'other_charge', $value);
                        }
                    ],
                    'fine_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'applied_fine_percentage' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'sub_total_charge' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'total_charge' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                ]);
                $currentUnit = $validated['current_unit'];

                $member = MemberEntry::on('tenant')->find($meterIssue->member_entry_id);
                $discountUnitForDisable = ($member && $member->is_disable)
                    ? optional(DisabilityDiscount::first())->units ?? 0
                    : 0;

                $unitAmount = $validated['unit_amount'];

                $subsidy = $validated['subsidy_charge'];
                $service = $validated['service_charge'];
                $other = $validated['other_charge'];
                $totalCharge = $validated['total_charge'];
                $fineAmount = $validated['fine_amount'] ?? 0;

                $expectedSubtotal = $unitAmount + $subsidy + $service + $other;
                $expectedTotal = $expectedSubtotal + $fineAmount;




                $MAX = 100000;
                $member = MemberEntry::on('tenant')->find($meterIssue->member_entry_id);
                $discountUnitForDisable = ($member && $member->is_disable) ? optional(DisabilityDiscount::first())->units ?? 0 : 0;

                if ($currentUnit < $previousUnit) {
                    $rolloverCount = 1;
                    $unitBeforeDiscount = ($MAX - $previousUnit) + $currentUnit;
                } else {
                    $rolloverCount = 0;
                    $unitBeforeDiscount = $currentUnit - $previousUnit;
                }

                $totalUnit = max(0, $unitBeforeDiscount - $discountUnitForDisable);

                $tariffSetup = TariffSetup::where('is_active', 1)->firstOrFail();

                $meterReadingEntry->update([
                    // 'reader_id' => $validated['reader_id'],
                    'status' => $validated['status'] ?? $meterReadingEntry->status,
                    'reading_month_in_bs' => $validated['reading_month_in_bs'],
                    'reading_date_in_bs' => $validated['reading_date_in_bs'],

                    'reading_date_in_ad' => $validated['reading_date_in_ad'],
                    'meter_issue_id' => $meterIssue->id,
                    'tariff_setup_id' => $tariffSetup->id,
                    'previous_unit' => $previousUnit,
                    'current_unit' => $currentUnit,

                    'discount_unit_for_disable' => $discountUnitForDisable,
                    'total_unit' => $totalUnit,
                    'unit_amount' => $unitAmount,




                    'fine_amount' => $validated['fine_amount'] ?? 0,


                    'total_charge' => $expectedTotal,
                ]);

                $todayAD = now()->toDateString();
                $todayBS = NepaliCalendar::adToBs($todayAD);

                DB::afterCommit(function () use ($meterReadingEntry, $todayAD, $todayBS) {
                    app(FineService::class)
                        ->processMeterEntry($meterReadingEntry, $todayAD, $todayBS);


                });

                return response()->json([
                    'message' => 'Meter reading entry updated successfully',
                    'data' => $meterReadingEntry->fresh(),
                    'rollover_count' => $rolloverCount,
                ], 200);
            });

        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors()
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Meter reading entry not found.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the meter reading entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


/**
 * Check if the member entry is involved in a name transfer
 */
private function isMemberInNameTransfer(int $memberEntryId): bool
{
    return NameTransferEntry::on('tenant')
        ->where('previous_member_entry_id', $memberEntryId)
        ->exists();
}

    /**
     * List all non-soft-deleted rate and capacity records.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listRatesAndCapacities(Request $request)
    {
        try {
            $rates = RateAndCapacity::select([
                'id',
                'unit_from',
                'unit_to',
                'rate_per_unit',
                'minimum_demand',
                'subsidy_charge',
                'service_charge',
                'other_charge'
            ])
                ->get();
            return response()->json([
                'message' => 'Rate and capacity records retrieved successfully',
                'data' => $rates
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving rate and capacity records',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function deleteMeterReadingEntry(Request $request, $id)
    {


        $connection = (new MeterReadingEntry)->getConnectionName() ?: config('database.default');
         $meterReadingEntry = MeterReadingEntry::on('tenant')->withoutTrashed()->findOrFail($id);
                $meterIssue = MeterIssue::on('tenant')->withTrashed()->findOrFail($meterReadingEntry->meter_issue_id);
                $memberEntryId = $meterIssue->member_entry_id;

                // Prevent deletion if involved in name transfer
                if ($this->isMemberInNameTransfer($memberEntryId)) {
                    return response()->json([
                        'message' => 'This meter reading entry cannot be deleted because the member has been transferred.'
                    ], 422);
                }
        try {
            $response = DB::connection($connection)->transaction(function () use ($id,  $meterReadingEntry) {

                $meterIssue = MeterIssue::on('tenant')->findOrFail($meterReadingEntry->meter_issue_id);
                
                // Only latest meter reading can be deleted
                $latestMeterReading = MeterReadingEntry::withoutTrashed()
                    ->where('meter_issue_id', $meterIssue->id)
                    //->orderBy('reading_date_in_bs', 'desc')
                        ->orderByDesc('id')
                    ->first();

                if (!$latestMeterReading || $latestMeterReading->id !== $meterReadingEntry->id) {
                    return response()->json([
                        'message' => 'Only the latest meter reading entry can be deleted for this meter issue.'
                    ], 422);
                }

                // Check if any Mahasul receipt exists for this meter reading or after its reading month
                $existsInMahasul = MahasulReceiptEntry::withoutTrashed()
                    ->where('meter_issue_id', $meterIssue->id)
                    ->where(function ($query) use ($meterReadingEntry) {
                        $query->where('meter_reading_entry_id', $meterReadingEntry->id)
                            //->orWhere('date_in_ad', '>=', $meterReadingEntry->reading_date_in_ad);
                            ->orWhere('created_at', '>=', $meterReadingEntry->created_at);
                    })
                    ->where('is_cancel', 0)
                    ->exists();

                
                if ($existsInMahasul) {
                return response()->json([
                    'message' => 'This meter reading entry cannot be deleted because a Mahasul Receipt has already been generated for or after this reading month.'
                ], 422);
            }

                $resolver = new MemberResolverService();
                $memberEntryId = $resolver->getMemberEntryIdFromMeterIssue(
                    $meterReadingEntry->meter_issue_id
                );

                /**
                 *  BLACKLIST TOTAL BEFORE DELETE
                 */
                $meterReadingIds = MeterReadingEntry::where('meter_issue_id', $meterIssue->id)
                    ->pluck('id');

                $transactionsBefore = CustomerTransaction::where('member_entry_id', $memberEntryId)
                    ->where(function ($query) use ($meterReadingIds) {
                        $query->whereIn('reference_id', $meterReadingIds)
                            ->orWhereIn('transaction_type', [1, 2, 5]);
                    })
                    ->get();

                $blacklistBefore = 0;

                foreach ($transactionsBefore as $tx) {

                    $multiplier = 0;

                    if (in_array($tx->transaction_type, [1, 5]) && $tx->direction === 'DR') {
                        $multiplier = 1;
                    } elseif ($tx->transaction_type === 2 && $tx->direction === 'CR') {
                        $multiplier = -1;
                    }

                    if ($multiplier !== 0 && $tx->charge_type == 11) {
                        $blacklistBefore += $tx->amount * $multiplier;
                    }
                }

                $blacklistBefore = max(0, $blacklistBefore);

                /**
                 *  Delete fines ALWAYS
                 */
                Fine::where('meter_reading_entry_id', $meterReadingEntry->id)
                    ->delete();

                /**
                 * Delete transactions of this meter reading
                 */
                CustomerTransaction::where('reference_id', $meterReadingEntry->id)
                    ->where('transaction_type', 1)
                    ->delete();

                /**
                 * BLACKLIST TOTAL AFTER DELETE
                 */
                $transactionsAfter = CustomerTransaction::where('member_entry_id', $memberEntryId)
                    ->where(function ($query) use ($meterReadingIds) {
                        $query->whereIn('reference_id', $meterReadingIds)
                            ->orWhereIn('transaction_type', [1, 2, 5]);
                    })
                    ->get();

                $blacklistAfter = 0;

                foreach ($transactionsAfter as $tx) {

                    $multiplier = 0;

                    if (in_array($tx->transaction_type, [1, 5]) && $tx->direction === 'DR') {
                        $multiplier = 1;
                    } elseif ($tx->transaction_type === 2 && $tx->direction === 'CR') {
                        $multiplier = -1;
                    }

                    if ($multiplier !== 0 && $tx->charge_type == 11) {
                        $blacklistAfter += $tx->amount * $multiplier;
                    }
                }

                $blacklistAfter = max(0, $blacklistAfter);

                /**
                 * CONDITIONAL UNBLACKLIST
                 */
                if ($blacklistBefore > 0 && $blacklistAfter == 0) {
                    MemberEntry::where('id', $memberEntryId)
                        ->update([
                            'is_blacklisted' => 0,
                        ]);
                }
                $meterReadingEntry->delete();

                return [
                    'message' => 'Meter reading entry deleted successfully',
                    'id' => $id
                ];
            });
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                return $response;
            }
            return response()->json($response, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Meter reading entry not found or already deleted',
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the meter reading entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }








}
