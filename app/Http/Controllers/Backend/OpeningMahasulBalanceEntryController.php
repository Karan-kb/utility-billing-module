<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\TenantRuntimeHelper;
use App\Http\Controllers\Controller;
use App\Models\AdvancePayment;
use App\Models\CustomerTransaction;
use App\Models\MeterIssue;
use App\Models\MeterReadingEntry;
use App\Models\OpeningMahasulBalanceEntry;
use App\Models\TariffSetup;
use App\Services\CustomerTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\MemberInfoFromMeterIssueService;

use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class OpeningMahasulBalanceEntryController extends Controller
{
    public function searchCustomerDetails(Request $request)
    {
        try {
            $searchTerm = $request->input('search');

            $customers = MeterIssue::where('is_active', 1)
                ->where(function ($query) use ($searchTerm) {
                    $query->whereHas('memberEntry', function ($q) use ($searchTerm) {
                        $q->where('customer_name_en', 'like', "%{$searchTerm}%")
                            ->orWhere('customer_name_np', 'like', "%{$searchTerm}%")
                            ->orWhere('member_no', 'like', "%{$searchTerm}%");
                    });
                })
                // ->whereDoesntHave('openingMahasulBalanceEntry')
                // ->whereDoesntHave('openingMeterDepositEntry')
                ->with([
                    'memberEntry' => function ($query) {
                        $query->where('is_active', 1)
                            ->select('id', 'member_no', 'customer_name_en', 'customer_name_np', 'pan_no');
                    }
                ])
                ->get();

            if ($customers->isEmpty()) {
                return response()->json([
                    'message' => 'No active, non-deleted records found for the provided search term.',
                    'data' => [],
                ], 200);
            }

            $result = $customers->map(function ($customer) {
            $data = [
                'meter_issue_id' => $customer->id,
                'member_no' => $customer->memberEntry?->member_no,
                'customer_name_en' => $customer->memberEntry?->customer_name_en,
                'customer_name_np' => $customer->memberEntry?->customer_name_np,
                'meter_no' => $customer->meter_no,
                'pan_no' => $customer->memberEntry?->pan_no,
                'meter_start_no' => $customer->meter_start_no,
            ];

            // Only include phase/capacity/purpose for Bidut
            if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
                $data['demand_phase'] = $customer->phase_id;
                $data['demand_capacity'] = $customer->capacity_id;
                $data['purpose_type'] = $customer->purpose_id;
            }

            return $data;
        });

            return response()->json([
                'message' => 'Customer details retrieved successfully',
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while searching customer details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    public function createOpeningMahasulBalanceEntry(Request $request)
    {
        $connection = (new OpeningMahasulBalanceEntry)->getConnectionName() ?: config('database.default');
        $openingValidator = app(\App\Services\OpeningEntryValidationService::class);

        try {
            $meterIssueId = $request->input('meter_issue_id');
            if ($meterIssueId) {
                $openingValidator->validateOpening($meterIssueId);
            }

            return DB::connection($connection)->transaction(function () use ($request) {

                $validated = $request->validate([
                    'reading_date_in_bs' => [
                        'required',
                        'string',
                        'max:10',
                        'regex:/^\d{4}-\d{2}-\d{2}$/',
                        function ($attr, $val, $fail) {
                            try {
                                $adDate = \App\Helpers\NepaliCalendar::bsToAd($val);
                                if ($adDate > date('Y-m-d'))
                                    $fail("The {$attr} cannot be a future date.");
                            } catch (\Exception $e) {
                                $fail("The {$attr} is not a valid BS date.");
                            }
                        }
                    ],
                    'reading_date_in_ad' => [
                        'required',
                        'string',
                        'max:10',
                        'regex:/^\d{4}-\d{2}-\d{2}$/',
                        function ($attr, $val, $fail) {
                            if ($val > date('Y-m-d'))
                                $fail("The {$attr} cannot be a future date.");
                        }
                    ],
                    'meter_issue_id' => [
                        'required',
                        'integer',
                        function ($attr, $val, $fail) use ($request) {
                            $type = $request->input('type', 0);

                            // Check if meter_issue exists
                            $exists = MeterIssue::on('tenant')->where('id', $val)->where('is_active', 1)->exists();
                            if (!$exists)
                                $fail('The selected meter issue does not exist.');

                            // Check exclusivity: cannot exist in the other table
                            if ($type == 0) {
                                // creating MeterReadingEntry -> check AdvancePayment
                                $existsInAdvance = AdvancePayment::where('meter_issue_id', $val)
                                    ->whereNull('deleted_at')
                                    ->where('type', 1)
                                    ->exists();
                                if ($existsInAdvance) {
                                    $fail('This meter already has an Advance Payment entry.');
                                }

                                // check existing MeterReadingEntry
                                $hasOpening = MeterReadingEntry::withoutTrashed()
                                    ->where('meter_issue_id', $val)
                                    ->where('entry_type', 2)
                                    ->exists();
                                if ($hasOpening)
                                    $fail('Opening mahasul already exists for this meter.');

                            } else {
                                // creating AdvancePayment -> check MeterReadingEntry
                                $existsInMeter = MeterReadingEntry::withoutTrashed()
                                    ->where('meter_issue_id', $val)
                                    ->where('entry_type', 2)
                                    ->exists();
                                if ($existsInMeter) {
                                    $fail('This meter already has an Opening Mahasul entry.');
                                }

                                // check existing AdvancePayment
                                $hasAdvance = AdvancePayment::where('meter_issue_id', $val)
                                    ->whereNull('deleted_at')
                                    ->where('type', 1)
                                    ->exists();
                                if ($hasAdvance)
                                    $fail('Advance Payment already exists for this meter.');
                            }
                        }
                    ],
                    'unit_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
                    'demand_charge' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'subsidy_charge' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'service_charge' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'other_charge' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'fine_charge' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'total_charge' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'type' => ['nullable', 'integer', 'in:0,1'], // type input
                ]);

                $type = $validated['type'] ?? 0; // default 0
                $meterIssue = MeterIssue::findOrFail($validated['meter_issue_id']);
                $memberEntryId = $meterIssue->member_entry_id;

                if ($type === 0) {
                    // --- MeterReadingEntry ---
                    $entry = MeterReadingEntry::create([
                        'entry_type' => 2,
                        'reading_date_in_bs' => $validated['reading_date_in_bs'],
                        'reading_date_in_ad' => $validated['reading_date_in_ad'],
                        'meter_issue_id' => $validated['meter_issue_id'],
                        'unit_amount' => $validated['unit_amount'],
                        'fine_amount' => $validated['fine_charge'] ?? 0,
                        'total_charge' => $validated['total_charge'],
                        'sub_total_charge' => $validated['total_charge'] - $validated['fine_charge'],
                        'status' => 0,
                    ]);

                    $charges = [
                        'unit_amount' => $validated['unit_amount'],
                        'subsidy_charge' => $validated['subsidy_charge'] ?? 0,
                        'service_charge' => $validated['service_charge'] ?? 0,
                        'other_charge' => $validated['other_charge'] ?? 0,
                        'fine_charge' => $validated['fine_charge'] ?? 0,
                        'demand_charge' => $validated['demand_charge'] ?? 0,
                    ];

                    // Create CustomerTransaction like before
                    app(\App\Services\CustomerTransactionService::class)
                        ->createForOpeningMahasul($memberEntryId, $entry->id, $charges, $validated['reading_date_in_bs']);

                    $responseData = $entry->toArray();

                } else {
                    // --- AdvancePayment ---
                    $entry = AdvancePayment::create([
                        'type' => 1,
                        'voucher_no' => null,
                        'date_in_bs' => $validated['reading_date_in_bs'],
                        'date_in_ad' => $validated['reading_date_in_ad'],
                        'meter_issue_id' => $validated['meter_issue_id'],
                        'amount' => $validated['total_charge'] ?? $validated['unit_amount'],
                        'is_cancel' => 0,
                    ]);

                    // Create simplified CustomerTransaction
                    \App\Models\CustomerTransaction::create([
                        'member_entry_id' => $memberEntryId,
                        'transaction_date' => $validated['reading_date_in_ad'],
                        'transaction_type' => 6, // Advance Payment
                        'charge_type' => 9,
                        'amount' => $validated['total_charge'] ?? $validated['unit_amount'],
                        'direction' => 'CR',
                        'reference_id' => $entry->id,
                    ]);

                    $responseData = $entry->toArray();
                }

                return response()->json([
                    'message' => 'Opening Mahasul Entry created successfully!',
                    'data' => $responseData
                ], 201);

            }, 5);

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();
            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors
            ], 422);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'message' => 'An error occurred while creating the opening mahasul balance entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function editOpeningMahasulBalanceEntry(Request $request, $id)
    {
        $connection = (new OpeningMahasulBalanceEntry)->getConnectionName() ?: config('database.default');

        try {
            return DB::connection($connection)->transaction(function () use ($request, $id) {

                $type = $request->input('type', 0); // from query or body
                $validated = $request->validate([
                    'reading_date_in_bs' => ['required', 'string', 'max:10', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
                    'reading_date_in_ad' => ['required', 'string', 'max:10', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
                    'meter_issue_id' => ['required', 'integer'],
                    'unit_amount' => ['nullable', 'numeric', 'min:0'],
                    'demand_charge' => ['nullable', 'numeric', 'min:0'],
                    'subsidy_charge' => ['nullable', 'numeric', 'min:0'],
                    'service_charge' => ['nullable', 'numeric', 'min:0'],
                    'other_charge' => ['nullable', 'numeric', 'min:0'],
                    'fine_charge' => ['nullable', 'numeric', 'min:0'],
                    'total_charge' => ['nullable', 'numeric', 'min:0'],
                ]);

                $meterIssue = MeterIssue::findOrFail($validated['meter_issue_id']);

                if ($type == 0) {
                    $entry = MeterReadingEntry::withoutTrashed()->findOrFail($id);

                    $entry->update([
                        'reading_date_in_bs' => $validated['reading_date_in_bs'],
                        'reading_date_in_ad' => $validated['reading_date_in_ad'],
                        'meter_issue_id' => $validated['meter_issue_id'],
                        'unit_amount' => $validated['unit_amount'],
                        'fine_amount' => $validated['fine_charge'] ?? 0,
                        'total_charge' => $validated['total_charge'],
                    ]);

                    $charges = [
                        'unit_amount' => $validated['unit_amount'] ?? 0,
                        'subsidy_charge' => $validated['subsidy_charge'] ?? 0,
                        'service_charge' => $validated['service_charge'] ?? 0,
                        'other_charge' => $validated['other_charge'] ?? 0,
                        'fine_charge' => $validated['fine_charge'] ?? 0,
                        'demand_charge' => $validated['demand_charge'] ?? 0,
                    ];

                    CustomerTransaction::where('reference_id', $entry->id)
                        ->where('transaction_type', 5)
                        ->delete();

                    app(CustomerTransactionService::class)
                        ->createForOpeningMahasul(
                            $meterIssue->member_entry_id,
                            $entry->id,
                            $charges,
                            $validated['reading_date_in_bs']
                        );

                    return ['message' => 'Opening Mahasul updated successfully', 'data' => $entry];

                } else {
                    $entry = AdvancePayment::findOrFail($id);

                    $entry->update([
                        'date_in_bs' => $validated['reading_date_in_bs'],
                        'date_in_ad' => $validated['reading_date_in_ad'],
                        'meter_issue_id' => $validated['meter_issue_id'],
                        'amount' => $validated['total_charge'] ?? $validated['unit_amount'],
                    ]);

                    CustomerTransaction::where('reference_id', $entry->id)
                        ->where('transaction_type', 6)
                        ->update([
                            'member_entry_id' => $meterIssue->member_entry_id,
                            'transaction_date' => $validated['reading_date_in_ad'],
                            'amount' => $validated['total_charge'] ?? $validated['unit_amount'],
                        ]);

                    return ['message' => 'Advance payment updated successfully', 'data' => $entry];
                }
            });

        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function listOpeningMahasulBalanceEntries(Request $request)
    {
        try {

            $memberService = new MemberInfoFromMeterIssueService();

            /*
            |--------------------------------------------------------------------------
            | 1. Meter Opening Mahasul Entries
            |--------------------------------------------------------------------------
            */
            $meterEntries = MeterReadingEntry::withoutTrashed()
                ->where('entry_type', 2)
                ->get()
                ->map(function ($entry) use ($memberService) {

                    $memberInfo = $memberService->getMemberInfo($entry->meter_issue_id);

                    return [
                        'reference_id' => $entry->id,
                        'type' => 0,
                        'reading_date_in_bs' => $entry->reading_date_in_bs,
                        'reading_date_in_ad' => $entry->reading_date_in_ad,
                        'meter_no' => $memberInfo['meter_no'] ?? null,
                        'member_no' => $memberInfo['member_no'] ?? null,
                        'customer_name_en' => $memberInfo['customer_name_en'] ?? null,
                        'customer_name_np' => $memberInfo['customer_name_np'] ?? null,
                        'total_charge' => $entry->total_charge,
                        'created_at' => $entry->created_at,
                        'updated_at' => $entry->updated_at,
                    ];
                })
                ->toBase();


            /*
            |--------------------------------------------------------------------------
            | 2. Advance Opening Entries
            |--------------------------------------------------------------------------
            */
            $advanceEntries = AdvancePayment::where('type', 1)
                ->whereNull('deleted_at')
                ->get()
                ->map(function ($entry) use ($memberService) {

                    $memberInfo = $memberService->getMemberInfo($entry->meter_issue_id);

                    return [
                        'reference_id' => $entry->id,
                        'type' => 1,
                        'reading_date_in_bs' => $entry->date_in_bs,
                        'reading_date_in_ad' => $entry->date_in_ad,
                        'meter_no' => $memberInfo['meter_no'] ?? null,
                        'member_no' => $memberInfo['member_no'] ?? null,
                        'customer_name_en' => $memberInfo['customer_name_en'] ?? null,
                        'customer_name_np' => $memberInfo['customer_name_np'] ?? null,
                        'total_charge' => $entry->amount,
                        'created_at' => $entry->created_at,
                        'updated_at' => $entry->updated_at,
                    ];
                })
                ->toBase();


            /*
            |--------------------------------------------------------------------------
            | 3. Merge Safely (Now Plain Collections)
            |--------------------------------------------------------------------------
            */
            $allEntries = collect()
                ->merge($meterEntries)
                ->merge($advanceEntries)
                ->sortByDesc('created_at')
                ->values();


            /*
            |--------------------------------------------------------------------------
            | 4. Add Sequential ID
            |--------------------------------------------------------------------------
            */
            $allEntries = $allEntries->values()->map(function ($item, $index) {
                $item['id'] = $index + 1;
                return $item;
            });


            /*
            |--------------------------------------------------------------------------
            | 5. Pagination
            |--------------------------------------------------------------------------
            */
            $perPage = 10;
            $page = LengthAwarePaginator::resolveCurrentPage();

            $paginated = new LengthAwarePaginator(
                $allEntries->forPage($page, $perPage)->values(),
                $allEntries->count(),
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return response()->json($paginated, 200);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'An error occurred while retrieving opening mahasul balance entries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getById(Request $request, $id)
    {
        try {
            $type = $request->query('type', 0); // default 0
            $memberService = new MemberInfoFromMeterIssueService();

            if ($type == 0) {
                $entry = MeterReadingEntry::withoutTrashed()
                    ->with(['memberOpening:id,member_no,customer_name_en,customer_name_np', 'meterIssueNumber:id,meter_no', 'customerTransaction'])
                    ->where('entry_type', 2)
                    ->findOrFail($id);

                $transactions = $entry->customerTransaction;

                $charges = [
                    'unit_amount' => $transactions->where('charge_type', 6)->sum('amount'),
                    'demand_charge' => $transactions->where('charge_type', 1)->sum('amount'),
                    'service_charge' => $transactions->where('charge_type', 2)->sum('amount'),
                    'fine_charge' => $transactions->where('charge_type', 3)->sum('amount'),
                    'subsidy_charge' => $transactions->where('charge_type', 4)->sum('amount'),
                    'other_charge' => $transactions->where('charge_type', 5)->sum('amount'),
                    'discount_amount' => $transactions->where('charge_type', 8)->sum('amount'),
                ];

                $memberInfo = $memberService->getMemberInfo($entry->meter_issue_id);

                $data = [
                    'reference_id' => $entry->id,
                    'type' => 0,
                    'reading_date_in_bs' => $entry->reading_date_in_bs,
                    'reading_date_in_ad' => $entry->reading_date_in_ad,
                    'meter_no' => $memberInfo['meter_no'] ?? null,
                    'meter_issue_id' => $entry->meter_issue_id,
                    'member_no' => $memberInfo['member_no'] ?? null,
                    'customer_name_en' => $memberInfo['customer_name_en'] ?? null,
                    'customer_name_np' => $memberInfo['customer_name_np'] ?? null,
                    'unit_amount' => $charges['unit_amount'],
                    'demand_charge' => $charges['demand_charge'],
                    'service_charge' => $charges['service_charge'],
                    'other_charge' => $charges['other_charge'],
                    'fine_charge' => $charges['fine_charge'],
                    'subsidy_charge' => $charges['subsidy_charge'],
                    'total_charge' => $entry->total_charge,
                    'created_at' => $entry->created_at,
                    'updated_at' => $entry->updated_at,
                ];

            } else {
                $entry = AdvancePayment::where('type', 1)
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->firstOrFail();

                $memberInfo = $memberService->getMemberInfo($entry->meter_issue_id);

                $data = [
                    'reference_id' => $entry->id,
                    'type' => 1,
                    'reading_date_in_bs' => $entry->date_in_bs,
                    'reading_date_in_ad' => $entry->date_in_ad,
                    'meter_issue_id' => $entry->meter_issue_id,
                    'meter_no' => $memberInfo['meter_no'] ?? null,
                    'member_no' => $memberInfo['member_no'] ?? null,
                    'customer_name_en' => $memberInfo['customer_name_en'] ?? null,
                    'customer_name_np' => $memberInfo['customer_name_np'] ?? null,
                    'total_charge' => $entry->amount,
                    'created_at' => $entry->created_at,
                    'updated_at' => $entry->updated_at,
                ];
            }

            return response()->json(['opening_mahasul_balance_entry' => $data], 200);

        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Entry not found or deleted.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error fetching entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteOpeningMahasulBalanceEntry(Request $request, $id)
    {
        $type = $request->query('type', 0); // default type 0

        try {
            if ($type == 0) {
                // --- Opening Mahasul ---
                $entry = MeterReadingEntry::withoutTrashed()->findOrFail($id);

                $entry->delete();

                // Remove related transactions
                CustomerTransaction::where('reference_id', $id)
                    ->where('transaction_type', 5)
                    ->delete();

                return response()->json([
                    'message' => 'Opening Mahasul balance entry soft deleted successfully!',
                    'reference_id' => $entry->id,
                    'type' => 0
                ], 200);

            } else {
                // --- Advance Payment ---
                $entry = AdvancePayment::where('type', 1)
                    ->where('id', $id)
                    ->whereNull('deleted_at')
                    ->firstOrFail();

                $entry->delete();

                // Remove related transactions
                CustomerTransaction::where('reference_id', $id)
                    ->where('transaction_type', 6)
                    ->delete();

                return response()->json([
                    'message' => 'Advance Payment entry soft deleted successfully!',
                    'reference_id' => $entry->id,
                    'type' => 1
                ], 200);
            }
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Entry not found or already deleted.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the entry.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
