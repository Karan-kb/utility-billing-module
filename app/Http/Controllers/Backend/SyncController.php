<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\Helper;
use App\Helpers\NepaliCalendar;
use App\Helpers\TenantRuntimeHelper;
use Illuminate\Http\Request;
use App\Models\MeterReadingEntry;
use App\Models\MahasulReceiptEntry;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\MasterSetup;
use App\Models\MeterIssue;
use App\Models\MemberEntry;
use App\Models\TariffSetup;
use App\Models\RateAndCapacity;
use App\Models\DisabilityDiscount;
use App\Services\PriorityChargeService;
use App\Services\PaymentService;
use App\Services\VoucherEntryService;
use App\Services\CustomerDueService;
use App\Services\FineService;
use App\Services\CustomerTransactionService;
use App\Services\PaymentAllocationService;
use App\Services\MeterReadingFindService;
use App\Http\Controllers\DateConversionController;
use App\Services\KnowMeterStartService;
use App\Services\PaymentValidationService;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SyncController extends Controller
{
    protected DateConversionController $dateConversionController;
    protected PaymentService $paymentService;
    protected VoucherEntryService $voucherService;
    protected FineService $fineService;
    protected PaymentAllocationService $allocationService;
    protected CustomerDueService $dueService;
    protected KnowMeterStartService $knowMeterStartService;
    protected PriorityChargeService $priorityChargeService;
    protected CustomerTransactionService $customerTransactionService;

    public function __construct(
        DateConversionController $dateConversionController,
        PaymentService $paymentService,
        VoucherEntryService $voucherService,
        FineService $fineService,
        PaymentAllocationService $allocationService,
        CustomerDueService $dueService,
        KnowMeterStartService $knowMeterStartService,
        PriorityChargeService $priorityChargeService,
        CustomerTransactionService $customerTransactionService
    ) {
        $this->dateConversionController = $dateConversionController;
        $this->paymentService = $paymentService;
        $this->voucherService = $voucherService;
        $this->fineService = $fineService;
        $this->allocationService = $allocationService;
        $this->dueService = $dueService;
        $this->knowMeterStartService = $knowMeterStartService;
        $this->priorityChargeService = $priorityChargeService;
        $this->customerTransactionService = $customerTransactionService;
    }



     public function upload(Request $request)
    {
        $errors = [
            'meter_readings' => [],
            'mahsul_receipts' => [],
        ];

        $connection = (new MeterReadingEntry)->getConnectionName() ?: config('database.default');

        try {
            $payload = $request->all();

            if (
                !isset($payload['meter_readings']) ||
                !is_array($payload['meter_readings']) ||
                !isset($payload['mahsul_receipts']) ||
                !is_array($payload['mahsul_receipts'])
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid payload. Expected arrays: meter_readings, mahsul_receipts.',
                    'errors' => $errors,
                ], 422);
            }

            $incomingMeterReadings = $payload['meter_readings'] ?? [];
            $incomingMahsulReceipts = $payload['mahsul_receipts'] ?? [];

            $meterReadingsWithIndex = [];
            foreach ($incomingMeterReadings as $i => $row) {
                $meterReadingsWithIndex[] = ['index' => $i, 'data' => $row];
            }

            $mahsulReceiptsWithIndex = [];
            foreach ($incomingMahsulReceipts as $i => $row) {
                $mahsulReceiptsWithIndex[] = ['index' => $i, 'data' => $row];
            }

            // Get all meter issue IDs from both arrays
            $allMeterIssueIds = collect($meterReadingsWithIndex)->pluck('data.meter_issue_id')
                ->merge(collect($mahsulReceiptsWithIndex)->pluck('data.meter_issue_id'))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $incomingMeterUuids = collect($meterReadingsWithIndex)->pluck('data.uuid')->filter()->unique()->values()->all();
            $incomingMahsulUuids = collect($mahsulReceiptsWithIndex)->pluck('data.uuid')->filter()->unique()->values()->all();
            $incomingVoucherNos = collect($mahsulReceiptsWithIndex)->pluck('data.voucher_no')->filter()->unique()->values()->all();

            // Get active meter issues
            $activeMeterIssues = MeterIssue::whereIn('id', $allMeterIssueIds)
                ->where('is_active', 1)
                ->get()
                ->keyBy('id');

            // Get latest reading month for each meter issue
            $latestReadingMonthByMeterIssue = [];
            $latestReadingsRaw = MeterReadingEntry::withoutTrashed()
                ->whereIn('meter_issue_id', $allMeterIssueIds)
                ->where('entry_type', 1)
                ->select('meter_issue_id', DB::raw('MAX(CAST(reading_month_in_bs AS UNSIGNED)) as last_month'))
                ->groupBy('meter_issue_id')
                ->get();

            foreach ($latestReadingsRaw as $row) {
                $meterIssueId = $row->meter_issue_id ?? null;
                $lastMonth = (int) ($row->last_month ?? 0);
                if ($meterIssueId) {
                    $latestReadingMonthByMeterIssue[$meterIssueId] = $lastMonth;
                }
            }

            // Check existing UUIDs and voucher numbers
            $existingMeterUuids = !empty($incomingMeterUuids)
                ? MeterReadingEntry::whereIn('uuid', $incomingMeterUuids)->pluck('uuid')->toArray()
                : [];
            $existingMahsulUuids = !empty($incomingMahsulUuids)
                ? MahasulReceiptEntry::whereIn('uuid', $incomingMahsulUuids)->pluck('uuid')->toArray()
                : [];
            $existingVoucherNos = !empty($incomingVoucherNos)
                ? MahasulReceiptEntry::whereIn('dummy_voucher', $incomingVoucherNos)->withTrashed()->pluck('dummy_voucher')->toArray()
                : [];

            // Get fiscal year info for voucher generation
            $fiscalYearCode = null;
            $lastVoucherNumber = 0;
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

                $lastVoucherNumber = $lastReceipt ? (int) substr($lastReceipt->voucher_no, 8) : 0;
            } catch (\Throwable $e) {
                Log::error('Failed to get fiscal year info: ' . $e->getMessage());
            }

              DB::connection($connection)->beginTransaction();

        try {
              $createdMeterReadings = [];
                $createdMahsulReceipts = [];
                $meterReadingsByUuid = [];

                // FIRST PASS: Validate all meter readings without creating anything
                foreach ($meterReadingsWithIndex as $item) {
                    $origIndex = $item['index'];
                    $row = $item['data'];

                    // Validate UUID
                    if (empty($row['uuid'])) {
                        $errors['meter_readings'][$origIndex] = 'UUID is required';
                        continue;
                    }

                    if (in_array($row['uuid'], $existingMeterUuids, true)) {
                        $errors['meter_readings'][$origIndex] = "Duplicate entry for UUID '{$row['uuid']}'.";
                        continue;
                    }

                    // Validate meter issue
                    $meterIssueId = $row['meter_issue_id'] ?? null;
                    if (!$meterIssueId || !isset($activeMeterIssues[$meterIssueId])) {
                        $errors['meter_readings'][$origIndex] = "Invalid or inactive meter_issue_id: {$meterIssueId}";
                        continue;
                    }

                    $meterIssue = $activeMeterIssues[$meterIssueId];
                    // SKIP if this month already exists for this meter
                    $monthExists = MeterReadingEntry::withoutTrashed()
                        ->where('meter_issue_id', $meterIssueId)
                        ->where('reading_month_in_bs', $row['reading_month_in_bs'])
                        ->where('entry_type', 1)
                        ->exists();

                    if ($monthExists) {
                        Log::info("Skipping meter_issue_id {$meterIssueId} month {$row['reading_month_in_bs']} because it already exists.");
                        continue;
                    }

                    try {
                        // Validate the meter reading row structure and business rules
                        $this->validateMeterReadingRow($row, $meterIssue, $latestReadingMonthByMeterIssue[$meterIssueId] ?? null);
                    } catch (ValidationException $ve) {
                        $errors['meter_readings'][$origIndex] = collect($ve->errors())->flatten()->first();
                        continue;
                    } catch (\Exception $e) {
                        $errors['meter_readings'][$origIndex] = $e->getMessage();
                        continue;
                    }
                }

                // If there are any meter reading errors, rollback immediately
            if (!empty($errors['meter_readings'])) {
                DB::connection($connection)->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Meter reading validation failed. No records were saved.',
                    'errors' => $errors,
                ], 422);
            }


                // SECOND PASS: Process meter readings (create them first)
                foreach ($meterReadingsWithIndex as $item) {
                    $origIndex = $item['index'];
                    $row = $item['data'];

                    $meterIssueId = $row['meter_issue_id'];
                    $meterIssue = $activeMeterIssues[$meterIssueId];
                    // SKIP if this month already exists for this meter
                    $monthExists = MeterReadingEntry::withoutTrashed()
                        ->where('meter_issue_id', $meterIssueId)
                        ->where('reading_month_in_bs', $row['reading_month_in_bs'])
                        ->where('entry_type', 1)
                        ->exists();

                    if ($monthExists) {
                        Log::info("Skipping creation for meter_issue_id {$meterIssueId} month {$row['reading_month_in_bs']} because it already exists.");
                        continue;
                    }

                    try {
                        // Get previous unit
                        $previousUnit = $this->knowMeterStartService->getMeterStartNo($meterIssueId);

                        // Get validated data
                        $validated = $this->validateMeterReadingRow($row, $meterIssue, $latestReadingMonthByMeterIssue[$meterIssueId] ?? null);
                        
                        // Get active tariff
                        $tariff = TariffSetup::where('is_active', 1)->first();
                        if (!$tariff) {
                            throw new \Exception('No active tariff setup found.');
                        }

                        $currentUnit = (float) ($validated['current_unit']);

                        // Calculate discount for disabled customers
                        $discountUnit = 0;
                        $memberEntry = MemberEntry::on('tenant')->find($meterIssue->member_entry_id);
                        if ($memberEntry && $memberEntry->is_disable) {
                            $discount = DisabilityDiscount::first();
                            if ($discount && $discount->is_applied) {
                                $discountUnit = $discount->units ?? 0;
                            }
                        }

                        $unitBeforeDiscount = $currentUnit - $previousUnit;
                        $totalUnit = max(0, $unitBeforeDiscount - $discountUnit);

                        $fiscalYearID = Helper::getActiveFiscalYearId();

                        // Create meter reading entry
                        $meterReadingEntry = MeterReadingEntry::create([
                            'entry_type' => 1,
                            'reader_id' => Auth::id(),
                            'uuid' => $row['uuid'],
                            'reading_month_in_bs' => $validated['reading_month_in_bs'],
                            'reading_date_in_bs' => $validated['reading_date_in_bs'],
                            'reading_date_in_ad' => $validated['reading_date_in_ad'],
                            'meter_issue_id' => $meterIssue->id,
                            'tariff_setup_id' => $tariff->id,
                            'previous_unit' => $previousUnit,
                            'current_unit' => $currentUnit,
                            'discount_unit_for_disable' => $discountUnit,
                            'total_unit' => $unitBeforeDiscount,
                            'unit_amount' => $validated['unit_amount'],
                            'fine_amount' => 0,
                            'total_charge' => $validated['total_charge'],
                            'sub_total_charge' => $validated['total_charge'],
                            'fiscal_year_id' => $fiscalYearID,
                            'status' => 0,
                            'is_synced' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // Check if change meter record exists
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
                            'fine_amount' => 0,
                        ];

                        // Check if advance exists
                        $cr = \App\Models\CustomerTransaction::where('member_entry_id', $meterIssue->member_entry_id)
                            ->where('charge_type', 9)
                            ->where('direction', 'CR')
                            ->sum('amount');
                        $dr = \App\Models\CustomerTransaction::where('member_entry_id', $meterIssue->member_entry_id)
                            ->where('charge_type', 9)
                            ->where('direction', 'DR')
                            ->sum('amount');

                        $hasAdvance = ($cr - $dr > 0);

                        if (!$hasAdvance) {
                            // Create customer transactions
                            $this->customerTransactionService->createForMeterReading(
                                $meterIssue->member_entry_id,
                                $meterReadingEntry->id,
                                $charges,
                                $validated['reading_date_in_ad']
                            );
                        }

                        // Apply fine if reading date is in the past
                        if ($meterReadingEntry->reading_date_in_ad < now()->toDateString()) {
                            try {
                                $todayAD = now()->toDateString();
                                $todayBS = NepaliCalendar::adToBs($todayAD);

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

                        $createdMeterReadings[] = $meterReadingEntry;
                        $meterReadingsByUuid[$row['uuid']] = $meterReadingEntry;

                    } catch (\Exception $e) {
                    // If any meter reading creation fails, rollback everything
                    DB::connection($connection)->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Error during meter reading creation: ' . $e->getMessage(),
                        'errors' => $errors,
                    ], 422);
                }
            }

                // THIRD PASS: Validate all mahsul receipts (now with meter readings in database)
                $nextVoucherNumber = $lastVoucherNumber;
                $processedVouchers = [];

                foreach ($mahsulReceiptsWithIndex as $item) {
                    $origIndex = $item['index'];
                    $row = $item['data'];

                    // Validate UUID
                    if (empty($row['uuid'])) {
                        $errors['mahsul_receipts'][$origIndex] = 'UUID is required';
                        continue;
                    }

                    if (in_array($row['uuid'], $existingMahsulUuids, true)) {
                        $errors['mahsul_receipts'][$origIndex] = "Duplicate entry for UUID '{$row['uuid']}'.";
                        continue;
                    }

                    // Check for duplicate voucher in batch
                    if (isset($processedVouchers[$row['voucher_no']])) {
                        $errors['mahsul_receipts'][$origIndex] = "Duplicate voucher_no '{$row['voucher_no']}' in upload batch.";
                        continue;
                    }

                    // Check if voucher already exists in database
                    if (in_array($row['voucher_no'], $existingVoucherNos, true)) {
                        $errors['mahsul_receipts'][$origIndex] = "voucher_no '{$row['voucher_no']}' already exists.";
                        continue;
                    }

                    // Validate meter issue
                    $meterIssueId = $row['meter_issue_id'] ?? null;
                    if (!$meterIssueId || !isset($activeMeterIssues[$meterIssueId])) {
                        $errors['mahsul_receipts'][$origIndex] = "Invalid or inactive meter_issue_id: {$meterIssueId}";
                        continue;
                    }

                    $meterIssue = $activeMeterIssues[$meterIssueId];

                    try {
                        // Validate mahsul receipt row
                        $validated = $this->validateMahsulReceiptRow($row);

                        // Check if there are unpaid bills (now should include the newly created meter readings)
                        $bills = MeterReadingEntry::where('meter_issue_id', $meterIssue->id)
                            ->whereIn('status', [0, 1])
                            ->orderBy('reading_date_in_ad')
                            ->get();

                        if ($bills->isEmpty()) {
                            $errors['mahsul_receipts'][$origIndex] = 'No pending bill to be paid for this meter';
                            continue;
                        }

                        $processedVouchers[$row['voucher_no']] = true;
                        $nextVoucherNumber++;

                    } catch (ValidationException $ve) {
                        $errors['mahsul_receipts'][$origIndex] = collect($ve->errors())->flatten()->first();
                        continue;
                    } catch (\Exception $e) {
                        $errors['mahsul_receipts'][$origIndex] = $e->getMessage();
                        continue;
                    }
                }

                // If there are any mahsul receipt errors, rollback everything
                 if (!empty($errors['mahsul_receipts'])) {
                DB::connection($connection)->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Mahsul receipt validation failed. No records were saved.',
                    'errors' => $errors,
                ], 422);
            }

                // Reset counter for actual processing
                $nextVoucherNumber = $lastVoucherNumber;
                $processedVouchers = [];

                // FOURTH PASS: Process mahsul receipts (now all validated)
                foreach ($mahsulReceiptsWithIndex as $item) {
                    $origIndex = $item['index'];
                    $row = $item['data'];

                    $meterIssueId = $row['meter_issue_id'];
                    $meterIssue = $activeMeterIssues[$meterIssueId];

                    try {
                        // Validate mahsul receipt row
                        $validated = $this->validateMahsulReceiptRow($row);

                        // Get unpaid bills
                        $bills = MeterReadingEntry::where('meter_issue_id', $meterIssue->id)
                            ->whereIn('status', [0, 1])
                            ->orderBy('reading_date_in_ad')
                            ->get();

                        // Calculate disable discount if applicable
                        $discountUnit = 0;
                        if ($meterIssue->memberEntry && $meterIssue->memberEntry->is_disable) {
                            $discount = DisabilityDiscount::first();
                            if ($discount && $discount->is_applied) {
                                $discountUnit = $discount->units ?? 0;
                            }
                        }

                        $tariff = TariffSetup::where('is_active', 1)->first();
                        $rateRow = null;
                        $unitRate = 0;

                      if (TenantRuntimeHelper::isRuntimeKhanepani()) {
                        if ($tariff) {
                            $rateRow = RateAndCapacity::where('tariff_setup_id', $tariff->id)
                                ->whereNull('deleted_at')
                                ->orderBy('unit_from', 'asc')
                                ->first();

                            $unitRate = $rateRow ? $rateRow->rate_per_unit : 0;
                        }
                      }else{
                        if ($tariff) {
                            $rateRow = RateAndCapacity::where('tariff_setup_id', $tariff->id)
                                ->where('phase_id', $meterIssue->phase_id)
                                ->where('capacity_id', $meterIssue->capacity_id)
                                ->where('purpose_id', $meterIssue->purpose_id)
                                ->whereNull('deleted_at')
                                ->orderBy('unit_from', 'asc')
                                ->first();

                            $unitRate = $rateRow ? $rateRow->rate_per_unit : 0;
                        }
                    }

                        $unpaidBillsCount = MeterReadingEntry::withoutTrashed()
                            ->where('meter_issue_id', $meterIssue->id)
                            ->where('status', 0)
                            ->count();

                        $disableDiscountAmount = $discountUnit * $unitRate * $unpaidBillsCount;

                        // Generate next voucher number
                        $nextVoucherNumber++;
                        $calculatedVoucherNo = "M{$fiscalYearCode}-" . str_pad($nextVoucherNumber, 6, '0', STR_PAD_LEFT);

                        // Get latest reading ID
                        $latestReading = MeterReadingEntry::withoutTrashed()
                            ->where('meter_issue_id', $meterIssue->id)
                            ->orderBy('reading_date_in_ad', 'desc')
                            ->first();

                        $fiscalYearId = Helper::getActiveFiscalYearId();
                        // Ensure total_due_amount is total_amount - paid_amount
                        $validated['total_due_amount'] = ($validated['total_amount'] ?? 0) - ($validated['paid_amount'] ?? 0);
                        if ($validated['total_due_amount'] < 0) {
                            throw new \Exception("Invalid total_due_amount: cannot be negative for voucher {$row['voucher_no']}");
                        }
                        // Create mahsul receipt
                        $receipt = MahasulReceiptEntry::create([
                            'uuid' => $row['uuid'],
                            'dummy_voucher' => $row['voucher_no'],
                            'voucher_no' => $calculatedVoucherNo,
                            'date_in_bs' => $validated['date_in_bs'],
                            'date_in_ad' => $validated['date_in_ad'],
                            'meter_issue_id' => $meterIssue->id,
                            'meter_reading_entry_id' => $latestReading?->id,
                            'unit_amount' => $validated['unit_amount'] ?? 0,
                            'discount_amount' => 0,
                            'fine_amount' => 0,
                            'demand_charge' => $validated['demand_charge'] ?? 0,
                            'subsidy_charge' => $validated['subsidy_charge'] ?? 0,
                            'service_charge' => $validated['service_charge'] ?? 0,
                            'other_charge' => $validated['other_charge'] ?? 0,
                            'rebate_amount' => $validated['rebate_amount'] ?? 0,
                            'black_list_charge' => 0,
                            'total_due_amount' => $validated['total_due_amount'] ?? 0,
                            'total_amount' => $validated['total_amount'] ?? 0,
                            'paid_amount' => $validated['paid_amount'],
                            'advance_payment' => $validated['advance_payment'] ?? 0,
                           'payment_by_cash' => true,
                            'payment_by_bank' => false,
                            'cash_amount' => $validated['paid_amount'],
                            'bank_amount' => 0,
                            'cheque_no' => null,
                            'bank_id' => null,
                            'fiscal_year_id' => $fiscalYearId,
                            'is_synced' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // Allocate payment
                        $this->priorityChargeService->allocate(
                            $meterIssue->member_entry_id,
                            $receipt->id,
                            $validated['paid_amount'],
                            $validated['date_in_ad'],
                            $validated['discount_amount'] ?? 0,
                            $validated['rebate_amount'] ?? 0,
                            $disableDiscountAmount
                        );

                        // Create payment records
                        $this->paymentService->createPayments($receipt->id, [
                            'cash_amount' => $validated['paid_amount'],
                            'bank_amount' => 0,
                            'cheque_no' => null,
                            'bank_id' => null,
                        ], 11);

                        // Process customer transactions for voucher
                        $lines = [];
                        $customerTransactionLines = $this->processCustomerTransactionsForVoucher($receipt->id, $receipt->voucher_no);
                        $lines = array_merge($lines, $customerTransactionLines);

                        // Add payment lines
                       if ($validated['paid_amount'] > 0) {
                            $lines[] = [
                                'account_head_id' => 1,
                                'debit' => $validated['paid_amount'],
                                'particulars' => "Cash Received (Mahasul Receipt {$receipt->voucher_no})",
                            ];
                        }

                    
                        if (($receipt->discount_amount ?? 0) > 0) {
                            $lines[] = [
                                'account_head_id' => 22,
                                'particulars' => "Discount Applied (Mahasul Receipt {$receipt->voucher_no})",
                                'debit' => $receipt->discount_amount,
                            ];
                        }

                        if (($receipt->rebate_amount ?? 0) > 0) {
                            $lines[] = [
                                'account_head_id' => 23,
                                'particulars' => "Rebate Applied (Mahasul Receipt {$receipt->voucher_no})",
                                'debit' => $receipt->rebate_amount,
                            ];
                        }

                        if ($disableDiscountAmount > 0) {
                            $lines[] = [
                                'account_head_id' => 53,
                                'particulars' => "Disable Discount Applied (Mahasul Receipt {$receipt->voucher_no})",
                                'debit' => $disableDiscountAmount,
                            ];
                        }

                        if (($receipt->advance_payment ?? 0) > 0) {
                            $lines[] = [
                                'account_head_id' => 9,
                                'particulars' => "Advance Received (Mahasul Receipt {$receipt->voucher_no})",
                                'credit' => $receipt->advance_payment,
                            ];
                        }

                        // Update meter reading status if total due is 0
                        if (($receipt->total_due_amount ?? 0) == 0) {
                            $meterReadingService = new MeterReadingFindService();
                            $meterReadingIds = $meterReadingService->getMeterReadingIdsBetweenReceipts(
                                $receipt->meter_issue_id,
                                $receipt->id
                            );

                            MeterReadingEntry::whereIn('id', $meterReadingIds)
                                ->where('status', 1)
                                ->update(['status' => 2]);
                        }

                        // Create voucher
                        $voucher = $this->voucherService->create([
                            'date' => $validated['date_in_ad'],
                            'fiscal_year_id' => Helper::getActiveFiscalYearId(),
                            'voucher_no' => $receipt->voucher_no,
                            'particulars' => "Mahasul Receipt {$receipt->voucher_no}",
                            'status' => 2,
                            'reference_type' => 11,
                            'reference_id' => $receipt->id,
                            'member_entry_id' => $meterIssue->member_entry_id ?? null,
                            'lines' => $lines,
                        ]);

                        $createdMahsulReceipts[] = $receipt;

                    } catch (\Exception $e) {
                    // If any mahsul receipt creation fails, rollback everything
                    DB::connection($connection)->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Error during mahsul receipt creation: ' . $e->getMessage(),
                        'errors' => $errors,
                    ], 422);
                }
            }
            DB::connection($connection)->commit();

                return response()->json([
                'success' => true,
                'message' => 'Meter readings and Mahasul receipts uploaded successfully.',
                'data' => [
                    'meter_readings' => $createdMeterReadings,
                    'mahsul_receipts' => $createdMahsulReceipts,
                ],
                'errors' => $errors,
            ], 201);

        } catch (\Exception $e) {
            // Rollback on any exception
            DB::connection($connection)->rollBack();
            throw $e;
        }

    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => collect($e->errors())->flatten()->first(),
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        // Check if this is one of our intentional rollback messages
        if (
            strpos($e->getMessage(), 'validation failed') !== false ||
            strpos($e->getMessage(), 'Error during') !== false
        ) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'success' => false,
            'message' => 'An error occurred during upload.',
            'errors' => $errors,
            'error' => $e->getMessage(),
        ], 500);
    }
}
    protected function validateMeterReadingRow(array $row, MeterIssue $meterIssue, $lastMonth = null)
    {
        $rules = [
            'reading_month_in_bs' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($meterIssue, $lastMonth) {
                    $month = (int) $value;

                    // Validate month range (Shrawan to Ashad)
                    $isValidMonth = ($month >= 4 && $month <= 12) || ($month >= 1 && $month <= 3);
                    if (!$isValidMonth) {
                        $fail("Reading month must be between Shrawan (4) and Ashad (3) of the fiscal year.");
                    }

                    // Check if it's irrigation customer
                    $isIrrigationCustomer = false;
                    $purposeSetup = MasterSetup::where('id', $meterIssue->purpose_id)
                        ->where('master_setup_type_id', 1)
                        ->where('name_en', 'Irrigation')
                        ->where('is_active', true)
                        ->first();

                    if ($purposeSetup) {
                        $isIrrigationCustomer = true;
                    }

                    if ($isIrrigationCustomer) {
                        return;
                    }

                    // Check month sequence
                    if ($lastMonth) {
                        $lastMonthInt = (int) $lastMonth;
                        $expectedMonth = $lastMonthInt == 12 ? 1 : $lastMonthInt + 1;

                        if ($month < $lastMonthInt) {
                            $fail('Cannot create a record for a month earlier than the latest record (' . $lastMonth . ').');
                        }

                        if ($month != $expectedMonth) {
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
                            $fail('The reading date cannot be a future date.');
                        }
                    } catch (\Exception $e) {
                        $fail('The reading date is not a valid BS date.');
                    }
                },
            ],
            'reading_date_in_ad' => [
                'required',
                'date',
                'before_or_equal:today'
            ],
            'current_unit' => [
                'required',
                'numeric',
                'min:0',
                'max:100000',
            ],
            'unit_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.9999'],
            'minimum_demand' => ['required', 'numeric', 'min:0'],
            'subsidy_charge' => ['required', 'numeric', 'min:0'],
            'service_charge' => ['required', 'numeric', 'min:0'],
            'other_charge' => ['required', 'numeric', 'min:0'],
            'fine_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'total_charge' => ['required', 'numeric', 'min:1', 'max:999999999999.99'],
        ];

        $validator = Validator::make($row, $rules);

        $validator->after(function ($v) use ($row) {
            // Check if total charge matches sum of charges
            $unitAmount = $row['unit_amount'] ?? 0;
            $minDemand = $row['minimum_demand'] ?? 0;
            $subsidy = $row['subsidy_charge'] ?? 0;
            $service = $row['service_charge'] ?? 0;
            $other = $row['other_charge'] ?? 0;
            $fineAmount = $row['fine_amount'] ?? 0;

            $expectedSubtotal = $unitAmount + $minDemand + $subsidy + $service + $other;
            $expectedTotal = $expectedSubtotal + $fineAmount;

            if (isset($row['total_charge'])) {
                if (abs($row['total_charge'] - $expectedTotal) > 0.1) {
                    $v->errors()->add(
                        'total_charge',
                        "Invalid total_charge: {$row['total_charge']} must equal {$expectedTotal} (unit_amount + minimum_demand + subsidy_charge + service_charge + other_charge + fine_amount)."
                    );
                }
            }

           
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    protected function validateMahsulReceiptRow(array $row)
    {
        $rules = [
            'date_in_bs' => [
                'required',
                'string',
                'max:10',
                'regex:/^\d{4}-\d{2}-\d{2}$/',
                function ($attribute, $value, $fail) {
                    try {
                        $adDate = NepaliCalendar::bsToAd($value);
                        $today = date('Y-m-d');
                        if ($adDate > $today) {
                            $fail('The date cannot be a future date.');
                        }
                    } catch (\Exception $e) {
                        $fail('The date is not a valid BS date.');
                    }
                },
            ],
            'date_in_ad' => [
                'required',
                'date',
                'before_or_equal:today'
            ],
            'unit_amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'rebate_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'demand_charge' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'subsidy_charge' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'service_charge' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'other_charge' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'total_due_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'advance_payment' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'paid_amount' => [
                'required',
                'numeric',
                'min:0.0001',
                'max:999999999999.99',
            ],
            
        ];

        $validator = Validator::make($row, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function processCustomerTransactionsForVoucher($receiptId, $voucherNo)
    {
        $lines = [];

        // Handle advance used amount (charge_type 9, direction DR)
        $advanceUsedAmount = \App\Models\CustomerTransaction::where('receipt_id', $receiptId)
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
        $chargeAmounts = \App\Models\CustomerTransaction::where('receipt_id', $receiptId)->get();

        // Customer transaction charge_type to account_head mapping
        $chargeTypeAccountMap = [
            11 => 20, // blacklist_charge
            3 => 13,  // fine_amount
            1 => 14,  // demand_charge
            5 => 15,  // other_charge
            4 => 16,  // subsidy_charge
            2 => 17,  // service_charge
            6 => 11,  // unit_amount (energy)
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
}