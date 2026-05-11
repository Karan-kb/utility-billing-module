<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\MemberEntry;
use App\Models\MasterSetup;
use App\Models\MeterIssue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Sagautam5\LocalStateNepal\Entities\Province;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MeterIssueReportController extends Controller
{
    public function index(Request $request)
    {
        

        try {
            $validated = $request->validate([
                'date_from' => 'nullable|date|regex:/^\d{4}-\d{2}-\d{2}$/',
                'date_to' => 'nullable|date|regex:/^\d{4}-\d{2}-\d{2}$/',
                'search' => 'nullable|string|max:255',
                'area_id' => 'nullable|exists:tenant.master_setups,id',
                'demand_phase' => 'nullable|exists:tenant.master_setups,id',
                'demand_capacity' => 'nullable|exists:tenant.master_setups,id',
                'purpose_type' => 'nullable|exists:tenant.master_setups,id',
                'transformer' => 'nullable|exists:tenant.master_setups,id',
            ]);

            $query = MeterIssue::on('tenant')
                ->withoutTrashed()
                ->with([
                    'memberEntry:id,member_no,customer_name_en,customer_name_np',
                    'demandPhase:id,name_en,name_np',
                    'demandCapacity:id,name_en,name_np',
                    'purposeType:id,name_en,name_np',
                    'transformer:id,name_en,name_np',
                    'readingArea:id,name_en,name_np'
                ]);

            // Filter by date range
            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween('issue_date_ad', [$validated['date_from'], $validated['date_to']]);
            }

            // Filter by search
            if ($request->filled('search')) {
                $search = $validated['search'];
                $query->whereHas('memberEntry', function ($q) use ($search) {
                    $q->where('customer_name_en', 'like', "%{$search}%")
                      ->orWhere('customer_name_np', 'like', "%{$search}%")
                      ->orWhere('member_no', 'like', "%{$search}%");
                });
            }

            // Filter by foreign keys
            if ($request->filled('area_id')) {
                $query->where('area_id', $validated['area_id']);
            }
            if ($request->filled('demand_phase')) {
                $query->where('phase_id', $validated['demand_phase']);
            }
            if ($request->filled('demand_capacity')) {
                $query->where('capacity_id', $validated['demand_capacity']);
            }
            if ($request->filled('purpose_type')) {
                $query->where('purpose_id', $validated['purpose_type']);
            }
            if ($request->filled('transformer')) {
                $query->where('transformer_id', $validated['transformer']);
            }

            // Fetch and format results
            $meterIssues = $query->get()->map(function ($meterIssue) {
                return [
                    'id' => $meterIssue->id,
                    'member_entry_id' => $meterIssue->memberEntry?->id,
                    'member_no' => $meterIssue->memberEntry?->member_no,
                    'customer_name_en' => $meterIssue->memberEntry?->customer_name_en,
                    'customer_name_np' => $meterIssue->memberEntry?->customer_name_np,
                    'demand_phase' => [
                        'id' => $meterIssue->demandPhase?->id,
                        'name_en' => $meterIssue->demandPhase?->name_en,
                        'name_np' => $meterIssue->demandPhase?->name_np,
                    ],
                    'demand_capacity' => [
                        'id' => $meterIssue->demandCapacity?->id,
                        'name_en' => $meterIssue->demandCapacity?->name_en,
                        'name_np' => $meterIssue->demandCapacity?->name_np,
                    ],
                    'purpose_type' => [
                        'id' => $meterIssue->purposeType?->id,
                        'name_en' => $meterIssue->purposeType?->name_en,
                        'name_np' => $meterIssue->purposeType?->name_np,
                    ],
                    'meter_no' => $meterIssue->meter_no,
                    'transformer' => [
                        'id' => $meterIssue->transformer?->id,
                        'name_en' => $meterIssue->transformer?->name_en,
                        'name_np' => $meterIssue->transformer?->name_np,
                    ],
                    'meter_start_no' => $meterIssue->meter_start_no,
                    'issue_date_bs' => $meterIssue->issue_date_bs,
                    'issue_date_ad' => $meterIssue->issue_date_ad,
                    'construct_company' => $meterIssue->construct_company,
                    'issue_meter_capacity' => $meterIssue->issue_meter_capacity,
                    'reading_seal_no' => $meterIssue->reading_seal_no,
                    'terminal_seal_no' => $meterIssue->terminal_seal_no,
                    'meter_box_seal_no' => $meterIssue->meter_box_seal_no,
                    'pole_no' => $meterIssue->pole_no,
                    'pole_distance' => $meterIssue->pole_distance,
                    'reading_area' => [
                        'id' => $meterIssue->readingArea?->id,
                        'name_en' => $meterIssue->readingArea?->name_en,
                        'name_np' => $meterIssue->readingArea?->name_np,
                    ],
                    'meter_issue_record' => $meterIssue->meter_issue_record,
                    'is_active' => $meterIssue->is_active,
                    'deleted_at' => $meterIssue->deleted_at,
                    'created_at' => $meterIssue->created_at,
                    'updated_at' => $meterIssue->updated_at,
                ];
            });

            return response()->json([
                'message' => 'Meter issues retrieved successfully',
                'data' => $meterIssues->toArray()
            ], 200);

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();
            return response()->json(['message' => $firstErrorMessage, 'errors' => $allErrors], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving meter issues',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    public function indexWithPagination(Request $request)
    {
        if (!$request->user()->hasOrganizationPermission('view meter issue report')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $validated = $request->validate([
                'date_from' => 'nullable|date|regex:/^\d{4}-\d{2}-\d{2}$/',
                'date_to' => 'nullable|date|regex:/^\d{4}-\d{2}-\d{2}$/',
                'search' => 'nullable|string|max:255',
                'area_id' => 'nullable|exists:tenant.master_setups,id',
                'demand_phase' => 'nullable|exists:tenant.master_setups,id',
                'demand_capacity' => 'nullable|exists:tenant.master_setups,id',
                'purpose_type' => 'nullable|exists:tenant.master_setups,id',
                'transformer' => 'nullable|exists:tenant.master_setups,id',
                'per_page' => 'nullable|integer|min:1|max:1000',
            ]);

            $perPage = $request->input('per_page', 10);

            $query = MeterIssue::on('tenant')
                ->withoutTrashed()
                ->with([
                    'memberEntry:id,member_no,customer_name_en,customer_name_np',
                    'demandPhase:id,name_en,name_np',
                    'demandCapacity:id,name_en,name_np',
                    'purposeType:id,name_en,name_np',
                    'transformer:id,name_en,name_np',
                    'readingArea:id,name_en,name_np'
                ]);

            // Filter by date range
            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween('issue_date_ad', [$validated['date_from'], $validated['date_to']]);
            }

            // Filter by search
            if ($request->filled('search')) {
                $search = $validated['search'];
                $query->whereHas('memberEntry', function ($q) use ($search) {
                    $q->where('customer_name_en', 'like', "%{$search}%")
                      ->orWhere('customer_name_np', 'like', "%{$search}%")
                      ->orWhere('member_no', 'like', "%{$search}%");
                });
            }

            // Filter by foreign keys
            if ($request->filled('area_id')) {
                $query->where('area_id', $validated['area_id']);
            }
            if ($request->filled('demand_phase')) {
                $query->where('phase_id', $validated['demand_phase']);
            }
            if ($request->filled('demand_capacity')) {
                $query->where('capacity_id', $validated['demand_capacity']);
            }
            if ($request->filled('purpose_type')) {
                $query->where('purpose_id', $validated['purpose_type']);
            }
            if ($request->filled('transformer')) {
                $query->where('transformer_id', $validated['transformer']);
            }

            // Fetch with pagination
            $meterIssues = $query->paginate($perPage)->through(function ($meterIssue) {
                return [
                    'id' => $meterIssue->id,
                    'member_entry_id' => $meterIssue->memberEntry?->id,
                    'member_no' => $meterIssue->memberEntry?->member_no,
                    'customer_name_en' => $meterIssue->memberEntry?->customer_name_en,
                    'customer_name_np' => $meterIssue->memberEntry?->customer_name_np,
                    'demand_phase' => [
                        'id' => $meterIssue->demandPhase?->id,
                        'name_en' => $meterIssue->demandPhase?->name_en,
                        'name_np' => $meterIssue->demandPhase?->name_np,
                    ],
                    'demand_capacity' => [
                        'id' => $meterIssue->demandCapacity?->id,
                        'name_en' => $meterIssue->demandCapacity?->name_en,
                        'name_np' => $meterIssue->demandCapacity?->name_np,
                    ],
                    'purpose_type' => [
                        'id' => $meterIssue->purposeType?->id,
                        'name_en' => $meterIssue->purposeType?->name_en,
                        'name_np' => $meterIssue->purposeType?->name_np,
                    ],
                    'meter_no' => $meterIssue->meter_no,
                    'transformer' => [
                        'id' => $meterIssue->transformer?->id,
                        'name_en' => $meterIssue->transformer?->name_en,
                        'name_np' => $meterIssue->transformer?->name_np,
                    ],
                    'meter_start_no' => $meterIssue->meter_start_no,
                    'issue_date_bs' => $meterIssue->issue_date_bs,
                    'issue_date_ad' => $meterIssue->issue_date_ad,
                    'construct_company' => $meterIssue->construct_company,
                    'issue_meter_capacity' => $meterIssue->issue_meter_capacity,
                    'reading_seal_no' => $meterIssue->reading_seal_no,
                    'terminal_seal_no' => $meterIssue->terminal_seal_no,
                    'meter_box_seal_no' => $meterIssue->meter_box_seal_no,
                    'pole_no' => $meterIssue->pole_no,
                    'pole_distance' => $meterIssue->pole_distance,
                    'reading_area' => [
                        'id' => $meterIssue->readingArea?->id,
                        'name_en' => $meterIssue->readingArea?->name_en,
                        'name_np' => $meterIssue->readingArea?->name_np,
                    ],
                    'meter_issue_record' => $meterIssue->meter_issue_record,
                    'is_active' => $meterIssue->is_active,
                    'deleted_at' => $meterIssue->deleted_at,
                    'created_at' => $meterIssue->created_at,
                    'updated_at' => $meterIssue->updated_at,
                ];
            });

            return response()->json([
                'message' => 'Meter issues retrieved successfully',
                'data' => $meterIssues->toArray()
            ], 200);

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();
            return response()->json(['message' => $firstErrorMessage, 'errors' => $allErrors], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving meter issues',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
