<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\FiscalYear;
use App\Models\MemberEntry;
use App\Models\MasterSetup;
use App\Models\MeterIssue;

use App\Models\MeterReadingEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Pratiksh\Nepalidate\Services\EnglishDate;
use Illuminate\Validation\Rule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Maatwebsite\Excel\Facades\Excel;
use App\Helpers\Helper;
use App\Helpers\TenantRuntimeHelper;
use App\Services\KnowMeterStartService;

class MeterIssueController extends Controller
{


    public function getById(Request $request, $id)
    {


        try {
            $entry = MeterIssue::with(['memberEntry:id,member_no,customer_name_en,customer_name_np'])
                ->findOrFail($id);
            $meterStartService = new KnowMeterStartService();
            $currentUnit = $meterStartService->getMeterStartNo($entry->id);

            $response = [
                'id' => $entry->id,
                'member_no' => $entry->memberEntry?->member_no,
                'customer_name_en' => $entry->memberEntry?->customer_name_en,
                'customer_name_np' => $entry->memberEntry?->customer_name_np,
               
                ...(
                    TenantRuntimeHelper::isRuntimeKhanepani()
                    ? []
                    : [
                        'phase_id' => $entry->phase_id,
                        'capacity_id' => $entry->capacity_id,
                        'purpose_id' => $entry->purpose_id,
                        'issue_meter_capacity' => $entry->issue_meter_capacity,
                    ]
                ),


                'meter_no' => $entry->meter_no,
                'transformer_id' => $entry->transformer_id,
                'meter_start_no' => $currentUnit,
                'issue_date_bs' => $entry->issue_date_bs,
                'issue_date_ad' => $entry->issue_date_ad,
                'construct_company' => $entry->construct_company,
                
                'reading_seal_no' => $entry->reading_seal_no,
                'terminal_seal_no' => $entry->terminal_seal_no,
                'meter_box_seal_no' => $entry->meter_box_seal_no,
                'pole_no' => $entry->pole_no,
                'pole_distance' => $entry->pole_distance,
                'area_id' => $entry->area_id,
                'meter_issue_record' => $entry->meter_issue_record,
                'is_active' => $entry->is_active,
                'deleted_at' => $entry->deleted_at,
                'created_at' => $entry->created_at,
                'updated_at' => $entry->updated_at,
            ];

            return response()->json(['meter_issue' => $response], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Meter issue not found or already deleted',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the meter issue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function toggleActiveStatus(Request $request, $id)
    {

        try {
            $meterIssue = MeterIssue::findOrFail($id);

            $meterIssue->is_active = !$meterIssue->is_active;
            $meterIssue->save();
            return response()->json([
                'message' => "Meter issue {$meterIssue->customer_name_en} active status updated to " . ($meterIssue->is_active ? 'active' : 'inactive'),
                'is_active' => $meterIssue->is_active
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Meter issue not found or already deleted'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while toggling the meter issue active status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function searchCustomers(Request $request)
    {


        try {
            $searchTerm = $request->input('search');

            $customers = MemberEntry::where('is_active', 1)
                ->where(function ($query) use ($searchTerm) {
                    $query->where('member_no', 'like', "%{$searchTerm}%")
                        ->orWhere('customer_name_en', 'like', "%{$searchTerm}%")
                        ->orWhere('customer_name_np', 'like', "%{$searchTerm}%");
                })
                ->whereDoesntHave('meterIssues', function ($query) {
                    $query->whereNull('deleted_at');
                })
                ->select('id', 'member_no', 'customer_name_en', 'customer_name_np', 'area_id')
                ->get()
                ->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'member_no' => $customer->member_no,
                        'customer_name_en' => $customer->customer_name_en ?? 'N/A',
                        'customer_name_np' => $customer->customer_name_np ?? 'N/A',
                        'area_id' => $customer->area_id ?? 'N/A',
                    ];
                });

            if ($customers->isEmpty()) {
                return response()->json([
                    'message' => 'No customers found for the given search term',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'message' => 'Customers retrieved successfully',
                'data' => $customers,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while searching customers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function listMeterIssues(Request $request)
    {


        try {
            $search = $request->query('search');
            $phaseId = $request->query('phase_id');
            $capacityId = $request->query('capacity_id');
            $purposeId = $request->query('purpose_id');

           
            $entriesQuery = MeterIssue::with([
                'memberEntry:id,member_no,customer_name_en,customer_name_np',
                 'readingArea:id,name_en,name_np',
                    'transformer:id,name_en,name_np',
            ]);

            if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
                $entriesQuery->with([
                    'demandPhase:id,name_en,name_np',
                    'demandCapacity:id,name_en,name_np',
                    'purposeType:id,name_en,name_np',
                   
                ]);
            }


            if (!empty($search)) {
                $entriesQuery->whereHas('memberEntry', function ($query) use ($search) {

                    if (is_numeric($search)) {
                        $query->where('member_no', $search);
                    } else {
                        $query->where(function ($q) use ($search) {
                            $q->where('customer_name_en', 'like', "%{$search}%")
                            ->orWhere('customer_name_np', 'like', "%{$search}%");
                        });
                    }

                });
            }

            // if (!empty($phaseId) && $phaseId !== 'undefined') {
            //     $entriesQuery->where('phase_id', $phaseId);
            // }

            // if (!empty($capacityId) && $capacityId !== 'undefined') {
            //     $entriesQuery->where('capacity_id', $capacityId);
            // }

            // if (!empty($purposeId) && $purposeId !== 'undefined') {
            //     $entriesQuery->where('purpose_id', $purposeId);
            // }
            if (!TenantRuntimeHelper::isRuntimeKhanepani()) {

                if (!empty($phaseId) && $phaseId !== 'undefined') {
                    $entriesQuery->where('phase_id', $phaseId);
                }

                if (!empty($capacityId) && $capacityId !== 'undefined') {
                    $entriesQuery->where('capacity_id', $capacityId);
                }

                if (!empty($purposeId) && $purposeId !== 'undefined') {
                    $entriesQuery->where('purpose_id', $purposeId);
                }
            }
           $entriesQuery->orderBy('id', 'desc');
            $entries = $entriesQuery->paginate(10);
            $meterStartService = new KnowMeterStartService();

            $entries->getCollection()->transform(function ($entry) use ($meterStartService) {
                $entry->customer_name_en = $entry->memberEntry->customer_name_en ?? null;
                $entry->customer_name_np = $entry->memberEntry->customer_name_np ?? null;
                $currentUnit = $meterStartService->getMeterStartNo($entry->id);

                unset($entry->memberEntry);

                return [
                    'id' => $entry->id,
                    'member_no' => $entry->memberEntry->member_no ?? null,
                    'customer_name_en' => $entry->customer_name_en,
                    'customer_name_np' => $entry->customer_name_np,
                  
                    ...(
                        TenantRuntimeHelper::isRuntimeKhanepani()
                        ? []
                        : [
                            'phase_id' => optional($entry->demandPhase)->id,
                            'phase_name_en' => optional($entry->demandPhase)->name_en,
                            'phase_name_np' => optional($entry->demandPhase)->name_np,

                            'capacity_id' => optional($entry->demandCapacity)->id,
                            'capacity_name_en' => optional($entry->demandCapacity)->name_en,
                            'capacity_name_np' => optional($entry->demandCapacity)->name_np,

                            'purpose_id' => optional($entry->purposeType)->id,
                            'purpose_name_en' => optional($entry->purposeType)->name_en,
                            'purpose_name_np' => optional($entry->purposeType)->name_np,
                             'issue_meter_capacity' => $entry->issue_meter_capacity,
                        ]
                    ),
                    'meter_no' => $entry->meter_no,
                    'transformer_id' => $entry->transformer->id,
                    'transformer_name_en' => $entry->transformer->name_en ?? null,
                    'transformer_name_np' => $entry->transformer->name_np ?? null,
                    'meter_start_no' => $currentUnit,
                    'issue_date_bs' => $entry->issue_date_bs,
                    'issue_date_ad' => $entry->issue_date_ad,
                    'construct_company' => $entry->construct_company,
                   
                    'reading_seal_no' => $entry->reading_seal_no,
                    'terminal_seal_no' => $entry->terminal_seal_no,
                    'meter_box_seal_no' => $entry->meter_box_seal_no,
                    'pole_no' => $entry->pole_no,
                    'pole_distance' => $entry->pole_distance,
                    'area_id' => $entry->readingArea->id ?? null,
                    'area_name_en' => $entry->readingArea->name_en ?? null,
                    'area_name_np' => $entry->readingArea->name_np ?? null,
                    'meter_issue_record' => $entry->meter_issue_record,
                    'is_active' => $entry->is_active,
                    'deleted_at' => $entry->deleted_at,
                    'created_at' => $entry->created_at,
                    'updated_at' => $entry->updated_at,
                ];
            });

            return response()->json($entries);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing meter issues',
                'error' => $e->getMessage(),
            ], 500);
        }
    }






    public function createMeterIssue(Request $request)
    {


        try {
              $rules = [

                'member_entry_id' => [
                    'required',
                    'integer',
                    'exists:tenant.member_entries,id,deleted_at,NULL,is_active,1',
                    function ($attribute, $value, $fail) {

                        $memberEntry = MemberEntry::on('tenant')
                            ->where('id', $value)

                            ->where('is_active', 1)
                            ->first();

                        if (!$memberEntry) {
                            return $fail("Invalid member number.");
                        }


                        $exists = MeterIssue::on('tenant')

                            ->where('member_entry_id', $memberEntry->id)
                            ->exists();

                        if ($exists) {
                            $fail("A meter has already been issued for this member.");
                        }
                    },
                ],
                
               

                'meter_no' => [
                    'required',
                    'string',
                    'max:15',
                    Rule::unique('tenant.meter_issues', 'meter_no'),
                ],
                'transformer_id' => [
                    'required',
                    'integer',
                    'exists:tenant.master_setups,id',
                    function ($attribute, $value, $fail) {
                        if (
                            !MasterSetup::on('tenant')->where('id', $value)
                                ->where('master_setup_type_id', 5)
                                ->where('is_active', 1)
                                ->whereNull('deleted_at')
                                ->exists()
                        ) {
                            $fail('The selected transformer must have type Transformer, be active, and not deleted !');
                        }
                    },
                ],
                'meter_start_no' => 'required|numeric|min:0',

                'issue_date_bs' => [
                    'required',
                    'string',
                    'max:10',
                    'regex:/^\d{4}-\d{2}-\d{2}$/',
                    function ($attribute, $value, $fail) {
                        try {
                            $adDate = \App\Helpers\NepaliCalendar::bsToAd($value);
                            $today = date('Y-m-d');
                            if ($adDate > $today) {
                                $fail('The ' . $attribute . ' cannot be a future date.');
                            }
                        } catch (\Exception $e) {
                            $fail('The ' . $attribute . ' is not a valid BS date.');
                        }
                    },
                ],
                'issue_date_ad' => [
                    'required',
                    'date',
                    function ($attribute, $value, $fail) {
                        $today = date('Y-m-d');
                        if ($value > $today) {
                            $fail('The ' . $attribute . ' cannot be a future date !');
                        }
                    },
                ],
                'construct_company' => 'nullable|string|max:50',
                
                'reading_seal_no' => 'nullable|string|max:50',
                'terminal_seal_no' => 'nullable|string|max:50',
                'meter_box_seal_no' => 'nullable|string|max:50',
                'pole_no' => 'nullable|string|max:50',
                'pole_distance' => 'nullable|string|max:100',
                'area_id' => [
                    'required',
                    'integer',
                    'exists:tenant.master_setups,id',
                    function ($attribute, $value, $fail) {
                        if (
                            !MasterSetup::on('tenant')->where('id', $value)
                                ->where('master_setup_type_id', 6)
                                ->where('is_active', 1)
                                ->whereNull('deleted_at')
                                ->exists()
                        ) {
                            $fail('The selected reading_area must have type Area, be active, and not deleted.');
                        }
                    },
                ],
                'is_active' => 'sometimes|boolean',
           ];
                    if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
                                $rules['phase_id'] = [
                                    'required',
                                    'integer',
                                    'exists:tenant.master_setups,id',
                                    function ($attribute, $value, $fail) {
                                        if (
                                            !MasterSetup::on('tenant')->where('id', $value)
                                                ->where('master_setup_type_id', 3)
                                                ->where('is_active', 1)
                                                ->whereNull('deleted_at')
                                                ->exists()
                                        ) {
                                            $fail('The selected demand_phase must have type Phase, be active, and not deleted !');
                                        }
                                    },
                                ];
                                $rules['capacity_id'] = [
                                    'required',
                                    'integer',
                                    'exists:tenant.master_setups,id',
                                    function ($attribute, $value, $fail) {
                                        if (
                                            !MasterSetup::on('tenant')->where('id', $value)
                                                ->where('master_setup_type_id', 4)
                                                ->where('is_active', 1)
                                                ->whereNull('deleted_at')
                                                ->exists()
                                        ) {
                                            $fail('The selected demand_capacity must have type Capacity, be active, and not deleted.');
                                        }
                                    },
                                ];
                                $rules['purpose_id'] = [
                                    'required',
                                    'integer',
                                    'exists:tenant.master_setups,id',
                                    function ($attribute, $value, $fail) {
                                        if (
                                            !MasterSetup::on('tenant')->where('id', $value)
                                                ->where('master_setup_type_id', 1)
                                                ->where('is_active', 1)
                                                ->whereNull('deleted_at')
                                                ->exists()
                                        ) {
                                            $fail('The selected purpose_type must have type Purpose of Use, be active, and not deleted.');
                                        }
                                    },
                                ];
                                $rules['issue_meter_capacity'] = 'nullable|string|max:50';
                            }


                 $validated = $request->validate($rules);

            $memberEntry = MemberEntry::on('tenant')
                ->where('id', $validated['member_entry_id'])
                ->where('is_active', 1)

                ->first();

            if (!$memberEntry) {
                return response()->json([
                    'message' => 'Active MemberEntry not found for the provided customer_id',
                ], 422);
            }

            $validated['meter_issue_record'] = 0;

            $fiscalYearId = Helper::getActiveFiscalYearId();
            $validated['fiscal_year_id'] = $fiscalYearId;
            $validated['member_entry_id'] = $memberEntry->id;
            $meterIssue = MeterIssue::on('tenant')->create($validated);

            return response()->json([
                'message' => "Meter issue created successfully",
                'data' => $meterIssue->toArray()
            ], 201);

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();
            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the meter issue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function editMeterIssue(Request $request, $id)
    {


        try {

            $meterIssue = MeterIssue::with([
                'meterReadingEntries' => function ($query) {
                    $query->whereNull('deleted_at');
                }
            ])->findOrFail($id);

             $rules = [

                // 'phase_id' => [
                //     'sometimes',
                //     'required',
                //     'integer',
                //     'exists:tenant.master_setups,id',
                //     function ($attr, $val, $fail) {
                //         if (
                //             !MasterSetup::on('tenant')->where('id', $val)
                //                 ->where('master_setup_type_id', 3)
                //                 ->where('is_active', 1)
                //                 ->whereNull('deleted_at')
                //                 ->exists()
                //         ) {
                //             $fail('The selected demand_phase must have type Phase, be active, and not deleted.');
                //         }
                //     },
                // ],

                // 'capacity_id' => [
                //     'sometimes',
                //     'required',
                //     'integer',
                //     'exists:tenant.master_setups,id',
                //     function ($attr, $val, $fail) {
                //         if (
                //             !MasterSetup::on('tenant')->where('id', $val)
                //                 ->where('master_setup_type_id', 4)
                //                 ->where('is_active', 1)
                //                 ->whereNull('deleted_at')
                //                 ->exists()
                //         ) {
                //             $fail('The selected demand_capacity must have type Capacity, be active, and not deleted.');
                //         }
                //     },
                // ],

                // 'purpose_id' => [
                //     'sometimes',
                //     'required',
                //     'integer',
                //     'exists:tenant.master_setups,id',
                //     function ($attr, $val, $fail) {
                //         if (
                //             !MasterSetup::on('tenant')->where('id', $val)
                //                 ->where('master_setup_type_id', 1)
                //                 ->where('is_active', 1)
                //                 ->whereNull('deleted_at')
                //                 ->exists()
                //         ) {
                //             $fail('The selected purpose_type must have type Purpose of Use, be active, and not deleted.');
                //         }
                //     },
                // ],

                'meter_no' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:15',
                    Rule::unique('tenant.meter_issues', 'meter_no')
                        ->whereNull('deleted_at')
                        ->ignore($meterIssue->id),
                ],


                'transformer_id' => [
                    'sometimes',
                    'required',
                    'integer',
                    'exists:tenant.master_setups,id',
                    function ($attr, $val, $fail) {
                        if (
                            !MasterSetup::on('tenant')->where('id', $val)
                                ->where('master_setup_type_id', 5)
                                ->where('is_active', 1)
                                ->whereNull('deleted_at')
                                ->exists()
                        ) {
                            $fail('The selected transformer must have type Transformer, be active, and not deleted.');
                        }
                    },
                ],

                // 'meter_start_no' => [
                //     'sometimes',
                //     'required',
                //     'numeric',
                //     'min:0',
                //     function ($attr, $val, $fail) use ($meterIssue) {
                //         $originalStartNo = $meterIssue->meter_start_no;
                //         $hasActiveReadings = $meterIssue->meterReadingEntries()->count() > 0;

                //         if ($val != $originalStartNo && $hasActiveReadings) {
                //             $fail('Cannot change meter_start_no when there are active meter reading entries associated with this meter issue.');
                //         }
                //     },
                // ],
                'meter_start_no' => [
                    'sometimes',
                    'required',
                    'numeric',
                    'min:0',
                ],

                'issue_date_bs' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:10',
                    'regex:/^\d{4}-\d{2}-\d{2}$/',
                    function ($attribute, $value, $fail) {
                        try {
                            $adDate = \App\Helpers\NepaliCalendar::bsToAd($value);
                            $today = date('Y-m-d');
                            if ($adDate > $today) {
                                $fail('The ' . $attribute . ' cannot be a future date.');
                            }
                        } catch (\Exception $e) {
                            $fail('The ' . $attribute . ' is not a valid BS date.');
                        }
                    },
                ],
                'issue_date_ad' => [
                    'sometimes',
                    'required',
                    'date',
                    function ($attribute, $value, $fail) {
                        $today = date('Y-m-d');
                        if ($value > $today) {
                            $fail('The ' . $attribute . ' cannot be a future date.');
                        }
                    },
                ],
                'construct_company' => 'sometimes|nullable|string|max:50',
                
                'reading_seal_no' => 'sometimes|nullable|string|max:50',
                'terminal_seal_no' => 'sometimes|nullable|string|max:50',
                'meter_box_seal_no' => 'sometimes|nullable|string|max:50',
                'pole_no' => 'sometimes|nullable|string|max:50',
                'pole_distance' => 'sometimes|nullable|string|max:100',

                'area_id' => [
                    'sometimes',
                    'required',
                    'integer',
                    'exists:tenant.master_setups,id',
                    function ($attr, $val, $fail) {
                        if (
                            !MasterSetup::on('tenant')->where('id', $val)
                                ->where('master_setup_type_id', 6)
                                ->where('is_active', 1)
                                ->whereNull('deleted_at')
                                ->exists()
                        ) {
                            $fail('The selected reading_area must have type Area, be active, and not deleted.');
                        }
                    },
                ],

                'is_active' => 'sometimes|boolean',
              ];
                if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
                            $rules['phase_id'] = [
                                'sometimes',
                                'required',
                                'integer',
                                'exists:tenant.master_setups,id',
                                function ($attr, $val, $fail) {
                                    if (
                                        !MasterSetup::on('tenant')->where('id', $val)
                                            ->where('master_setup_type_id', 3)
                                            ->where('is_active', 1)
                                            ->whereNull('deleted_at')
                                            ->exists()
                                    ) {
                                        $fail('The selected demand_phase must have type Phase, be active, and not deleted.');
                                    }
                                },
                            ];
                            $rules['capacity_id'] = [
                                'sometimes',
                                'required',
                                'integer',
                                'exists:tenant.master_setups,id',
                                function ($attr, $val, $fail) {
                                    if (
                                        !MasterSetup::on('tenant')->where('id', $val)
                                            ->where('master_setup_type_id', 4)
                                            ->where('is_active', 1)
                                            ->whereNull('deleted_at')
                                            ->exists()
                                    ) {
                                        $fail('The selected demand_capacity must have type Capacity, be active, and not deleted.');
                                    }
                                },
                            ];
                            $rules['purpose_id'] = [
                                'sometimes',
                                'required',
                                'integer',
                                'exists:tenant.master_setups,id',
                                function ($attr, $val, $fail) {
                                    if (
                                        !MasterSetup::on('tenant')->where('id', $val)
                                            ->where('master_setup_type_id', 1)
                                            ->where('is_active', 1)
                                            ->whereNull('deleted_at')
                                            ->exists()
                                    ) {
                                        $fail('The selected purpose_type must have type Purpose of Use, be active, and not deleted.');
                                    }
                                },
                            ];
                            $rules['issue_meter_capacity'] = 'nullable|string|max:50';
                        }

                        $validated = $request->validate($rules);

            if (isset($validated['issue_date_bs'])) {
                $validated['issue_date_ad'] = EnglishDate::create($validated['issue_date_bs'])->toAD();
            }



            // Ignore meter_start_no change if readings already exist
            if (array_key_exists('meter_start_no', $validated)) {

                $hasReadings = MeterReadingEntry::where('meter_issue_id', $meterIssue->id)
                    ->whereNull('deleted_at')
                    ->where('entry_type', 1)
                    ->exists();

                if ($hasReadings) {
                    // Keep original value (ignore new one)
                    $validated['meter_start_no'] = $meterIssue->getOriginal('meter_start_no');
                }
            }

            $meterIssue->update($validated);
            return response()->json([
                'message' => "Meter issue for member_no {$meterIssue->member_entry_id} updated successfully !!",
                'data' => $meterIssue->fresh()->toArray()
            ], 200);

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();
            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the meter issue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function deleteMeterIssue(Request $request, $id)
    {


        try {
            $entry = MeterIssue::findOrFail($id);

            $hasMeterReading = MeterReadingEntry::where('meter_issue_id', $entry->id)
                ->withoutTrashed()
                ->exists();

            if ($hasMeterReading) {
                return response()->json([
                    'message' => 'Cannot delete meter issue because related non-deleted MeterReadingEntry records exist for this customer.'
                ], 422);
            }

            $entry->delete();

            return response()->json([
                'message' => 'Meter issue deleted successfully',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Meter issue not found or already deleted',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the meter issue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function listCustomeronly(Request $request, $id)
    {


        try {
            $customer = MemberEntry::where('is_active', 1)
                ->findOrFail($id);

            $response = [
                'id' => $customer->id,
                'member_no' => $customer->member_no,
                'customer_name_en' => $customer->customer_name_en,
                'customer_name_np' => $customer->customer_name_np,
            ];

            return response()->json(['meter_issue' => $response]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while listing customer'], 500);
        }
    }



    public function listPhaseNames(Request $request)
    {
        try {
            $phaseNames = MasterSetup::select('id', 'name_en')
                ->where('master_setup_type_id', 3)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->get();


            return response()->json([
                'message' => 'Phase names retrieved successfully',
                'data' => $phaseNames,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving phase names',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List name_en for master_setups with type Capacity
     */
    public function listCapacityNames(Request $request)
    {
        try {
            $capacityNames = MasterSetup::select('id', 'name_en')
                ->where('master_setup_type_id', 4)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->get();
            return response()->json([
                'message' => 'Capacity names retrieved successfully',
                'data' => $capacityNames,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving capacity names',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List name_en for master_setups with type Purpose of Use
     */
    public function listPurposeNames(Request $request)
    {
        try {
            $purposeNames = MasterSetup::select('id', 'name_en')
                ->where('master_setup_type_id', 1)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->get();

            return response()->json([
                'message' => 'Purpose of use names retrieved successfully',
                'data' => $purposeNames,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving purpose names',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List name_en for master_setups with type Transformer
     */
    public function listTransformerNames(Request $request)
    {
        try {
            $transformerNames = MasterSetup::select('id', 'name_en')
                ->where('master_setup_type_id', 5)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->get();

            return response()->json([
                'message' => 'Transformer names retrieved successfully',
                'data' => $transformerNames,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving transformer names',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List name_en for master_setups with type Area
     */
    public function listAreaNames(Request $request)
    {
        try {
            $areaNames = MasterSetup::select('id', 'name_en')
                ->where('master_setup_type_id', 6)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->get();


            return response()->json([
                'message' => 'Area names retrieved successfully',
                'data' => $areaNames,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving area names',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function importExcel(Request $request)
    {


        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
            ]);

            $import = new \App\Imports\MeterIssueImport;

            Excel::import($import, $request->file('file'));

            return response()->json([
                'message' => 'Excel imported successfully',
                'imported' => $import->importedCount ?? 0,
                'failed' => count($import->failedRows ?? []),
                'failed_rows' => $import->failedRows ?? [],
            ], 200);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $firstErrorMessage = $failures[0]->errors()[0] ?? 'Excel validation failed';

            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => ['excel_validation' => $failures],
            ], 422);

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();

            return response()->json([
                'message' => $firstErrorMessage ?: 'Validation failed',
                'errors' => $allErrors
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error importing Excel',
                'errors' => ['exception' => [$e->getMessage()]],
            ], 500);
        }
    }
public function getMeterIssueImportFieldNames()
{
    try {
        $fields = [
            'member_entry_id',
            'meter_no',
            'transformer_id',
            'meter_start_no',
            'issue_date_bs',
            'issue_date_ad',
            'construct_company',
           
            'reading_seal_no',
            'terminal_seal_no',
            'meter_box_seal_no',
            'pole_no',
            'pole_distance',
            'area_id',
             ...(
                    TenantRuntimeHelper::isRuntimeKhanepani()
                    ? []
                    : [
                       'phase_id',     // only for non-Khanepani
                        'capacity_id',  // only for non-Khanepani
                        'purpose_id',   // only for non-Khanepani
                         'issue_meter_capacity',
                    ]
                ),
            'is_active',
        ];

        return response()->json([
            'data' => $fields,
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'status' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}


}
