<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\MemberEntry;
use App\Models\MasterSetup;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MemberReportController extends Controller
{
   

    public function index(Request $request)
{
    

    try {
       
        $genderMap = [
            0 => 'Male',
            1 => 'Female',
            2 => 'Others',
        ];

       
        $validated = $request->validate([
            'date_from' => 'nullable|date|regex:/^\d{4}-\d{2}-\d{2}$/',
            'date_to' => 'nullable|date|regex:/^\d{4}-\d{2}-\d{2}$/',
            'search' => 'nullable|string|max:255',
            'gender' => 'nullable|in:0,1,2',
            'occupation_id' => 'nullable|exists:tenant.master_setups,id',
            'area_id' => 'nullable|exists:tenant.master_setups,id',
            'demand_phase' => 'nullable|exists:tenant.master_setups,id',
            'demand_capacity' => 'nullable|exists:tenant.master_setups,id',
            'purpose_type' => 'nullable|exists:tenant.master_setups,id',
            'transformer_id' => 'nullable|exists:tenant.master_setups,id',
        ]);

      
        $query = MemberEntry::on('tenant')
            ->with([
                'occupation:id,name_en,name_np',
                'area:id,name_en,name_np',
                'province:id,name_en,name_np',
                'district:id,name_en,name_np',
                'municipality:id,name_en,name_np',
                'meterIssues' => function ($q) use ($validated) {
                    $q->with([
                        'demandPhase:id,name_en,name_np',
                        'demandCapacity:id,name_en,name_np',
                        'purposeType:id,name_en,name_np',
                        'transformer:id,name_en,name_np',
                        'readingArea:id,name_en,name_np',
                    ])->where('is_active', 1);

                    
                }
            ]);

       
        $memberFilters = [
            'occupation_id' => 'occupation_id',
            'area_id' => 'area_id',
            'gender' => 'gender',
        ];

        foreach ($memberFilters as $param => $column) {
            if (!empty($validated[$param])) {
                $query->where($column, $validated[$param]);
            }
        }

        if (!empty($validated['demand_phase']) || !empty($validated['demand_capacity']) ||  !empty($validated['purpose_type']) ||  !empty($validated['transformer_id']) || !empty($validated['area_id'])) {
            $query->whereHas('meterIssues', function ($q) use ($validated) {
                $q->where('is_active', 1);
        
                if (!empty($validated['demand_phase'])) {
                    $q->where('phase_id', $validated['demand_phase']);
                }
                if (!empty($validated['demand_capacity'])) {
                    $q->where('capacity_id', $validated['demand_capacity']);
                }
                if (!empty($validated['purpose_type'])) {
                    $q->where('purpose_id', $validated['purpose_type']);
                }
                if (!empty($validated['transformer_id'])) {
                    $q->where('transformer_id', $validated['transformer_id']);
                }
                if (!empty($validated['area_id'])) {
                    $q->where('area_id', $validated['area_id']);
                }
            });
        }

        
        if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
            $query->whereBetween('created_at', [$validated['date_from'], $validated['date_to']]);
        }

       
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('customer_name_en', 'like', "%{$search}%")                 
                  ->orWhere('member_no', 'like', "%{$search}%");
            });
        }

        $perPage = $request->query('per_page', 50); // default 50
        $page = $request->query('page', 1);
        $memberEntries = $query->paginate($perPage, ['*'], 'page', $page);
        $memberEntries->getCollection()->transform(function ($member) use ($genderMap) {
            return [
                'id' => $member->id,
                'member_no' => $member->member_no,
                'customer_name_en' => $member->customer_name_en,
                'customer_name_np' => $member->customer_name_np,
                'gender' => $genderMap[$member->gender] ?? 'Others',
                'contact_no' => $member->contact_no,
                'pan_no' => $member->pan_no,
                'contact_no' => $member->contact_no,
                'house_no' => $member->house_no,
                'ward_no' => $member->ward_no,
                'occupation' => $member->occupation->name_en ?? null,
                'member_area' => $member->area->name_en ?? null,  
                'province' => $member->province->name_en ?? null,
                'district' => $member->district->name_en ?? null,
                'municipality' => $member->municipality->name_en ?? null,
                'meter_issues' => $member->meterIssues->map(function ($meter) {
                    return [
                        'id' => $meter->id,                      
                        'meter_no' => $meter->meter_no,
                        'meter_start_no' => $meter->meter_start_no,
                        'meter_box_seal_no' => $meter->meter_box_seal_no,
                        'pole_no' => $meter->pole_no,
                        'pole_distance' => $meter->pole_distance,
                        'reading_seal_no' => $meter->reading_seal_no,
                        'terminal_seal_no' => $meter->terminal_seal_no,
                        // 'issue_date_bs' => $meter->issue_date_bs,
                        'issue_meter_capacity' => $meter->issue_meter_capacity,
                        'demand_phase' => $meter->demandPhase
                                                            ? [
                                                                'id'   => $meter->demandPhase->id,
                                                                'name_en' => $meter->demandPhase->name_en,                                                                
                                                            ]
                                                            : null,
                        'demand_capacity' => $meter->demandCapacity
                                                    ? [
                                                        'id'   => $meter->demandCapacity->id,
                                                        'name_en' => $meter->demandCapacity->name_en,                                                                
                                                    ]
                                                    : null,
                        'purpose_type' => $meter->purposeType->name_en ?? null,
                   
                        'transformer' => $meter->transformer->name_en ?? null,
                    
                        'reading_area' =>  $meter->readingArea->name_en ?? null,                  
                    ];
                }),
            ];
        });

        $totalEntries = $memberEntries->count();
        $genderCounts = $memberEntries->getCollection()->groupBy('gender')->map(fn($g) => $g->count())->union([
            0 => 0, 1 => 0, 2 => 0
        ])->toArray();

         
                return response()->json([
                    'message' => 'Member entries retrieved successfully',
                    'data' => $memberEntries, // full paginator with transformed collection
                    'gender_counts' => $genderCounts,
                ], 200);

    } catch (ValidationException $e) {
        $allErrors = $e->errors();
        $firstErrorMessage = collect($allErrors)->flatten()->first();
        return response()->json(['message' => $firstErrorMessage, 'errors' => $allErrors], 422);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while retrieving member entries !!',
            'error' => $e->getMessage()
        ], 500);
    }
}





    public function indexWithPagination(Request $request)
    {
        

        try {
           
            $genderMap = [
                0 => 'Male',
                1 => 'Female',
                2 => 'Others',
            ];

            $validated = $request->validate([
                'date_from' => 'nullable|date|regex:/^\d{4}-\d{2}-\d{2}$/',
                'date_to' => 'nullable|date|regex:/^\d{4}-\d{2}-\d{2}$/',
                'search' => 'nullable|string|max:255',
                'gender' => 'nullable|in:0,1,2',
                'occupation_id' => 'nullable|exists:tenant.master_setups,id',
                'demand_phase' => 'nullable|exists:tenant.master_setups,id',
                'demand_capacity' => 'nullable|exists:tenant.master_setups,id',
                'purpose_type' => 'nullable|exists:tenant.master_setups,id',
                'transformer_id' => 'nullable|exists:tenant.master_setups,id',
                'area_id' => 'nullable|exists:tenant.master_setups,id',
                'limit' => 'nullable|integer|min:1|max:1000',
            ]);

            $perPage = $validated['limit'] ?? 50;

            $query = MemberEntry::on('tenant')
               
                ->select(
                    'member_entries.*',
                    'occupation.name_en as occupation_name_en',
                    'occupation.name_np as occupation_name_np',
                    'area.name_en as area_name_en',
                    'area.name_np as area_name_np',
                    'meter_issues.id as meter_issue_id',
                    'meter_issues.meter_no',
                    'meter_issues.meter_start_no',
                    'meter_issues.issue_date_bs',
                    'meter_issues.issue_date_ad',
                    'meter_issues.construct_company',
                    'meter_issues.issue_meter_capacity',
                    'meter_issues.reading_seal_no',
                    'meter_issues.terminal_seal_no',
                    'meter_issues.meter_box_seal_no',
                    'meter_issues.pole_no',
                    'meter_issues.pole_distance',
                    'meter_issues.meter_issue_record',
                    'meter_issues.is_active as meter_issue_is_active',
                    'meter_issues.deleted_at as meter_issue_deleted_at',
                    'meter_issues.created_at as meter_issue_created_at',
                    'meter_issues.updated_at as meter_issue_updated_at',
                    'phase.name_en as demand_phase_name_en',
                    'phase.name_np as demand_phase_name_np',
                    'capacity.name_en as demand_capacity_name_en',
                    'capacity.name_np as demand_capacity_name_np',
                    'purpose.name_en as purpose_type_name_en',
                    'purpose.name_np as purpose_type_name_np',
                    'transformer.name_en as transformer_name_en',
                    'transformer.name_np as transformer_name_np',
                    'meter_area.name_en as reading_area_name_en',
                    'meter_area.name_np as reading_area_name_np'
                )
                ->leftJoin('master_setups as occupation', function ($join) {
                    $join->on('member_entries.occupation_id', '=', 'occupation.id')
                        ->where('occupation.master_setup_type_id', 7)
                        ->where('occupation.is_active', 1)
                        ->whereNull('occupation.deleted_at');
                })
                ->leftJoin('master_setups as area', function ($join) {
                    $join->on('member_entries.area_id', '=', 'area.id')
                        ->where('area.master_setup_type_id', 6)
                        ->where('area.is_active', 1)
                        ->whereNull('area.deleted_at');
                })
                ->leftJoin('meter_issues', function ($join) {
                    $join->on('member_entries.id', '=', 'meter_issues.member_entry_id')
                        ->whereNull('meter_issues.deleted_at');
                })
                ->leftJoin('master_setups as phase', function ($join) {
                    $join->on('meter_issues.phase_id', '=', 'phase.id')
                        ->where('phase.master_setup_type_id', 3)
                        ->where('phase.is_active', 1)
                        ->whereNull('phase.deleted_at');
                })
                ->leftJoin('master_setups as capacity', function ($join) {
                    $join->on('meter_issues.capacity_id', '=', 'capacity.id')
                        ->where('capacity.master_setup_type_id', 4)
                        ->where('capacity.is_active', 1)
                        ->whereNull('capacity.deleted_at');
                })
                ->leftJoin('master_setups as purpose', function ($join) {
                    $join->on('meter_issues.purpose_id', '=', 'purpose.id')
                        ->where('purpose.master_setup_type_id', 1)
                        ->where('purpose.is_active', 1)
                        ->whereNull('purpose.deleted_at');
                })
                ->leftJoin('master_setups as transformer', function ($join) {
                    $join->on('meter_issues.transformer_id', '=', 'transformer.id')
                        ->where('transformer.master_setup_type_id', 5)
                        ->where('transformer.is_active', 1)
                        ->whereNull('transformer.deleted_at');
                })
                ->leftJoin('master_setups as meter_area', function ($join) {
                    $join->on('meter_issues.area_id', '=', 'meter_area.id')
                        ->where('meter_area.master_setup_type_id', 6)
                        ->where('meter_area.is_active', 1)
                        ->whereNull('meter_area.deleted_at');
                });

        
            $filters = [
                'occupation_id' => 'member_entries.occupation_id',
                'area_id' => 'member_entries.area_id',
                'demand_phase' => 'meter_issues.phase_id',
                'demand_capacity' => 'meter_issues.capacity_id',
                'purpose_type' => 'meter_issues.purpose_id',
                'transformer_id' => 'meter_issues.transformer_id',
                'gender' => 'member_entries.gender',
            ];

            foreach ($filters as $param => $column) {
                if ($request->filled($param)) {
                    $query->where($column, $validated[$param]);
                }
            }

            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween('member_entries.created_at', [$validated['date_from'], $validated['date_to']]);
            }

            if ($request->filled('search')) {
                $search = $validated['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('member_entries.member_no', 'like', "%{$search}%")
                      ->orWhere('member_entries.customer_name_en', 'like', "%{$search}%")
                      ->orWhere('member_entries.customer_name_np', 'like', "%{$search}%");
                });
            }

            $memberEntries = $query->paginate($perPage)->through(function ($memberEntry) use ($genderMap) {
                return [
                    'id' => $memberEntry->id,
                    'member_no' => $memberEntry->member_no,
                    'customer_name_en' => $memberEntry->customer_name_en,
                    'customer_name_np' => $memberEntry->customer_name_np,
                    'gender' => $genderMap[$memberEntry->gender] ?? 'Others',
                    'occupation' => [
                        'id' => $memberEntry->occupation_id,
                        'name_en' => $memberEntry->occupation_name_en,
                        'name_np' => $memberEntry->occupation_name_np,
                    ],
                    'area' => [
                        'id' => $memberEntry->area_id,
                        'name_en' => $memberEntry->area_name_en,
                        'name_np' => $memberEntry->area_name_np,
                    ],
                    'meter_issues' => $memberEntry->meter_issue_id ? [
                        'id' => $memberEntry->meter_issue_id,
                        'meter_no' => $memberEntry->meter_no,
                        'phase' => [
                            'id' => $memberEntry->phase_id,
                            'name_en' => $memberEntry->demand_phase_name_en,
                            'name_np' => $memberEntry->demand_phase_name_np,
                        ],
                        'capacity' => [
                            'id' => $memberEntry->capacity_id,
                            'name_en' => $memberEntry->demand_capacity_name_en,
                            'name_np' => $memberEntry->demand_capacity_name_np,
                        ],
                        'purpose' => [
                            'id' => $memberEntry->purpose_id,
                            'name_en' => $memberEntry->purpose_type_name_en,
                            'name_np' => $memberEntry->purpose_type_name_np,
                        ],
                        'transformer' => [
                            'id' => $memberEntry->transformer_id,
                            'name_en' => $memberEntry->transformer_name_en,
                            'name_np' => $memberEntry->transformer_name_np,
                        ],
                        'reading_area' => [
                            'id' => $memberEntry->area_id,
                            'name_en' => $memberEntry->reading_area_name_en,
                            'name_np' => $memberEntry->reading_area_name_np,
                        ],
                    ] : null,
                ];
            });

            return response()->json([
                'message' => 'Member entries retrieved successfully',
                'total_entries' => $memberEntries->total(),
            ] + $memberEntries->toArray());

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();
            return response()->json(['message' => $firstErrorMessage, 'errors' => $allErrors], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while retrieving member entries', 'error' => $e->getMessage()], 500);
        }
    }




}
