<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\BlacklistPeriod;
use App\Services\BlacklistService;
use App\Models\MahasulReceiptEntry;
use App\Models\MeterIssue;
use App\Models\MeterReadingEntry;
use App\Helpers\NepaliCalendar;
use Carbon\Carbon;
use Doctrine\DBAL\Query\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BlacklistPeriodController extends Controller
{

    public function get(Request $request)
    {
        try {
            $record = BlacklistPeriod::first();

            if (!$record) {
                $record = BlacklistPeriod::create([
                    'days' => 30,
                    'amount' => 0.00,
                    'is_applied' => true,
                ]);
            }

            return response()->json([
                'message' => 'Blacklist period retrieved successfully.',
                'data' => $record,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve blacklist period.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function update(Request $request)
    {
        try {
            $validated = $request->validate([
                'days' => 'required|numeric|min:1',
                'amount' => 'required|numeric|min:0|max:9999999999.99',
                'is_applied' => 'required|boolean',
            ]);

            $record = BlacklistPeriod::first();

            if (!$record) {
                $record = BlacklistPeriod::create($validated);
            } else {
                $record->update($validated);
            }

            return response()->json([
                'message' => 'Blacklist period updated successfully.',
                'data' => $record,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update blacklist period.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if a specific customer is blacklisted.
     */




    public function getBlacklistedCustomers()
    {
        try {
            $blacklistPeriod = BlacklistPeriod::first();
            if (!$blacklistPeriod) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No blacklist period configured.'
                ], 404);
            }

            $blacklistDays = (int) $blacklistPeriod->days;
            $blacklistAmount = (float) ($blacklistPeriod->amount ?? 0);
            $applyStatus = (bool) ($blacklistPeriod->apply_status ?? false);

            $today = Carbon::now();
            $meterIssues = MeterIssue::pluck('meter_no', 'member_entry_id');

            $entries = MeterReadingEntry::with('member')->get();
            $grouped = $entries->groupBy('member_entry_id');

            $blacklisted = [];

            foreach ($grouped as $customerId => $customerReadings) {
                $customerInfo = $customerReadings->first();
                $unpaidMonths = [];

                foreach ($customerReadings as $entry) {
                    $readingDateBs = $entry->reading_date;
                    $readingDateAd = $entry->reading_date_in_ad;

                    $dueDateBs = $this->addDaysToNepaliDate($readingDateBs, $blacklistDays);
                    $dueDateAd = $this->bsToAd($dueDateBs);

                    // Skip if still within blacklist period
                    if ($today->lte($dueDateAd)) {
                        continue;
                    }

                    // Get payments that cover this reading
                    $payments = MahasulReceiptEntry::where('member_entry_id', $entry->customer_id)
                        ->where('is_cancel', 0)
                        ->where('last_mahasul_date', '>=', $entry->reading_date_in_ad)
                        ->get();

                    $totalPaid = $payments->sum('paid_amount');

                    // Consider unpaid only if totalPaid < total_charge of the reading
                    if ($totalPaid < $entry->total_charge) {
                        $unpaidMonths[] = [
                            'reading_month' => $entry->reading_month,
                            'reading_date_bs' => $readingDateBs,
                            'reading_date_in_ad' => $readingDateAd,
                            'due_date_bs' => $dueDateBs,
                            'due_date_ad' => $dueDateAd,
                            'last_payment_date' => $payments->max('date_in_ad') ?? null,
                        ];
                    }
                }

                if (!empty($unpaidMonths) && $applyStatus) {
                    $blacklisted[] = [
                        'member_entry_id' => $customerId,
                        'customer_name_en' => $customerInfo->member->customer_name_en ?? null,
                        'customer_name_np' => $customerInfo->member->customer_name_np ?? null,
                        'meter_no' => $meterIssues[$customerId] ?? null,
                        'unpaid_months_count' => count($unpaidMonths),
                        'unpaid_months' => $unpaidMonths,
                        'blacklist_amount' => $blacklistAmount,
                    ];
                }
            }

            return response()->json([
                'status' => 'ok',
                'blacklisted_count' => count($blacklisted),
                'data' => $blacklisted,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch blacklisted customers.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





    /**
     * Add days to a Nepali date (BS)
     */





    public function toggleApplyStatus(Request $request)
    {


        try {
            $record = BlacklistPeriod::first();

            if (!$record) {
                $record = BlacklistPeriod::create([
                    'days' => 30,
                    'amount' => 0.00,
                    'apply_status' => true,
                ]);
            } else {
                // Toggle the apply_status
                $record->apply_status = !$record->apply_status;
                $record->save();
            }


            return response()->json([
                'apply_status' => $record->apply_status,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'apply_status' => null,
            ], 500);
        }
    }
}
