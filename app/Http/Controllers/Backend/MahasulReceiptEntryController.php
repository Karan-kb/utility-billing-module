<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\Helper;
use App\Helpers\NepaliCalendar;
use App\Helpers\TenantRuntimeHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\MahasulReceipt\StoreRequest;
use App\Http\Requests\MahasulReceipt\UpdateRequest;
use App\Models\AdvancePayment;
use App\Models\BlacklistPeriod;
use Illuminate\Support\Facades\Log;
use App\Models\DiscountAndFine;
use App\Models\MahasulReceiptEntry;
use App\Models\MasterSetup;
use App\Models\MemberEntry;
use App\Models\MeterIssue;
use App\Models\MeterReadingEntry;
use App\Models\TariffSetup;
use App\Services\CustomerChargeService;
use App\Services\MahasulReceiptTransactionService;
use App\Services\MemberInfoFromMeterIssueService;
use App\Services\MeterReadingFindService;
use App\Services\PaymentAllocationService;
use App\Services\PriorityChargeService;
use App\Services\VoucherEntryService;
use App\Services\PaymentService;
use App\Services\PaymentValidationService;
use App\Models\CustomerTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Models\Fine;
use App\Models\RateAndCapacity;
use App\Models\DisabilityDiscount;
use App\Services\CustomerDueService;
use App\Services\MemberResolverService;
use App\Services\VoucherBalanceService;
use Illuminate\Validation\Rule;

class MahasulReceiptEntryController extends Controller
{
    protected PaymentService $paymentService;
    protected $dueService;
protected MemberResolverService $memberResolver;

    public function __construct(PaymentService $paymentService, protected VoucherEntryService $voucherService, CustomerDueService $dueService, MemberResolverService $memberResolver)
    {
        $this->paymentService = $paymentService;
        $this->dueService = $dueService;
        $this->memberResolver = $memberResolver;
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'date_in_bs' => [
                    'required',
                    'string',
                    'max:10',
                ],
                'date_in_ad' => [
                    'required',
                    'date',
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
                    function ($attribute, $value, $fail) {
                        if ($value) {
                            try {
                                $adDate = Carbon::now()->format('Y-m-d');
                                $bsDate = NepaliCalendar::adToBs($adDate);
                                $bsParts = explode('-', $bsDate);
                                $currentBsYear = (int) $bsParts[0];
                                $currentBsMonth = (int) $bsParts[1];

                                $fiscalYear = $currentBsMonth >= 4 ? $currentBsYear : $currentBsYear - 1;
                                $fiscalYearCode = substr($fiscalYear, 2, 2) . substr($fiscalYear + 1, 2, 2);

                                $lastReceipt = MahasulReceiptEntry::withTrashed()
                                    ->where('voucher_no', 'like', "M{$fiscalYearCode}%")
                                    ->orderBy('id', 'desc')
                                    ->first();

                                $lastNumber = $lastReceipt ? (int) substr($lastReceipt->voucher_no, 8) : 0;
                                $expectedVoucherNo = "M{$fiscalYearCode}-" . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);

                                $pattern = "/^M{$fiscalYearCode}-\d{6}$/";
                                if (!preg_match($pattern, $value)) {
                                    $fail("The {$attribute} must be in format M{$fiscalYearCode}-XXXXXX (e.g., {$expectedVoucherNo}).");
                                    return;
                                }

                                if ($value !== $expectedVoucherNo) {
                                    $fail("Invalid {$attribute}. Expected: {$expectedVoucherNo}.");
                                }

                            } catch (\Exception $e) {
                                $fail("Error validating {$attribute}: " . $e->getMessage());
                            }
                        }
                    }
                ],
                'unit_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'rebate_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'fine_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'demand_charge' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'black_list_charge' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'subsidy_charge' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'service_charge' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'other_charge' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'total_due_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'total_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'advance_payment' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'cheque_no' => ['nullable', 'string', 'max:20', 'required_if:payment_by_bank,1'],
                'bank_id' => [
                    'nullable',
                    'integer',
                    Rule::requiredIf(fn() => (int) $request->bank_amount > 0),
                    function ($attr, $val, $fail) use ($request) {
                        PaymentValidationService::validateBank($request, $attr, $val, $fail);
                    }
                ],
                'paid_amount' => [
                    'required',
                    'numeric',
                    'min:1',
                    'max:999999999999.99',
                ],
                'payment_by_cash' => [
                    'required',
                    'boolean',
                    function ($attr, $val, $fail) use ($request) {
                        PaymentValidationService::validatePaymentMethods($request, $attr, $val, $fail);
                    }
                ],
                'payment_by_bank' => ['required', 'boolean'],
                'cash_amount' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:999999999999.99',
                    function ($attr, $val, $fail) use ($request) {
                        PaymentValidationService::validateCashAmount($request, $val, $fail);
                    }
                ],
                'bank_amount' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:999999999999.99',
                    function ($attr, $val, $fail) use ($request) {
                        PaymentValidationService::validateBankAmount($request, $val, $fail);
                    }
                ],
            ]);
            $totalAmount = $validated['total_amount'] ?? 0;
            $discountAmount = $validated['discount_amount'] ?? 0;
            $rebateAmount = $validated['rebate_amount'] ?? 0;
            $paidAmount = $validated['paid_amount'] ?? 0;
            $totalDueAmount = $validated['total_due_amount'] ?? 0;
            $advanceAmount= $validated['advance_payment'] ?? 0;
            if ($advanceAmount > 0) {
                $validated['total_due_amount'] = 0;
                $totalDueAmount = 0;
            } else {
                $expectedDue = $totalAmount - $paidAmount - $discountAmount - $rebateAmount;

                if (abs($totalDueAmount - $expectedDue) > 0.1) {
                    return response()->json([
                        'message' => 'Invalid total_due_amount: must equal total_amount - paid_amount - discount_amount - rebate_amount.',
                        'expected_total_due_amount' => round($expectedDue, 2),
                        'provided_total_due_amount' => round($totalDueAmount, 2),
                    ], 422);
                }
            }

            $bills = MeterReadingEntry::where('meter_issue_id', $validated['meter_issue_id'])
                ->whereIn('status', [0, 1])
                ->orderBy('reading_date_in_ad')
                ->get();

            if ($bills->isEmpty()) {
                return response()->json([
                    'message' => 'No pending bill to be paid for this meter',
                ], 200);
            }

            $discountUnit = 0;
            $meterIssue = MeterIssue::with('memberEntry')->find($validated['meter_issue_id']);
            if ($meterIssue && $meterIssue->memberEntry->is_disable) {
                $discount = DisabilityDiscount::first();
                if ($discount && $discount->is_applied) {
                    $discountUnit = $discount->units ?? 0;
                }
            }
            $tariff = TariffSetup::where('is_active', 1)->first();
                if (!$tariff) {
                    return response()->json([
                        'message' => 'Please setup Rule first.'
                    ], 422);
                }
            if (TenantRuntimeHelper::isRuntimeKhanepani()) {
                $rateRow = RateAndCapacity::where('tariff_setup_id', $tariff->id)
                    ->whereNull('deleted_at')
                    ->orderBy('unit_from', 'asc')
                    ->first();
            } else {
                // Bidut: include phase, capacity, purpose
                $rateRow = RateAndCapacity::where('tariff_setup_id', $tariff->id)
                    ->where('phase_id', $meterIssue->phase_id)
                    ->where('capacity_id', $meterIssue->capacity_id)
                    ->where('purpose_id', $meterIssue->purpose_id)
                    ->whereNull('deleted_at')
                    ->orderBy('unit_from', 'asc')
                    ->first();
            }

            $unpaidBillsCount = MeterReadingEntry::withoutTrashed()
                ->where('meter_issue_id', $validated['meter_issue_id'])
                ->where('status', 0)
                ->count();

            $unitRate = $rateRow ? $rateRow->rate_per_unit : 0;
            $disableDiscountAmount = $discountUnit * $unitRate * $unpaidBillsCount;

            $receipt = DB::connection('tenant')->transaction(function () use ($validated, $meterIssue, $disableDiscountAmount) {
                // $latestReading = MeterReadingEntry::withoutTrashed()
                //     ->where('meter_issue_id', $validated['meter_issue_id'])
                //     ->orderBy('reading_date_in_ad', 'desc')
                //     ->first();
                $latestReading = MeterReadingEntry::withoutTrashed()
                    ->where('meter_issue_id', $validated['meter_issue_id'])
                    ->latest('id') // order by id descending
                    ->first();
                $latestReadingId = $latestReading?->id;
                $fiscalYearId = Helper::getActiveFiscalYearId();

                $receipt = MahasulReceiptEntry::create([
                    'date_in_bs' => $validated['date_in_bs'],
                    'date_in_ad' => $validated['date_in_ad'],
                    'meter_issue_id' => $validated['meter_issue_id'],
                    'voucher_no' => $validated['voucher_no'],
                    'meter_reading_entry_id' => $latestReadingId,
                    'unit_amount' => $validated['unit_amount'],
                    'discount_amount' => $validated['discount_amount'] ?? 0,
                    'fine_amount' => $validated['fine_amount'],
                    'demand_charge' => $validated['demand_charge'],
                    'subsidy_charge' => $validated['subsidy_charge'],
                    'service_charge' => $validated['service_charge'],
                    'other_charge' => $validated['other_charge'],
                    'rebate_amount' => $validated['rebate_amount'],
                    'total_amount' => $validated['total_amount'],
                    'total_due_amount' => $validated['total_due_amount'],
                    'paid_amount' => $validated['paid_amount'],
                    'black_list_charge' => $validated['black_list_charge'],
                    'advance_payment' => $validated['advance_payment'] ?? 0,
                    'payment_by_cash' => $validated['payment_by_cash'] ?? false,
                    'payment_by_bank' => $validated['payment_by_bank'] ?? false,
                    'fiscal_year_id' => $fiscalYearId,
                    'cash_amount' => isset($validated['cash_amount'])
                        ? round((float) $validated['cash_amount'], 2)
                        : 0,
                    'bank_amount' => isset($validated['bank_amount'])
                        ? round((float) $validated['bank_amount'], 2)
                        : 0,
                    'cheque_no' => $validated['cheque_no'] ?? null,
                    'bank_id' => $validated['bank_id'] ?? null,
                ]);

                app(PriorityChargeService::class)->allocate(
                    $meterIssue->member_entry_id,
                    $receipt->id,
                    $validated['paid_amount'],
                    $validated['date_in_ad'],
                    $validated['discount_amount'],
                    $validated['rebate_amount'],
                    $disableDiscountAmount,
                );


                $this->paymentService->createPayments($receipt->id, [
                    'cash_amount' => $validated['cash_amount'] ?? 0,
                    'bank_amount' => $validated['bank_amount'] ?? 0,
                    'cheque_no' => $validated['cheque_no'] ?? null,
                    'bank_id' => $validated['bank_id'] ?? null,
                ], 11);

                $lines = [];
                $customerTransactionLines = $this->processCustomerTransactionsForVoucher($receipt->id, $receipt->voucher_no);
                $lines = array_merge($lines, $customerTransactionLines);

                if ($validated['payment_by_cash'] && $validated['cash_amount'] > 0) {
                    $lines[] = [
                        'account_head_id' => 1,
                        'debit' => $validated['cash_amount'],
                        'particulars' => "Cash Received (Mahasul Receipt {$receipt->voucher_no} )",
                    ];
                }

                if ($validated['payment_by_bank'] && $validated['bank_amount'] > 0) {
                    $lines[] = [
                        'account_head_id' => $validated['bank_id'],
                        'debit' => $validated['bank_amount'],
                        'particulars' => "Bank Received (Mahasul Receipt {$receipt->voucher_no})",
                    ];
                }

                if ($receipt->discount_amount > 0) {
                    $lines[] = [
                        'account_head_id' => 22,
                        'particulars' => "Discount Applied(Mahasul Receipt {$receipt->voucher_no})",
                        'debit' => $receipt['discount_amount'],
                    ];
                }

                if ($receipt->rebate_amount > 0) {
                    $lines[] = [
                        'account_head_id' => 23,
                        'particulars' => "Rebate Applied(Mahasul Receipt {$receipt->voucher_no})",
                        'debit' => $receipt['rebate_amount'],
                    ];
                }

                if ($disableDiscountAmount > 0) {
                    $lines[] = [
                        'account_head_id' => 53,
                        'particulars' => "Disable Discount Applied(Mahasul Receipt {$receipt->voucher_no})",
                        'debit' => $disableDiscountAmount,
                    ];
                }

                if (($receipt->advance_payment ?? 0) > 0) {
                    $lines[] = [
                        'account_head_id' => 9,
                        'particulars' => "Advance Recevied (Mahasul Receipt {$receipt->voucher_no})",
                        'credit' => $receipt->advance_payment,
                    ];
                }
                if (($receipt->total_due_amount ?? 0) == 0) {
                    $meterReadingService = new MeterReadingFindService();
                    $meterReadingIds = $meterReadingService->getMeterReadingIdsBetweenReceipts(
                        $receipt->meter_issue_id,
                        $receipt->id
                    );

                    MeterReadingEntry::whereIn('id', $meterReadingIds)
                        ->where('status', 1)  // Only pending readings (status 1)
                        ->update(['status' => 2]);  // Mark as paid/cleared (status 2)
                }
                VoucherBalanceService::validate($lines);
                 $memberEntryId = $this->memberResolver
    ->getMemberEntryIdFromMeterIssue($validated['meter_issue_id']);
                $voucher = $this->voucherService->create([
                    'date' => $validated['date_in_ad'],
                    'fiscal_year_id' => Helper::getActiveFiscalYearId(),
                    'voucher_no' => $receipt->voucher_no,
                    'particulars' => "Mahasul Receipt { $receipt->voucher_no}",
                    'status' => 2,
                    'reference_type' => 11,
                    'reference_id' => $receipt->id,
                  'member_entry_id' => $memberEntryId,
                    'lines' => $lines,
                ]);

                return $receipt;
            });

            return response()->json([
                'message' => 'Mahsul receipt record created successfully',
                'data' => $receipt,
            ], 201);

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();

            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors
            ], 422);
        } catch (\Exception $e) {

            if (str_contains($e->getMessage(), 'Voucher entry not balanced')) {
                return response()->json([
                    'message' => $e->getMessage()
                ], 422);
            }

            return response()->json([
                'message' => 'An error occurred while creating the Mahasul receipt entry.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process customer transactions for voucher lines
     *
     * @param int $receiptId
     * @param string $voucherNo
     * @return array
     */
    private function processCustomerTransactionsForVoucher($receiptId, $voucherNo)
    {
        $lines = [];

        // Handle advance used amount (charge_type 9, direction DR)
        $advanceUsedAmount = CustomerTransaction::where('receipt_id', $receiptId)
            ->where('charge_type', 9)
            ->where('direction', 'DR')
            ->first();

        if (($advanceUsedAmount->amount ?? 0) > 0) {
            $lines[] = [
                'account_head_id' => 9,
                'debit' => $advanceUsedAmount->amount,
                'particulars' => "Advance Used (Mahasul Receipt {$voucherNo})",
            ];
        }

        // Process charge amounts from customer transactions
        $chargeAmounts = CustomerTransaction::where('receipt_id', $receiptId)->get();

        // Customer transaction charge_type to account_head mapping
        $chargeTypeAccountMap = [
            11 => 20, // blacklist_charge
            3 => 13, // fine_amount
            1 => 14, // demand_charge
            5 => 15, // other_charge
            4 => 16, // subsidy_charge
            2 => 17, // service_charge
            6 => 11, // unit_amount (energy)
        ];

        foreach ($chargeAmounts as $tx) {
            $chargeLabel = match ($tx->charge_type) {
                1 => 'Demand Charge',
                2 => 'Service Charge',
                3 => 'Fine Charge',
                4 => 'Subsidy Charge',
                5 => 'Other Charge',
                6 => 'Energy Charge',
                7 => 'Rebate Charge',
                8 => 'Discount Charge',
                10 => 'Disable Discount',
                11 => 'Blacklist Charge',
                default => 'Unknown Charge',
            };

            if (!isset($chargeTypeAccountMap[$tx->charge_type])) {
                continue;
            }

            $lines[] = [
                'account_head_id' => $chargeTypeAccountMap[$tx->charge_type],
                'particulars' => $chargeLabel . " (Mahasul Receipt {$voucherNo})",
                'credit' => $tx->amount,
            ];
        }

        return $lines;
    }



    public function index(Request $request, MemberInfoFromMeterIssueService $memberInfoService)
    {


        try {
            $query = MahasulReceiptEntry::withoutTrashed()
                ->where('is_cancel', 0)
                ->with([
                    'bank:id,name_en,name_np',
                    'payments',
                ]);


            $receipts = $query->orderBy('created_at', 'desc')->paginate(10);

            $receipts->getCollection()->transform(function ($receipt) use ($memberInfoService) {
                $paymentData = $this->paymentService->transformPayments($receipt->payments ?? []);

                $latestAdvance = AdvancePayment::on('tenant')
                    ->where('meter_issue_id', $receipt->meter_issue_id)
                    ->where('type', 0)
                    ->whereNull('deleted_at')
                    ->latest('created_at')
                    ->first();

                $latestReceipt = MahasulReceiptEntry::on('tenant')
                    ->where('meter_issue_id', $receipt->meter_issue_id)
                    ->whereNull('deleted_at')
                    ->latest('created_at')
                    ->first();
                $memberInfo = $memberInfoService->getMemberInfo($receipt->meter_issue_id);

                return array_merge([
                    'id' => $receipt->id,
                    'date_in_bs' => $receipt->date_in_bs,
                    'date_in_ad' => $receipt->date_in_ad,
                    'meter_issue_id' => $receipt->meter_issue_id,
                    'member_no' => $memberInfo['member_no'],
                    'customer_name_en' => $memberInfo['customer_name_en'],
                    'customer_name_np' => $memberInfo['customer_name_np'],
                    'meter_no' => $memberInfo['meter_no'],
                    'area_id' => $memberInfo['area_id'],
                    'meter_reading_entry_id' => $receipt->meter_reading_entry_id,
                    'pan_no' => $memberInfo['pan_no'],
                    'unit_amount' => $receipt->unit_amount,
                    'discount_amount' => $receipt->discount_amount,
                    'rebate_amount' => $receipt->rebate_amount,
                    'fine_amount' => $receipt->fine_amount,
                    'voucher_no' => $receipt->voucher_no,
                    'payment_by_cash' => $receipt->payment_by_cash,
                    'payment_by_bank' => $receipt->payment_by_bank,
                    'cheque_no' => $receipt->cheque_no,
                    'bank_id' => $receipt->bank_id,
                    'bank_name_en' => optional($receipt->bank)->name_en,
                    'bank_name_np' => optional($receipt->bank)->name_np,
                    'cash_amount' => $receipt->cash_amount,
                    'bank_amount' => $receipt->bank_amount,
                    'mahasul_amount' => $receipt->mahasul_amount,
                    'total_due_amount' => $latestReceipt?->total_due_amount,
                    'paid_amount' => $receipt->paid_amount,
                    'receipt_amount' => $receipt->receipt_amount,
                    'advance_payment' => $latestReceipt?->advance_payment,
                    'demand_charge' => $receipt->demand_charge,
                    'subsidy_charge' => $receipt->subsidy_charge,
                    'service_charge' => $receipt->service_charge,
                    'other_charge' => $receipt->other_charge,
                    'black_list_charge' => $receipt->black_list_charge,
                    'total_amount' => $receipt->total_amount,
                    'advance_payment_from_table' => $latestAdvance?->amount,
                    'created_at' => $receipt->created_at,
                    'updated_at' => $receipt->updated_at,
                    'deleted_at' => $receipt->deleted_at,
                ], $paymentData);
            });

            return response()->json([
                'status' => 'success',
                'data' => $receipts,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving mahsul receipts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    public function customerDetail(Request $request, $id)
    {
        try {
            $searchTerm = $request->input('search');

            $customer = MemberEntry::with(['meterIssues', 'area'])
                ->where('is_active', 1)
                ->where('id', $id)
                ->firstOrFail();

            $meterIssue = $customer->meterIssues->first();

            // Latest Mahasul receipt (including cancelled)
            $latestReceipt = MahasulReceiptEntry::withoutTrashed()
                ->where('meter_issue_id', $meterIssue->id)
                ->latest('id')
                ->first();

            $rebateAmount = null;
            $excludeType5 = false;
            $serviceReading = null;

            // If latest receipt is cancelled and no newer non-cancelled exists
            if ($latestReceipt?->is_cancel) {
                $hasNewerNonCancelled = MahasulReceiptEntry::withoutTrashed()
                    ->where('meter_issue_id', $meterIssue->id)
                    ->where('id', '>', $latestReceipt->id)
                    ->where('is_cancel', 0)
                    ->exists();

                if (!$hasNewerNonCancelled) {
                    $excludeType5 = true;

                    // Use CustomerChargeService to calculate charges for service
                    $chargeService = new CustomerChargeService();
                    $totalChargesFiltered = $chargeService->getTotalCharges(
                        $customer->id,
                        $meterIssue->id,
                        $excludeType5
                    );

                    $chargeTypeNames = [
                        1 => 'demand_charge',
                        2 => 'service_charge',
                        3 => 'fine_charge',
                        4 => 'subsidy_charge',
                        5 => 'other_charge',
                        6 => 'unit_amount',
                        7 => 'rebate',
                        8 => 'discount',
                        9 => 'advance_charge',
                        10 => 'disable_discount',
                        11 => 'blacklist_charge'
                    ];

                    $namedCharges = [];
                    foreach ($totalChargesFiltered as $type => $amount) {
                        $namedCharges[$chargeTypeNames[$type]] = $amount;
                    }
                    Log::info('FINAL totalChargesFiltered', $totalChargesFiltered);

                    $serviceReading = [
                        'id' => null,
                        'fiscal_year_id' => null,
                        'reader_id' => null,
                        'uuid' => null,
                        'is_synced' => 0,
                        'status' => 2, // service / cancelled
                        'entry_type' => 1,
                        'reading_month_in_bs' => null,
                        'reading_date_in_bs' => null,
                        'reading_date_in_ad' => null,
                        'tariff_setup_id' => null,
                        'meter_issue_id' => $meterIssue->id,
                        'previous_unit' => 0,
                        'current_unit' => 0,
                        'discount_unit_for_disable' => 0,
                        'total_unit' => 0,
                        'unit_amount' => $namedCharges['unit_amount'] ?? 0,
                        'demand_charge' => $namedCharges['demand_charge'] ?? 0,
                        'service_charge' => $namedCharges['service_charge'] ?? 0,
                        'fine_amount' => $namedCharges['fine_charge'] ?? 0,
                        'subsidy_charge' => $namedCharges['subsidy_charge'] ?? 0,
                        'other_charge' => $namedCharges['other_charge'] ?? 0,
                        'discount_amount' => $namedCharges['discount'] ?? 0,
                        'total_charge' => array_sum($namedCharges),
                    ];

                    // Rebate calculation
                    $oldestReading = MeterReadingEntry::where('meter_issue_id', $meterIssue->id)
                        ->whereIn('status', [0, 1])
                        ->orderBy('reading_date_in_bs', 'asc')
                        ->first();

                    if ($oldestReading) {
                        $readingAdDate = NepaliCalendar::bsToAd($oldestReading->reading_date_in_bs);
                        $readingDate = Carbon::parse($readingAdDate)->startOfDay();
                        $today = Carbon::today();
                        $daysDifference = $readingDate->diffInDays($today);

                        $discountRule = DiscountAndFine::where('type', 1)
                            ->where('is_active', 1)
                            ->orderBy('days_after', 'asc')
                            ->first();

                        if ($discountRule && $daysDifference <= $discountRule->days_after) {
                            $unitAmount = $oldestReading->unit_amount;
                            $rebateAmount = $discountRule->amount_type == 1
                                ? round(($unitAmount * $discountRule->amount) / 100, 2)
                                : $discountRule->amount;
                        }
                    }
                }
            }

            if ($serviceReading) {
                $unpaidMeterReadingEntries = [$serviceReading];
            } else {
                $unpaidMeterReadingEntries = MeterReadingEntry::with(['customerTransaction'])
                    ->where('meter_issue_id', $meterIssue->id)
                    ->where('entry_type', 1)
                    ->where('status', 0)
                    ->get()
                    ->map(function ($entry) {
                        return [
                            'id' => $entry->id,
                            'fiscal_year_id' => $entry->fiscal_year_id,
                            'reader_id' => $entry->reader_id,
                            'uuid' => $entry->uuid,
                            'is_synced' => $entry->is_synced,
                            'status' => $entry->status,
                            'entry_type' => $entry->entry_type,
                            'reading_month_in_bs' => $entry->reading_month_in_bs,
                            'reading_date_in_bs' => $entry->reading_date_in_bs,
                            'reading_date_in_ad' => $entry->reading_date_in_ad,
                            'tariff_setup_id' => $entry->tariff_setup_id,
                            'meter_issue_id' => $entry->meter_issue_id,
                            'previous_unit' => $entry->previous_unit,
                            'current_unit' => $entry->current_unit,
                            'discount_unit_for_disable' => $entry->discount_unit_for_disable,
                            'total_unit' => $entry->total_unit,
                            'unit_amount' => $entry->unit_amount,
                            'total_charge' => $entry->customerTransaction->sum('amount'),
                            'demand_charge' => $entry->customerTransaction->where('charge_type', 1)->sum('amount'),
                            'service_charge' => $entry->customerTransaction->where('charge_type', 2)->sum('amount'),
                            'fine_amount' => $entry->customerTransaction->where('charge_type', 3)->sum('amount'),
                            'subsidy_charge' => $entry->customerTransaction->where('charge_type', 4)->sum('amount'),
                            'other_charge' => $entry->customerTransaction->where('charge_type', 5)->sum('amount'),
                            'discount_amount' => $entry->customerTransaction->where('charge_type', 8)->sum('amount'),
                        ];
                    })->toArray();


            }
           // Log::info('Unpaid readings count', ['count' => $unpaidMeterReadingEntries->count()]);

            $totalDue = $this->dueService->getPreviousTotalDue($customer->id, $meterIssue->id);
            Log::info('Total due', ['totalDue' => $totalDue]);
            // Blacklist amount
            $blackListAmount = null;
            if ((bool) $customer->is_blacklisted) {
                $blackListAmount = BlacklistPeriod::where('is_applied', 1)->value('amount');
            }
            Log::info('Blacklist amount', ['blackListAmount' => $blackListAmount]);
            $chargeService = new CustomerChargeService();

            $totalChargesFiltered = $chargeService->getTotalCharges(
                $customer->id,
                $meterIssue->id,
                $excludeType5 ?? false
            );



            $chargeTypeNames = [
                1 => 'demand_charge',
                2 => 'service_charge',
                3 => 'fine_charge',
                4 => 'subsidy_charge',
                5 => 'other_charge',
                6 => 'unit_amount',
                7 => 'rebate',
                8 => 'discount',
                9 => 'advance_charge',
                10 => 'disable_discount',
                11 => 'blacklist_charge'
            ];

            $namedCharges = array_fill_keys(array_values($chargeTypeNames), 0);

            foreach ($chargeTypeNames as $type => $name) {
                $namedCharges[$name] = $totalChargesFiltered[$type] ?? 0;
            }
            Log::info('NAMED CHARGES FINAL', $namedCharges);
            return [
                'meter_issue_id' => $meterIssue->id,
                'member_no' => $customer->member_no,
                'customer_name_en' => $customer->customer_name_en,
                'customer_name_np' => $customer->customer_name_np,
                'meter_no' => $meterIssue->meter_no,
                'area_name' => $customer->area->name_en ?? '',
                'pan_no' => $customer->pan_no,
                'previous_total_due' => $totalDue,
                'previous_advance' => $this->getAvailableAdvance($customer->id),
                'is_blacklisted' => (bool) $customer->is_blacklisted,
                'blacklist_amount' => $blackListAmount,
                'last_payment_date' => $latestReceipt?->date_in_ad ?? 0,
                'rebate_amount' => $rebateAmount,
                'total_unit_amount' => $namedCharges['unit_amount'] ?? 0,
                'total_demand_charge' => $namedCharges['demand_charge'] ?? 0,
                'total_service_charge' => $namedCharges['service_charge'] ?? 0,
                'total_fine_charge' => $namedCharges['fine_charge'] ?? 0,
                'total_subsidy_charge' => $namedCharges['subsidy_charge'] ?? 0,
                'total_other_charge' => $namedCharges['other_charge'] ?? 0,
                'total_blacklist_charge' => $namedCharges['blacklist_charge'] ?? 0,

                'meter_reading_entries' => $unpaidMeterReadingEntries,
            ];
          

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'No active member found matching your search criteria.',
                'error' => 'Member Not Found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while searching customer details !!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
   

    public function getAvailableAdvance(int $memberEntryId)
    {
        $cr = CustomerTransaction::where('member_entry_id', $memberEntryId)
            ->where('charge_type', 9)
            ->whereNull('deleted_at')
            ->where('direction', 'CR')
            ->sum('amount');

        $dr = CustomerTransaction::where('member_entry_id', $memberEntryId)
            ->where('charge_type', 9)
            ->whereNull('deleted_at')
            ->where('direction', 'DR')
            ->sum('amount');

        return max(0, round((float) $cr - (float) $dr, 2));
    }



    public function searchCustomer(Request $request)
    {
        try {
            $searchTerm = $request->input('search');

            $customers = MemberEntry::has('meterIssues')
                ->with(['meterIssues'])
                ->where('is_active', 1)
                ->where(function ($query) use ($searchTerm) {
                    $query->where('member_no', 'like', "%{$searchTerm}%")
                        ->orWhere('customer_name_en', 'like', "%{$searchTerm}%");
                })
                ->get()
                ->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'customer_name_en' => $customer->customer_name_en,
                        'member_no' => $customer->member_no,
                        'meter_no' => $customer->meterIssues->first()->meter_no ?? '',
                    ];
                });
            return response()->json([
                'message' => 'Customer Lists',
                'data' => $customers,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'No active member found matching your search criteria.',
                'error' => 'Member Not Found'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while searching customer details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getReceiptPaymentDetails(
        Request $request,
        $id,
        MemberInfoFromMeterIssueService $memberInfoService,
        PaymentService $paymentService // Add PaymentService dependency
    ) {
        try {
            // Get the specific Mahasul receipt with payments
            $receipt = MahasulReceiptEntry::withoutTrashed()
                ->with([
                    'payments' => function ($query) {
                        $query->with('bank'); // Eager load bank relationship on payments
                    }
                ])
                ->where('is_cancel', 0)
                ->findOrFail($id);

            // Get member info (for names, meter_no, area_id)
            $memberInfo = $memberInfoService->getMemberInfo($receipt->meter_issue_id);

            // Resolve member_entry_id using service
            $memberResolver = app(\App\Services\MemberResolverService::class);
            $memberId = $memberResolver->getMemberEntryIdFromMeterIssue($receipt->meter_issue_id);

            // Use PaymentService to transform payments
            $paymentDetails = $paymentService->transformPayments($receipt->payments);

            // Paid amount distribution according to priority
            $paidAmount = (float) $receipt->paid_amount;
            $totalDueAmount = (float) $receipt->total_due_amount;

            $priorityOrder = [
                'black_list_charge' => (float) $receipt->black_list_charge,
                'fine_amount' => (float) $receipt->fine_amount,
                'demand_charge' => (float) $receipt->demand_charge,
                'other_charge' => (float) $receipt->other_charge,
                'subsidy_charge' => (float) $receipt->subsidy_charge,
                'service_charge' => (float) $receipt->service_charge,
                'unit_amount' => (float) $receipt->unit_amount,
            ];

            $paidChargeTypes = [];
            $dueAmount = [];
            $remainingPaid = $paidAmount;

            foreach ($priorityOrder as $key => $amount) {
                if ($amount <= 0) {
                    $dueAmount[$key] = 0;
                    continue;
                }

                if ($remainingPaid >= $amount) {
                    $paidChargeTypes[$key] = $amount;
                    $dueAmount[$key] = 0;
                    $remainingPaid -= $amount;
                } else {
                    $paidChargeTypes[$key] = $remainingPaid > 0 ? $remainingPaid : 0;
                    $dueAmount[$key] = $amount - $paidChargeTypes[$key];
                    $remainingPaid = 0;
                }
            }

            // Add any remaining charges that are not part of the priority
            foreach (['discount_amount', 'rebate_amount', 'advance_payment'] as $extra) {
                if (isset($receipt->$extra) && (float) $receipt->$extra > 0) {
                    $paidChargeTypes[$extra] = (float) $receipt->$extra;
                    $dueAmount[$extra] = 0;
                }
            }

            // Round values
            $paidChargeTypes = array_map(fn($v) => round($v, 2), $paidChargeTypes);
            $dueAmount = array_map(fn($v) => round($v, 2), $dueAmount);

            // Prepare response
            $result = [
                'id' => $receipt->id,
                'date_in_bs' => $receipt->date_in_bs,
                'date_in_ad' => $receipt->date_in_ad,
                'voucher_no' => $receipt->voucher_no,
                'member_entry_id' => $memberId,
                'member_no' => $memberInfo['member_no'],
                'customer_name_en' => $memberInfo['customer_name_en'],
                'customer_name_np' => $memberInfo['customer_name_np'],
                'pan_no' => $memberInfo['pan_no'],
                'meter_no' => $memberInfo['meter_no'],
                // Payment details from service (will be 0/null when no payments)

                'discount_amount' => $receipt->discount_amount ?? 0,
                'rebate_amount' => $receipt->rebate_amount ?? 0,
                'advance_payment' => $receipt->advance_payment ?? 0,
                'paid_amount' => $paidChargeTypes,
                'due_amount' => $dueAmount,
                'total_due_amount' => $receipt->total_due_amount ?? 0,
                'total_paid_amount' => $receipt->paid_amount ?? 0,
                'payment_by_cash' => $paymentDetails['payment_by_cash'] ? 1 : 0,
                'payment_by_bank' => $paymentDetails['payment_by_bank'] ? 1 : 0,
                'cheque_no' => $paymentDetails['cheque_no'],
                'bank_id' => $paymentDetails['bank_id'],
                'bank_name_en' => $paymentDetails['bank_name_en'],
                'bank_name_np' => $paymentDetails['bank_name_np'],
                'cash_amount' => $paymentDetails['cash_amount'],
                'bank_amount' => $paymentDetails['bank_amount'],
                'created_at' => $receipt->created_at ? $receipt->created_at->format('Y-m-d H:i:s') : null,
            ];

            return response()->json($result, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Mahasul Receipt not found or already cancelled',
                'error_code' => 'RECEIPT_NOT_FOUND'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while retrieving receipt details',
                'error' => $e->getMessage(),
                'error_code' => 'SERVER_ERROR'
            ], 500);
        }
    }








}
