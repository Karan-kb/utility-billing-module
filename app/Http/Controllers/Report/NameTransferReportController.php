<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\MemberEntry;
use App\Models\MasterSetup;
use App\Models\MeterIssue;
use App\Models\NameTransferEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Sagautam5\LocalStateNepal\Entities\Province;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NameTransferReportController extends Controller
{
    public function index(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view meter issue transfer report')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            // Validate request
            $validated = $request->validate([
                'date_from' => 'nullable|date|regex:/^\d{4}-\d{2}-\d{2}$/',
                'date_to' => 'nullable|date|regex:/^\d{4}-\d{2}-\d{2}$/',
                'search' => 'nullable|string|max:255',
                'demand_phase_id' => 'nullable|integer|exists:tenant.master_setups,id,deleted_at,NULL,is_active,1',
                'demand_capacity_id' => 'nullable|integer|exists:tenant.master_setups,id,deleted_at,NULL,is_active,1',
                'transformer_id' => 'nullable|integer|exists:tenant.master_setups,id,deleted_at,NULL,is_active,1',
                'area_id' => 'nullable|integer|exists:tenant.master_setups,id,deleted_at,NULL,is_active,1',
            ]);

            // Build query
            $query = NameTransferEntry::on('tenant')
                ->withoutTrashed()
                ->with([
                    'previousMember',
                    'newMember',
                    'meterIssueOfNewMember.demandPhase',
                    'meterIssueOfNewMember.demandCapacity',
                    'meterIssueOfNewMember.transformer',
                    'meterIssueOfNewMember.readingArea'
                ]);

            // Date filter
            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween('created_at', [
                    $validated['date_from'] . ' 00:00:00',
                    $validated['date_to'] . ' 23:59:59'
                ]);
            }

            // Search filter
            if ($request->filled('search')) {
                $search = $validated['search'];

                $query->where(function ($q) use ($search) {
                    $q->whereHas('previousMember', function ($q2) use ($search) {
                        $q2->where('customer_name_en', 'like', "%$search%")
                           ->orWhere('customer_name_np', 'like', "%$search%")
                           ->orWhere('member_no', 'like', "%$search%");
                    })
                    ->orWhereHas('newMember', function ($q2) use ($search) {
                        $q2->where('customer_name_en', 'like', "%$search%")
                           ->orWhere('customer_name_np', 'like', "%$search%")
                           ->orWhere('member_no', 'like', "%$search%");
                    })
                    ->orWhereHas('meterIssueOfNewMember', function ($q2) use ($search) {
                        $q2->where('meter_no', 'like', "%$search%");
                    });
                });
            }

            // Filter meter_issue_of_new_member
            if ($request->filled('demand_phase_id')) {
                $query->whereHas('meterIssueOfNewMember', function ($q) use ($validated) {
                    $q->where('phase_id', $validated['demand_phase_id']);
                });
            }

            if ($request->filled('demand_capacity_id')) {
                $query->whereHas('meterIssueOfNewMember', function ($q) use ($validated) {
                    $q->where('capacity_id', $validated['demand_capacity_id']);
                });
            }

            if ($request->filled('transformer_id')) {
                $query->whereHas('meterIssueOfNewMember', function ($q) use ($validated) {
                    $q->where('transformer_id', $validated['transformer_id']);
                });
            }

            if ($request->filled('area_id')) {
                $query->whereHas('meterIssueOfNewMember', function ($q) use ($validated) {
                    $q->where('area_id', $validated['area_id']);
                });
            }

            // Fetch results
            $transfers = $query->orderBy('id', 'desc')->get()->map(function ($entry) {

                $meter = $entry->meterIssueOfNewMember;

                return [
                    'id' => $entry->id,

                    // Previous member
                    'previous_member_entry_id' => $entry->previous_member_entry_id,
                    'previous_customer_name_en' => $entry->previousMember?->customer_name_en,
                    'previous_customer_name_np' => $entry->previousMember?->customer_name_np,
                    'previous_member_no' => $entry->previousMember?->member_no,

                    // New member
                    'new_member_entry_id' => $entry->new_member_entry_id,
                    'new_customer_name_en' => $entry->newMember?->customer_name_en,
                    'new_customer_name_np' => $entry->newMember?->customer_name_np,
                    'new_member_no' => $entry->newMember?->member_no,

                    // Meter details from meter_issues
                    'meter_no' => $meter?->meter_no,
                    'meter_start_no' => $meter?->meter_start_no,
                    'meter_issue_record' => $meter?->meter_issue_record,

                    // Demand Phase
                    'demand_phase' => $meter ? [
                        'id' => $meter->demandPhase?->id,
                        'name_en' => $meter->demandPhase?->name_en,
                        'name_np' => $meter->demandPhase?->name_np,
                    ] : null,

                    // Capacity
                    'demand_capacity' => $meter ? [
                        'id' => $meter->demandCapacity?->id,
                        'name_en' => $meter->demandCapacity?->name_en,
                        'name_np' => $meter->demandCapacity?->name_np,
                    ] : null,

                    // Transformer
                    'transformer' => $meter ? [
                        'id' => $meter->transformer?->id,
                        'name_en' => $meter->transformer?->name_en,
                        'name_np' => $meter->transformer?->name_np,
                    ] : null,

                    // Area
                    'area' => $meter ? [
                        'id' => $meter->readingArea?->id,
                        'name_en' => $meter->readingArea?->name_en,
                        'name_np' => $meter->readingArea?->name_np,
                    ] : null,

                    'is_active' => $meter?->is_active,
                    'created_at' => $entry->created_at,
                    'updated_at' => $entry->updated_at,
                ];
            });

            return response()->json([
                'message' => 'Transfers retrieved successfully',
                'data' => $transfers
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving transfers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





    public function indexWithPagination(Request $request)
{
    // if (!$request->user()->hasOrganizationPermission('view meter issue transfer report')) {
    //     return response()->json(['message' => 'Unauthorized'], 403);
    // }

    try {
        $validated = $request->validate([
            'date_from' => 'nullable|date|regex:/^\d{4}-\d{2}-\d{2}$/',
            'date_to' => 'nullable|date|regex:/^\d{4}-\d{2}-\d{2}$/',
            'search' => 'nullable|string|max:255',

            'demand_phase_id' => 'nullable|integer|exists:tenant.master_setups,id,deleted_at,NULL,is_active,1',
            'demand_capacity_id' => 'nullable|integer|exists:tenant.master_setups,id,deleted_at,NULL,is_active,1',
            'transformer_id' => 'nullable|integer|exists:tenant.master_setups,id,deleted_at,NULL,is_active,1',
            'area_id' => 'nullable|integer|exists:tenant.master_setups,id,deleted_at,NULL,is_active,1',

            'per_page' => 'nullable|integer|min:1|max:1000',
        ]);

        $perPage = $request->input('per_page', 10);

        $query = NameTransferEntry::on('tenant')
            ->withTrashed()
            ->select(
                'name_transfer_entries.*',

                // Previous Member
                'prev_member.customer_name_en as previous_customer_name_en',
                'prev_member.customer_name_np as previous_customer_name_np',
                'prev_member.member_no as previous_member_no',

                // New Member
                'new_member.customer_name_en as new_customer_name_en',
                'new_member.customer_name_np as new_customer_name_np',
                'new_member.member_no as new_member_no',

                // Meter info
                'meter.meter_no',
                'meter.meter_start_no',
                'meter.meter_issue_record',

                // Master setups
                'phase.id as demand_phase_id',
                'phase.name_en as demand_phase_name_en',
                'phase.name_np as demand_phase_name_np',

                'capacity.id as demand_capacity_id',
                'capacity.name_en as demand_capacity_name_en',
                'capacity.name_np as demand_capacity_name_np',

                'transformer.id as transformer_id',
                'transformer.name_en as transformer_name_en',
                'transformer.name_np as transformer_name_np',

                'area.id as area_id',
                'area.name_en as area_name_en',
                'area.name_np as area_name_np'
            )

            // Previous member
            ->leftJoin('member_entries as prev_member', function ($join) {
                $join->on('name_transfer_entries.previous_member_entry_id', '=', 'prev_member.id');
            })

            // New member
            ->leftJoin('member_entries as new_member', function ($join) {
                $join->on('name_transfer_entries.new_member_entry_id', '=', 'new_member.id')
                    ->whereNull('new_member.deleted_at')
                    ->where('new_member.is_active', 1);
            })

            // Meter issues – FIXED
            ->leftJoin('meter_issues as meter', function ($join) {
                $join->on('name_transfer_entries.new_member_entry_id', '=', 'meter.member_entry_id')
                    ->whereNull('meter.deleted_at')
                    ->where('meter.is_active', 1);
            })

            // Phase – FIXED
            ->leftJoin('master_setups as phase', function ($join) {
                $join->on('meter.phase_id', '=', 'phase.id')
                    ->where('phase.master_setup_type_id', 3)
                    ->where('phase.is_active', 1)
                    ->whereNull('phase.deleted_at');
            })

            // Capacity – FIXED
            ->leftJoin('master_setups as capacity', function ($join) {
                $join->on('meter.capacity_id', '=', 'capacity.id')
                    ->where('capacity.master_setup_type_id', 4)
                    ->where('capacity.is_active', 1)
                    ->whereNull('capacity.deleted_at');
            })

            // Transformer – FIXED
            ->leftJoin('master_setups as transformer', function ($join) {
                $join->on('meter.transformer_id', '=', 'transformer.id')
                    ->where('transformer.master_setup_type_id', 5)
                    ->where('transformer.is_active', 1)
                    ->whereNull('transformer.deleted_at');
            })

            // Area – FIXED
            ->leftJoin('master_setups as area', function ($join) {
                $join->on('meter.area_id', '=', 'area.id')
                    ->where('area.master_setup_type_id', 6)
                    ->where('area.is_active', 1)
                    ->whereNull('area.deleted_at');
            });

        // Date filter
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('name_transfer_entries.created_at', [
                $validated['date_from'],
                $validated['date_to']
            ]);
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('prev_member.customer_name_en', 'like', "%$search%")
                    ->orWhere('prev_member.customer_name_np', 'like', "%$search%")
                    ->orWhere('new_member.customer_name_en', 'like', "%$search%")
                    ->orWhere('new_member.customer_name_np', 'like', "%$search%")
                    ->orWhere('prev_member.member_no', 'like', "%$search%")
                    ->orWhere('new_member.member_no', 'like', "%$search%")
                    ->orWhere('meter.meter_no', 'like', "%$search%");
            });
        }

        // Filters – FIXED
        if ($request->filled('demand_phase_id')) {
            $query->where('meter.phase_id', $validated['demand_phase_id']);
        }

        if ($request->filled('demand_capacity_id')) {
            $query->where('meter.capacity_id', $validated['demand_capacity_id']);
        }

        if ($request->filled('transformer_id')) {
            $query->where('meter.transformer_id', $validated['transformer_id']);
        }

        if ($request->filled('area_id')) {
            $query->where('meter.area_id', $validated['area_id']);
        }

        // Pagination transform
        $transfers = $query->paginate($perPage)->through(function ($entry) {
            return [
                'id' => $entry->id,

                'previous_member_entry_id' => $entry->previous_member_entry_id,
                'previous_customer_name_en' => $entry->previous_customer_name_en,
                'previous_customer_name_np' => $entry->previous_customer_name_np,
                'previous_member_no' => $entry->previous_member_no,

                'new_member_entry_id' => $entry->new_member_entry_id,
                'new_customer_name_en' => $entry->new_customer_name_en,
                'new_customer_name_np' => $entry->new_customer_name_np,
                'new_member_no' => $entry->new_member_no,

                'meter_no' => $entry->meter_no,
                'meter_start_no' => $entry->meter_start_no,
                'meter_issue_record' => $entry->meter_issue_record,

                'demand_phase' => [
                    'id' => $entry->demand_phase_id,
                    'name_en' => $entry->demand_phase_name_en,
                    'name_np' => $entry->demand_phase_name_np,
                ],

                'demand_capacity' => [
                    'id' => $entry->demand_capacity_id,
                    'name_en' => $entry->demand_capacity_name_en,
                    'name_np' => $entry->demand_capacity_name_np,
                ],

                'transformer' => [
                    'id' => $entry->transformer_id,
                    'name_en' => $entry->transformer_name_en,
                    'name_np' => $entry->transformer_name_np,
                ],

                'area' => [
                    'id' => $entry->area_id,
                    'name_en' => $entry->area_name_en,
                    'name_np' => $entry->area_name_np,
                ],

                'is_active' => $entry->is_active,
                'created_at' => $entry->created_at,
                'updated_at' => $entry->updated_at,
            ];
        });

        return response()->json([
            'message' => 'Transfers retrieved successfully',
        ] + $transfers->toArray());

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while retrieving transfers',
            'error' => $e->getMessage(),
        ], 500);
    }
}





}
