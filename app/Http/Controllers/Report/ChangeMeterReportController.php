<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\ChangeMeter;
use App\Models\MeterReadingEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ChangeMeterReportController extends Controller
{



    // public function index(Request $request)
    // {
    //     // if (!$request->user()->hasOrganizationPermission('view change meter report')) {
    //     //     return response()->json(['message' => 'Unauthorized'], 403);
    //     // }

    //     try {
    //         $validated = $request->validate([
    //             'date_from' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
    //             'date_to' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
    //             'search' => 'nullable|string|max:255',
    //         ]);

    //         $query = ChangeMeter::on('tenant')
    //             ->withoutTrashed()
    //             ->select(
    //                 'change_meters.*',
    //                 'member_entry.customer_name_en',
    //                 'member_entry.customer_name_np',
    //                 'area_master.name_en as area_name_en',
    //                 'area_master.name_np as area_name_np',
    //                 'meter_issue.transformer as transformer', // <-- add this
    //                 'transformer_master.name_en as transformer_name_en',
    //                 'transformer_master.name_np as transformer_name_np',
    //                 'phase_master.name_en as demand_phase_name_en',
    //                 'phase_master.name_np as demand_phase_name_np',
    //                 'capacity_master.name_en as demand_capacity_name_en',
    //                 'capacity_master.name_np as demand_capacity_name_np',
    //                 'purpose_master.name_en as purpose_type_name_en',
    //                 'purpose_master.name_np as purpose_type_name_np'
    //             )
    //             ->join('member_entry', function ($join) {
    //                 $join->on('change_meter_customer.customer_id', '=', 'member_entry.customer_id')
    //                     ->whereNull('member_entry.deleted_at')
    //                     ->where('member_entry.is_active', 1);
    //             })
    //             ->leftJoin('meter_issue', function ($join) {
    //                 $join->on('change_meter_customer.customer_id', '=', 'meter_issue.customer_id')
    //                     ->whereNull('meter_issue.deleted_at')
    //                     ->where('meter_issue.is_active', 1);
    //             })
    //             ->leftJoin('master_setup as area_master', function ($join) {
    //                 $join->on('meter_issue.reading_area', '=', 'area_master.id')
    //                     ->where('area_master.type_id', 6); // area
    //             })
    //             ->leftJoin('master_setup as transformer_master', function ($join) {
    //                 $join->on('meter_issue.transformer', '=', 'transformer_master.id')
    //                     ->where('transformer_master.type_id', 5); // transformer
    //             })
    //             ->leftJoin('master_setup as phase_master', function ($join) {
    //                 $join->on('meter_issue.demand_phase', '=', 'phase_master.id')
    //                     ->where('phase_master.type_id', 3); // phase
    //             })
    //             ->leftJoin('master_setup as capacity_master', function ($join) {
    //                 $join->on('meter_issue.demand_capacity', '=', 'capacity_master.id')
    //                     ->where('capacity_master.type_id', 4); // capacity
    //             })
    //             ->leftJoin('master_setup as purpose_master', function ($join) {
    //                 $join->on('meter_issue.purpose_type', '=', 'purpose_master.id')
    //                     ->where('purpose_master.type_id', 1); // purpose type
    //             });

    //         // Date filter
    //         if ($request->filled('date_from') && $request->filled('date_to')) {
    //             $query->whereBetween('change_meter_customer.issue_date_ad', [
    //                 $validated['date_from'],
    //                 $validated['date_to']
    //             ]);
    //         }

    //         // Search filter
    //         if ($request->filled('search')) {
    //             $search = $validated['search'];
    //             $query->where(function ($q) use ($search) {
    //                 $q->where('change_meter_customer.customer_id', 'like', "%{$search}%")
    //                     ->orWhere('member_entry.customer_name_en', 'like', "%{$search}%")
    //                     ->orWhere('member_entry.customer_name_np', 'like', "%{$search}%")
    //                     ->orWhere('change_meter_customer.meter_no', 'like', "%{$search}%")
    //                     ->orWhere('change_meter_customer.previous_meter_no', 'like', "%{$search}%")
    //                     ->orWhere('area_master.name_en', 'like', "%{$search}%")
    //                     ->orWhere('area_master.name_np', 'like', "%{$search}%")
    //                     ->orWhere('transformer_master.name_en', 'like', "%{$search}%")
    //                     ->orWhere('transformer_master.name_np', 'like', "%{$search}%")
    //                     ->orWhere('phase_master.name_en', 'like', "%{$search}%")
    //                     ->orWhere('phase_master.name_np', 'like', "%{$search}%")
    //                     ->orWhere('capacity_master.name_en', 'like', "%{$search}%")
    //                     ->orWhere('capacity_master.name_np', 'like', "%{$search}%")
    //                     ->orWhere('purpose_master.name_en', 'like', "%{$search}%")
    //                     ->orWhere('purpose_master.name_np', 'like', "%{$search}%");
    //             });
    //         }

    //         $entries = $query->get()->map(function ($entry) {
    //             return [
    //                 'id' => $entry->id,
    //                 'member_entry_id' => $entry->customer_id,
    //                 'customer_name_en' => $entry->customer_name_en,
    //                 'customer_name_np' => $entry->customer_name_np,
    //                 'previous_meter_no' => $entry->previous_meter_no,
    //                 'meter_no' => $entry->meter_no,
    //                 'issue_date_bs' => $entry->issue_date_bs,
    //                 'issue_date_ad' => $entry->issue_date_ad,
    //                 'construct_company' => $entry->construct_company,
    //                 'issue_meter_capacity' => $entry->issue_meter_capacity,
    //                 'reading_seal_no' => $entry->reading_seal_no,
    //                 'terminal_seal_no' => $entry->terminal_seal_no,
    //                 'meter_box_seal_no' => $entry->meter_box_seal_no,
    //                 'reading_area' => $entry->reading_area,
    //                 'area_name_en' => $entry->area_name_en,
    //                 'area_name_np' => $entry->area_name_np,
    //                 'transformer' => $entry->transformer,
    //                 'transformer_name_en' => $entry->transformer_name_en,
    //                 'transformer_name_np' => $entry->transformer_name_np,
    //                 'demand_phase_name_en' => $entry->demand_phase_name_en,
    //                 'demand_phase_name_np' => $entry->demand_phase_name_np,
    //                 'demand_capacity_name_en' => $entry->demand_capacity_name_en,
    //                 'demand_capacity_name_np' => $entry->demand_capacity_name_np,
    //                 'purpose_type_name_en' => $entry->purpose_type_name_en,
    //                 'purpose_type_name_np' => $entry->purpose_type_name_np,
    //                 'due_balance_amount' => $entry->due_balance_amount,
    //                 'is_active' => $entry->is_active,
    //                 'created_at' => $entry->created_at,
    //                 'updated_at' => $entry->updated_at,
    //             ];
    //         });

    //         return response()->json([
    //             'message' => 'Change meter report retrieved successfully',
    //             'data' => $entries,
    //         ], 200);

    //     } catch (ValidationException $e) {
    //         return response()->json([
    //             'message' => collect($e->errors())->flatten()->first(),
    //             'errors' => $e->errors(),
    //         ], 422);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'An error occurred while retrieving change meter report',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

public function index(Request $request)
{
    try {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d'],
            'search'    => 'nullable|string|max:255',
        ]);

        $query = ChangeMeter::on('tenant')
            ->withoutTrashed()
            ->select(
                'change_meters.*',

                // member
                'member_entries.customer_name_en',
                'member_entries.customer_name_np',

                // meter issue
                'meter_issues.meter_no',
                'meter_issues.transformer_id',
                'meter_issues.area_id',

                // master setup names
                'area_master.name_en as area_name_en',
                'area_master.name_np as area_name_np',

                'transformer_master.name_en as transformer_name_en',
                'transformer_master.name_np as transformer_name_np',

                'phase_master.name_en as demand_phase_name_en',
                'phase_master.name_np as demand_phase_name_np',

                'capacity_master.name_en as demand_capacity_name_en',
                'capacity_master.name_np as demand_capacity_name_np',

                'purpose_master.name_en as purpose_type_name_en',
                'purpose_master.name_np as purpose_type_name_np'
            )
             ->selectSub(
            MeterReadingEntry::query()
                ->select('current_unit')
                ->whereColumn('meter_reading_entries.meter_issue_id', 'change_meters.meter_issue_id')
                ->whereColumn('meter_reading_entries.created_at', '<=', 'change_meters.created_at')
                ->whereNull('meter_reading_entries.deleted_at')
                ->where('meter_reading_entries.entry_type', 1) 
                ->orderByDesc('id') 
                ->limit(1),
            'previous_meter_start_no'
        )

            // join meter issue
            ->join('meter_issues', function ($join) {
                $join->on('change_meters.meter_issue_id', '=', 'meter_issues.id')
                    ->whereNull('meter_issues.deleted_at')
                    ->where('meter_issues.is_active', 1);
            })

            // join member
            ->join('member_entries', function ($join) {
                $join->on('meter_issues.member_entry_id', '=', 'member_entries.id')
                    ->whereNull('member_entries.deleted_at')
                    ->where('member_entries.is_active', 1);
            })

            // area
            ->leftJoin('master_setups as area_master', function ($join) {
                $join->on('meter_issues.area_id', '=', 'area_master.id')
                    ->where('area_master.master_setup_type_id', 6);
            })

            // transformer
            ->leftJoin('master_setups as transformer_master', function ($join) {
                $join->on('meter_issues.transformer_id', '=', 'transformer_master.id')
                    ->where('transformer_master.master_setup_type_id', 5);
            })

            // phase
            ->leftJoin('master_setups as phase_master', function ($join) {
                $join->on('meter_issues.phase_id', '=', 'phase_master.id')
                    ->where('phase_master.master_setup_type_id', 3);
            })

            // capacity
            ->leftJoin('master_setups as capacity_master', function ($join) {
                $join->on('meter_issues.capacity_id', '=', 'capacity_master.id')
                    ->where('capacity_master.master_setup_type_id', 4);
            })

            // purpose
            ->leftJoin('master_setups as purpose_master', function ($join) {
                $join->on('meter_issues.purpose_id', '=', 'purpose_master.id')
                    ->where('purpose_master.master_setup_type_id', 1);
            });

        // ✅ Date filter
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('change_meters.date_in_ad', [
                $validated['date_from'],
                $validated['date_to']
            ]);
        }

        // ✅ Search filter
        if ($request->filled('search')) {
            $search = $validated['search'];

            $query->where(function ($q) use ($search) {
                $q->where('member_entries.customer_name_en', 'like', "%{$search}%")
                  ->orWhere('member_entries.customer_name_np', 'like', "%{$search}%")
                  ->orWhere('meter_issues.meter_no', 'like', "%{$search}%")
                  ->orWhere('change_meters.previous_meter_no', 'like', "%{$search}%")
                  ->orWhere('area_master.name_en', 'like', "%{$search}%")
                  ->orWhere('transformer_master.name_en', 'like', "%{$search}%");
            });
        }

        $entries = $query->get()->map(function ($entry) {
            return [
                'id' => $entry->id,
                'member_entry_id' => $entry->member_entry_id,

                'customer_name_en' => $entry->customer_name_en,
                'customer_name_np' => $entry->customer_name_np,

                'previous_meter_no' => $entry->previous_meter_no,
                'current_meter_no' => $entry->meter_no,
            'previous_meter_start_no' => $entry->previous_meter_start_no ?? 0,
                'current_meter_start_no' => $entry->meter_start_no,

                'date_in_bs' => $entry->date_in_bs,
                'date_in_ad' => $entry->date_in_ad,

                'construct_company' => $entry->construct_company,

                'reading_seal_no' => $entry->reading_seal_no,
                'terminal_seal_no' => $entry->terminal_seal_no,
                'meter_box_seal_no' => $entry->meter_box_seal_no,

                'area_name_en' => $entry->area_name_en,
                'area_name_np' => $entry->area_name_np,

                'transformer_name_en' => $entry->transformer_name_en,
                'transformer_name_np' => $entry->transformer_name_np,

                'demand_phase_name_en' => $entry->demand_phase_name_en,
                'demand_phase_name_np' => $entry->demand_phase_name_np,

                'demand_capacity_name_en' => $entry->demand_capacity_name_en,
                'demand_capacity_name_np' => $entry->demand_capacity_name_np,

                'purpose_type_name_en' => $entry->purpose_type_name_en,
                'purpose_type_name_np' => $entry->purpose_type_name_np,

                'created_at' => $entry->created_at,
                'updated_at' => $entry->updated_at,
            ];
        });

        return response()->json([
            'message' => 'Change meter report retrieved successfully',
            'data' => $entries,
        ]);

    } catch (ValidationException $e) {
        return response()->json([
            'message' => collect($e->errors())->flatten()->first(),
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error fetching report',
            'error' => $e->getMessage(),
        ], 500);
    }
}




   public function indexWithPagination(Request $request)
{
    // if (!$request->user()->hasOrganizationPermission('view change meter report')) {
    //     return response()->json(['message' => 'Unauthorized'], 403);
    // }

    try {
        $validated = $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to'   => 'nullable|date_format:Y-m-d',
            'search'    => 'nullable|string|max:255',
            'per_page'  => 'nullable|integer|min:1|max:1000',
        ]);

        $perPage = $validated['per_page'] ?? 15;

        $query = ChangeMeter::on('tenant')
            ->withoutTrashed()
            ->select(
                'change_meters.*',

                // member
                'member_entries.id as member_entry_id',
                'member_entries.customer_name_en',
                'member_entries.customer_name_np',

                // meter issue
                'meter_issues.meter_no',
                'meter_issues.transformer_id',
                'meter_issues.area_id',

                // master setup names
                'area_master.name_en as area_name_en',
                'area_master.name_np as area_name_np',

                'transformer_master.name_en as transformer_name_en',
                'transformer_master.name_np as transformer_name_np',

                'phase_master.name_en as demand_phase_name_en',
                'phase_master.name_np as demand_phase_name_np',

                'capacity_master.name_en as demand_capacity_name_en',
                'capacity_master.name_np as demand_capacity_name_np',

                'purpose_master.name_en as purpose_type_name_en',
                'purpose_master.name_np as purpose_type_name_np'
            )
            ->selectSub(
                        MeterReadingEntry::query()
                            ->select('current_unit')
                            ->whereColumn('meter_reading_entries.meter_issue_id', 'change_meters.meter_issue_id')
                            ->whereColumn('meter_reading_entries.created_at', '<=', 'change_meters.created_at')
                            ->whereNull('meter_reading_entries.deleted_at')
                            ->where('meter_reading_entries.entry_type', 1) 
                            ->orderByDesc('id') 
                            ->limit(1),
                        'previous_meter_start_no'
                    )
            ->join('meter_issues', function ($join) {
                $join->on('change_meters.meter_issue_id', '=', 'meter_issues.id')
                    ->whereNull('meter_issues.deleted_at')
                    ->where('meter_issues.is_active', 1);
            })

            ->join('member_entries', function ($join) {
                $join->on('meter_issues.member_entry_id', '=', 'member_entries.id')
                    ->whereNull('member_entries.deleted_at')
                    ->where('member_entries.is_active', 1);
            })

            ->leftJoin('master_setups as area_master', function ($join) {
                $join->on('meter_issues.area_id', '=', 'area_master.id')
                    ->where('area_master.master_setup_type_id', 6);
            })

            ->leftJoin('master_setups as transformer_master', function ($join) {
                $join->on('meter_issues.transformer_id', '=', 'transformer_master.id')
                    ->where('transformer_master.master_setup_type_id', 5);
            })

            ->leftJoin('master_setups as phase_master', function ($join) {
                $join->on('meter_issues.phase_id', '=', 'phase_master.id')
                    ->where('phase_master.master_setup_type_id', 3);
            })

            ->leftJoin('master_setups as capacity_master', function ($join) {
                $join->on('meter_issues.capacity_id', '=', 'capacity_master.id')
                    ->where('capacity_master.master_setup_type_id', 4);
            })

            ->leftJoin('master_setups as purpose_master', function ($join) {
                $join->on('meter_issues.purpose_id', '=', 'purpose_master.id')
                    ->where('purpose_master.master_setup_type_id', 1);
            });

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('change_meters.date_in_ad', [
                $validated['date_from'],
                $validated['date_to']
            ]);
        }

        if ($request->filled('search')) {
            $search = $validated['search'];

            $query->where(function ($q) use ($search) {
                $q->where('member_entries.customer_name_en', 'like', "%{$search}%")
                  ->orWhere('member_entries.customer_name_np', 'like', "%{$search}%")
                  ->orWhere('meter_issues.meter_no', 'like', "%{$search}%")
                  ->orWhere('change_meters.previous_meter_no', 'like', "%{$search}%")
                  ->orWhere('area_master.name_en', 'like', "%{$search}%")
                  ->orWhere('transformer_master.name_en', 'like', "%{$search}%");
            });
        }

        $entries = $query->paginate($perPage)->through(function ($entry) {
            return [
                'id' => $entry->id,
                'member_entry_id' => $entry->member_entry_id,

                'customer_name_en' => $entry->customer_name_en,
                'customer_name_np' => $entry->customer_name_np,

                'previous_meter_no' => $entry->previous_meter_no,
                'previous_meter_start_no' => $entry->previous_meter_start_no,
                'current_meter_no' => $entry->meter_no,
                'current_meter_start_no' => $entry->meter_start_no,

                'date_in_bs' => $entry->date_in_bs,
                'date_in_ad' => $entry->date_in_ad,

                'construct_company' => $entry->construct_company,

                'reading_seal_no' => $entry->reading_seal_no,
                'terminal_seal_no' => $entry->terminal_seal_no,
                'meter_box_seal_no' => $entry->meter_box_seal_no,

                'area_name_en' => $entry->area_name_en,
                'area_name_np' => $entry->area_name_np,

                'transformer_name_en' => $entry->transformer_name_en,
                'transformer_name_np' => $entry->transformer_name_np,

                'demand_phase_name_en' => $entry->demand_phase_name_en,
                'demand_phase_name_np' => $entry->demand_phase_name_np,

                'demand_capacity_name_en' => $entry->demand_capacity_name_en,
                'demand_capacity_name_np' => $entry->demand_capacity_name_np,

                'purpose_type_name_en' => $entry->purpose_type_name_en,
                'purpose_type_name_np' => $entry->purpose_type_name_np,

                'created_at' => $entry->created_at,
                'updated_at' => $entry->updated_at,
            ];
        });

        return response()->json([
            'message' => 'Change meter report retrieved successfully',
        ] + $entries->toArray());

    } catch (ValidationException $e) {
        return response()->json([
            'message' => collect($e->errors())->flatten()->first(),
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while retrieving paginated change meter report',
            'error' => $e->getMessage(),
        ], 500);
    }
}

}
