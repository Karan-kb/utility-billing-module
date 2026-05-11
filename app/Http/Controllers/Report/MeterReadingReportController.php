<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\MemberEntry;
use App\Models\MasterSetup;
use App\Models\MeterIssue;
use App\Models\Fine;
use App\Models\MeterReadingEntry;
use App\Models\OpeningMeterDepositEntry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MeterReadingReportController extends Controller
{

    public function readingReport(Request $request)
    {
        try {
            $validated = $request->validate([
                'fiscal_year_id' => 'nullable|integer',
                'month' => 'nullable|string',
                'member_no' => 'nullable|integer',
                    'is_synced' => 'nullable|integer|in:0,1',
                     'reader_id' => 'nullable|integer',

            ]);
           
            $query = MeterReadingEntry::withoutTrashed()
            ->where('entry_type', 1)
                ->with([
                    'meterIssue:id,member_entry_id,meter_no',
                    'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np',
                    'customerTransaction'
                ])
                ->select('meter_reading_entries.*')
                ->join('meter_issues', 'meter_reading_entries.meter_issue_id', '=', 'meter_issues.id')
                ->join('member_entries', function ($join) {
                    $join->on('meter_issues.member_entry_id', '=', 'member_entries.id')
                        ->where('member_entries.is_active', 1)
                        ->whereNull('member_entries.deleted_at');
                })
                
                ->orderBy('meter_reading_entries.reading_month_in_bs')
                ->orderBy('meter_reading_entries.created_at');
                if ($request->filled('is_synced')) {
                    $query->where('meter_reading_entries.is_synced', (int) $request->is_synced);
                }
                if (!empty($validated['reader_id'])) {
                    $query->where('meter_reading_entries.reader_id', $validated['reader_id']);
                }
       

            if (!empty($validated['fiscal_year_id'])) {
                $query->where('meter_reading_entries.fiscal_year_id', $validated['fiscal_year_id']);
            }

            if (!empty($validated['month'])) {
                $query->where('meter_reading_entries.reading_month_in_bs', $validated['month']);
            }

            if (!empty($validated['member_no'])) {
                $query->whereHas('meterIssue.memberEntry', function ($q) use ($validated) {
                    $q->where('member_no', $validated['member_no']);
                });
            }

      
            $totalQuery = clone $query;

            
            $readings = $query->paginate(100);

            if ($readings->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No meter readings found for the given filters',
                    'data' => [],
                    'totals' => [
                        'total_readings' => 0,
                        'total_units' => 0,
                        'total_unit_amount' => 0,
                        'total_fine_amount' => 0,
                        'total_demand_charge' => 0,
                        'total_service_charge' => 0,
                        'total_subsidy_charge' => 0,
                        'total_other_charge' => 0,
                        'grand_total_charge' => 0,
                        'total_fine_charge' => 0,
                        'members_count' => 0,
                    ],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 10,
                        'total' => 0,
                    ]
                ]);
            }

          

            $meterIssueIds = $readings->pluck('meter_issue_id')->unique();

            $allMeterReadings = MeterReadingEntry::whereIn('meter_issue_id', $meterIssueIds)
                ->where('status', '!=', 3)
                ->orderBy('reading_date_in_ad', 'asc')
                ->get()
                ->groupBy('meter_issue_id');

            $readingIds = $readings->pluck('id');

            $fines = Fine::whereIn('meter_reading_entry_id', $readingIds)
                ->where('fine_type', 1)
                ->where('status', 1)
                ->get()
                ->groupBy('meter_reading_entry_id');

            $detailedData = $readings->getCollection()->map(function ($reading) use ($fines, $allMeterReadings) {

                $previousUnit = 0;

                if (isset($allMeterReadings[$reading->meter_issue_id])) {
                    $previousReading = $allMeterReadings[$reading->meter_issue_id]
                        ->where('reading_date_in_ad', '<', $reading->reading_date_in_ad)
                        ->sortByDesc('reading_date_in_ad')
                        ->first();

                    if ($previousReading) {
                        $previousUnit = $previousReading->current_unit;
                    }
                }

                $readingFines = $fines->get($reading->id, collect());

                $fineCharge = $readingFines->sum('amount');

                return [
                    'reading_id' => $reading->id,
                    'fiscal_year_id' => $reading->fiscal_year_id,
                    'reading_month_in_bs' => $reading->reading_month_in_bs,
                    'reading_date_in_ad' => $reading->reading_date_in_ad,
                    'reading_date_in_bs' => $reading->reading_date_in_bs,
                    'meter_issue_id' => $reading->meter_issue_id,
                    'meter_no' => $reading->meterIssue->meter_no ?? 'N/A',
                    'member_no' => $reading->meterIssue->memberEntry->member_no ?? 'N/A',
                    'customer_name_en' => $reading->meterIssue->memberEntry->customer_name_en ?? 'N/A',
                    'customer_name_np' => $reading->meterIssue->memberEntry->customer_name_np ?? 'N/A',
                    'reader_name' => $reading->reader?->name ?? 'N/A',
                    'previous_unit' => $previousUnit,
                    'current_unit' => $reading->current_unit,
                    'total_unit' => $reading->total_unit,
                    'unit_amount' => $reading->unit_amount,

                    'demand_charge' => $reading->customerTransaction->where('charge_type', 1)->where('transaction_type', 1)->sum('amount'),
                    'service_charge' => $reading->customerTransaction->where('charge_type', 2)->where('transaction_type', 1)->sum('amount'),
                    'subsidy_charge' => $reading->customerTransaction->where('charge_type', 4)->where('transaction_type', 1)->sum('amount'),
                    'other_charge' => $reading->customerTransaction->where('charge_type', 5)->where('transaction_type', 1)->sum('amount'),

                    'fine_amount' => $reading->fine_amount,
                    'fine_charge' => $fineCharge,
                    'total_charge' => $reading->total_charge,
                    'is_synced' => $reading->is_synced,
                    'status' => $reading->status,
                    'entry_type' => $reading->entry_type,
                    'created_at' => $reading->created_at,
                ];
            });

            

            $allReadings = $totalQuery->with('customerTransaction')->get();

            $totals = [
                'total_readings' => $allReadings->count(),
                'total_units' => $allReadings->sum('total_unit'),
                'total_unit_amount' => $allReadings->sum('unit_amount'),
                'total_fine_amount' => $allReadings->sum('fine_amount'),
                'total_demand_charge' => $allReadings->sum(fn($r) => $r->customerTransaction->where('charge_type', 1)->where('transaction_type', 1)->sum('amount')),
                'total_service_charge' => $allReadings->sum(fn($r) => $r->customerTransaction->where('charge_type', 2)->where('transaction_type', 1)->sum('amount')),
                'total_subsidy_charge' => $allReadings->sum(fn($r) => $r->customerTransaction->where('charge_type', 4)->where('transaction_type', 1)->sum('amount')),
                'total_other_charge' => $allReadings->sum(fn($r) => $r->customerTransaction->where('charge_type', 5)->where('transaction_type', 1)->sum('amount')),
                'grand_total_charge' => $allReadings->sum('total_charge'),
                'total_fine_charge' => $allReadings->sum('fine_amount'),
                'members_count' => $allReadings->pluck('meterIssue.memberEntry.member_no')->unique()->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $detailedData,
                'totals' => $totals,
                'pagination' => [
                    'current_page' => $readings->currentPage(),
                    'last_page' => $readings->lastPage(),
                    'per_page' => $readings->perPage(),
                    'total' => $readings->total(),
                    'from' => $readings->firstItem(),
                    'to' => $readings->lastItem(),
                    'next_page_url' => $readings->nextPageUrl(),
                    'prev_page_url' => $readings->previousPageUrl(),
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed !',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Meter reading report error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred !',
                'error' => $e->getMessage()
            ], 500);
        }
    }

public function readingReportAll(Request $request)
{
    try {
        $validated = $request->validate([
            'fiscal_year_id' => 'nullable|integer',
            'month' => 'nullable|string',
            'member_no' => 'nullable|integer',
            'is_synced' => 'nullable|integer|in:0,1',
            'reader_id' => 'nullable|integer',
        ]);

        $query = MeterReadingEntry::withoutTrashed()
            ->where('entry_type', 1)
            ->with([
                'meterIssue:id,member_entry_id,meter_no',
                'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np',
                'customerTransaction',
                'reader'
            ])
            ->select('meter_reading_entries.*')
            ->join('meter_issues', 'meter_reading_entries.meter_issue_id', '=', 'meter_issues.id')
            ->join('member_entries', function ($join) {
                $join->on('meter_issues.member_entry_id', '=', 'member_entries.id')
                    ->where('member_entries.is_active', 1)
                    ->whereNull('member_entries.deleted_at');
            })
            ->orderBy('meter_reading_entries.reading_month_in_bs')
            ->orderBy('meter_reading_entries.created_at');

        // Filters
        if ($request->filled('is_synced')) {
            $query->where('meter_reading_entries.is_synced', (int) $request->is_synced);
        }

        if (!empty($validated['reader_id'])) {
            $query->where('meter_reading_entries.reader_id', $validated['reader_id']);
        }

        if (!empty($validated['fiscal_year_id'])) {
            $query->where('meter_reading_entries.fiscal_year_id', $validated['fiscal_year_id']);
        }

        if (!empty($validated['month'])) {
            $query->where('meter_reading_entries.reading_month_in_bs', $validated['month']);
        }

        if (!empty($validated['member_no'])) {
            $query->whereHas('meterIssue.memberEntry', function ($q) use ($validated) {
                $q->where('member_no', $validated['member_no']);
            });
        }

        // 👉 NO PAGINATION HERE
        $readings = $query->get();

        if ($readings->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No meter readings found',
                'data' => [],
                'totals' => []
            ]);
        }

        // Prepare supporting data
        $meterIssueIds = $readings->pluck('meter_issue_id')->unique();

        $allMeterReadings = MeterReadingEntry::whereIn('meter_issue_id', $meterIssueIds)
            ->where('status', '!=', 3)
            ->orderBy('reading_date_in_ad', 'asc')
            ->get()
            ->groupBy('meter_issue_id');

        $readingIds = $readings->pluck('id');

        $fines = Fine::whereIn('meter_reading_entry_id', $readingIds)
            ->where('fine_type', 1)
            ->where('status', 1)
            ->get()
            ->groupBy('meter_reading_entry_id');

        // Map data
        $detailedData = $readings->map(function ($reading) use ($fines, $allMeterReadings) {

            $previousUnit = 0;

            if (isset($allMeterReadings[$reading->meter_issue_id])) {
                $previousReading = $allMeterReadings[$reading->meter_issue_id]
                    ->where('reading_date_in_ad', '<', $reading->reading_date_in_ad)
                    ->sortByDesc('reading_date_in_ad')
                    ->first();

                if ($previousReading) {
                    $previousUnit = $previousReading->current_unit;
                }
            }

            $fineCharge = $fines->get($reading->id, collect())->sum('amount');

            return [
                'reading_id' => $reading->id,
                'fiscal_year_id' => $reading->fiscal_year_id,
                'reading_month_in_bs' => $reading->reading_month_in_bs,
                'reading_date_in_ad' => $reading->reading_date_in_ad,
                'meter_no' => $reading->meterIssue->meter_no ?? 'N/A',
                'member_no' => $reading->meterIssue->memberEntry->member_no ?? 'N/A',
                'customer_name_en' => $reading->meterIssue->memberEntry->customer_name_en ?? 'N/A',
                'customer_name_np' => $reading->meterIssue->memberEntry->customer_name_np ?? 'N/A',
                'reader_name' => $reading->reader?->name ?? 'N/A',

                'previous_unit' => $previousUnit,
                'current_unit' => $reading->current_unit,
                'total_unit' => $reading->total_unit,
                'unit_amount' => $reading->unit_amount,

                'demand_charge' => $reading->customerTransaction->where('charge_type', 1)->where('transaction_type', 1)->sum('amount'),
                'service_charge' => $reading->customerTransaction->where('charge_type', 2)->where('transaction_type', 1)->sum('amount'),
                'subsidy_charge' => $reading->customerTransaction->where('charge_type', 4)->where('transaction_type', 1)->sum('amount'),
                'other_charge' => $reading->customerTransaction->where('charge_type', 5)->where('transaction_type', 1)->sum('amount'),

                'fine_amount' => $reading->fine_amount,
                'fine_charge' => $fineCharge,
                'total_charge' => $reading->total_charge,
                'is_synced' => $reading->is_synced,
                'status' => $reading->status,
                'created_at' => $reading->created_at,
            ];
        });

        // Totals
        $totals = [
            'total_readings' => $readings->count(),
            'total_units' => $readings->sum('total_unit'),
            'total_unit_amount' => $readings->sum('unit_amount'),
            'total_fine_amount' => $readings->sum('fine_amount'),
            'grand_total_charge' => $readings->sum('total_charge'),
            'members_count' => $readings->pluck('meterIssue.memberEntry.member_no')->unique()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $detailedData,
            'totals' => $totals
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'date_from' => [
                    'nullable',
                    'string',
                    'regex:/^\d{4}-\d{2}-\d{2}$/',
                    function ($attribute, $value, $fail) {
                        if (!strtotime($value)) {
                            $fail('The date_from must be a valid date.');
                        }
                    },
                ],
                'date_to' => [
                    'nullable',
                    'string',
                    'regex:/^\d{4}-\d{2}-\d{2}$/',
                    function ($attribute, $value, $fail) {
                        if (!strtotime($value)) {
                            $fail('The date_to must be a valid date.');
                        }
                    },
                ],
                'search' => 'nullable|string|max:255',
                'per_page' => 'nullable|integer|min:1|max:1000',
            ]);

            $perPage = $request->input('per_page', 10);

            $query = MeterReadingEntry::on('tenant')
                ->withoutTrashed()
                ->where('entry_type', 1)
                ->with([
                    'meterIssue:id,member_entry_id,meter_no',
                    'meterIssue.memberEntry:id,customer_name_en,customer_name_np'
                ])
                ->select('meter_reading_entries.*')
                ->join('meter_issues', 'meter_reading_entries.meter_issue_id', '=', 'meter_issues.id')
                ->join('member_entries', function ($join) {
                    $join->on('meter_issues.member_entry_id', '=', 'member_entries.id')
                        ->where('member_entries.is_active', 1)
                        ->whereNull('member_entries.deleted_at');
                });

            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween('meter_reading_entries.reading_date_in_ad', [
                    $validated['date_from'],
                    $validated['date_to']
                ]);
            }

            if ($request->filled('search')) {
                $search = $validated['search'];

                $query->where(function ($q) use ($search) {
                    $q->where('member_entries.member_no', 'like', "%{$search}%")
                        ->orWhere('member_entries.customer_name_en', 'like', "%{$search}%")
                        ->orWhere('member_entries.customer_name_np', 'like', "%{$search}%")
                        ->orWhere('meter_issues.meter_no', 'like', "%{$search}%");
                });
            }

            $entries = $query->paginate($perPage)->through(function ($entry) {
                 $transactions = $entry->customerTransaction
        ->where('transaction_type', 1); 
                return [
                    'id' => $entry->id,
                    'reading_month_in_bs' => $entry->reading_month_in_bs,
                    'reading_date_in_bs' => $entry->reading_date_in_bs,
                    'reading_month_in_ad' => $entry->reading_month_in_ad,
                    'reading_date_in_ad' => $entry->reading_date_in_ad,
                    'meter_issue_id' => $entry->meter_issue_id,

                    // member
                    'customer_name_en' => $entry->meterIssue?->memberEntry?->customer_name_en,
                    'customer_name_np' => $entry->meterIssue?->memberEntry?->customer_name_np,

                    // meter
                    'meter_no' => $entry->meterIssue?->meter_no,

                    // units & charges
                    'previous_unit' => $entry->previous_unit,
                    'current_unit' => $entry->current_unit,
                    'total_unit' => $entry->total_unit,
                    'unit_amount' => $entry->unit_amount,
                    'minimum_demand' => $transactions->where('charge_type', 1)->sum('amount'),
                    'service_charge' => $transactions->where('charge_type', 2)->sum('amount'),
                    'subsidy_charge' => $transactions->where('charge_type', 4)->sum('amount'),
                    'other_charge' => $transactions->where('charge_type', 5)->sum('amount'),
                    'fine_amount' => $entry->fine_amount,
                    'total_charge' => $entry->total_charge,
                    'sub_total_charge' => $entry->sub_total_charge,

                    'created_at' => $entry->created_at,
                    'updated_at' => $entry->updated_at,
                ];
            });

            return response()->json([
                'message' => 'Meter reading entries retrieved successfully',
            ] + $entries->toArray());

        } catch (ValidationException $e) {
            $errors = $e->errors();
            $first = collect($errors)->flatten()->first();

            return response()->json([
                'message' => $first,
                'errors' => $errors
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving meter reading entries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function indexWithPagination(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view opening meter deposit report')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $validated = $request->validate([
                'date_from' => [
                    'nullable',
                    'string',
                    'regex:/^\d{4}-\d{2}-\d{2}$/',
                    function ($attribute, $value, $fail) {
                        if (!strtotime($value)) {
                            $fail('The date_from must be a valid date.');
                        }
                    },
                ],
                'date_to' => [
                    'nullable',
                    'string',
                    'regex:/^\d{4}-\d{2}-\d{2}$/',
                    function ($attribute, $value, $fail) {
                        if (!strtotime($value)) {
                            $fail('The date_to must be a valid date.');
                        }
                    },
                ],
                'search' => 'nullable|string|max:255',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

           $perPage = $request->input('per_page', 10);

        $query = MeterReadingEntry::on('tenant')
            ->withoutTrashed()
            ->where('entry_type', 1)
            ->with([
                'meterIssue:id,member_entry_id,meter_no',
                'meterIssue.memberEntry:id,customer_name_en,customer_name_np'
            ])
            ->select('meter_reading_entries.*')
            ->join('meter_issues', 'meter_reading_entries.meter_issue_id', '=', 'meter_issues.id')
            ->join('member_entries', function ($join) {
                $join->on('meter_issues.member_entry_id', '=', 'member_entries.id')
                    ->where('member_entries.is_active', 1)
                    ->whereNull('member_entries.deleted_at');
            });

        // Date filter
        if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
            $query->whereBetween('meter_reading_entries.reading_date_in_ad', [
                $validated['date_from'],
                $validated['date_to']
            ]);
        }

        // Search filter
        if (!empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($q) use ($search) {
                $q->where('member_entries.member_no', 'like', "%{$search}%")
                    ->orWhere('member_entries.customer_name_en', 'like', "%{$search}%")
                    ->orWhere('member_entries.customer_name_np', 'like', "%{$search}%")
                    ->orWhere('meter_issues.meter_no', 'like', "%{$search}%");
            });
        }

        // Pagination + transform
        $paginated = $query->paginate($perPage)->through(function ($entry) {
             $transactions = $entry->customerTransaction
        ->where('transaction_type', 1); 
            return [
                'id' => $entry->id,
                'reading_month_in_bs' => $entry->reading_month_in_bs,
                'reading_date_in_bs' => $entry->reading_date_in_bs,
                'reading_month_in_ad' => $entry->reading_month_in_ad,
                'reading_date_in_ad' => $entry->reading_date_in_ad,
                'meter_issue_id' => $entry->meter_issue_id,

                'customer_name_en' => $entry->meterIssue?->memberEntry?->customer_name_en,
                'customer_name_np' => $entry->meterIssue?->memberEntry?->customer_name_np,
                'meter_no' => $entry->meterIssue?->meter_no,

                'previous_unit' => $entry->previous_unit,
                'current_unit' => $entry->current_unit,
                'total_unit' => $entry->total_unit,
                'unit_amount' => $entry->unit_amount,
                'minimum_demand' => $transactions->where('charge_type', 1)->sum('amount'),
        'service_charge' => $transactions->where('charge_type', 2)->sum('amount'),
        'subsidy_charge' => $transactions->where('charge_type', 4)->sum('amount'),
        'other_charge' => $transactions->where('charge_type', 5)->sum('amount'),
                'fine_amount' => $entry->fine_amount,
                'total_charge' => $entry->total_charge,
                'sub_total_charge' => $entry->sub_total_charge,

                'created_at' => $entry->created_at,
                'updated_at' => $entry->updated_at,
            ];
        });

        return response()->json([
            'message' => 'Meter reading entries retrieved successfully',
            'data' => $paginated 
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'message' => collect($e->errors())->flatten()->first(),
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while retrieving meter reading entries',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}
