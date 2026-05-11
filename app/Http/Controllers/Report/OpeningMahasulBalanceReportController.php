<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\MeterIssue;
use App\Models\MeterReadingEntry;
use App\Models\OpeningMahasulBalanceEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OpeningMahasulBalanceReportController extends Controller
{



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
                ->where('entry_type', 2)
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
        ->where('transaction_type', 5); 
                return [
                    'id' => $entry->id,
                    'reading_date_in_bs' => $entry->reading_date_in_bs,
                    'reading_date_in_ad' => $entry->reading_date_in_ad,
                    'meter_issue_id' => $entry->meter_issue_id,

                    // member
                    'customer_name_en' => $entry->meterIssue?->memberEntry?->customer_name_en,
                    'customer_name_np' => $entry->meterIssue?->memberEntry?->customer_name_np,
                    'meter_no' => $entry->meterIssue?->meter_no,
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
            ->where('entry_type', 2)
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
        ->where('transaction_type', 5); 
            return [
                'id' => $entry->id,
                'reading_date_in_bs' => $entry->reading_date_in_bs,
                'reading_date_in_ad' => $entry->reading_date_in_ad,
                'meter_issue_id' => $entry->meter_issue_id,

                'customer_name_en' => $entry->meterIssue?->memberEntry?->customer_name_en,
                'customer_name_np' => $entry->meterIssue?->memberEntry?->customer_name_np,
                'meter_no' => $entry->meterIssue?->meter_no,
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
