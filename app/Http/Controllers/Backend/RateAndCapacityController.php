<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\TenantRuntimeHelper;
use App\Http\Controllers\Controller;
use App\Models\MeterIssue;
use App\Models\RateAndCapacity;
use App\Models\MeterReadingEntry;
use App\Models\MasterSetup;
use App\Models\TariffSetup;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
class RateAndCapacityController extends Controller
{


    public function listRatesAndCapacities(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view rates and capacities')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $search = $request->query('search');

            $query = RateAndCapacity::query()->orderBy('created_at', 'desc');

            if (!empty($search)) {
                $query->where('id', $search);
            }

            $entries = $query->paginate(10);

            return response()->json($entries);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing rates and capacities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function getById(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('view rates and capacities')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }


        try {
            $entry = RateAndCapacity::all()->findOrFail($id);
            return response()->json(['rate_capacity' => $entry], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Rate and capacity entry not found or already deleted',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the rate and capacity entry',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }


    public function listRules(Request $request)
    {
        try {
            $rules = TariffSetup::select('id', 'rule_name')
                ->whereNull('deleted_at')
                ->get();
            return response()->json([
                'message' => 'Rule names retrieved successfully',
                'data' => $rules,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving rule names',
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

    public function listPhaseNames(Request $request)
    {
        try {
            $phaseNames = MasterSetup::select('id', 'name_en')
                ->where('master_setup_type_id', 3)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->get();
            return response()->json([
                'message' => 'Phase of use names retrieved successfully',
                'data' => $phaseNames,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving phase names',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
 public function listChargesForCombination(Request $request)
{
    try {

        $activeTariff = TariffSetup::where('is_active', 1)->first();
        if (!$activeTariff) {
            return response()->json(['message' => 'No active tariff setup found'], 422);
        }

        $ruleId = $activeTariff->id;

        $isKhanepani = TenantRuntimeHelper::isRuntimeKhanepani();

        if ($isKhanepani) {

           

            $record = RateAndCapacity::where('tariff_setup_id', $ruleId)
                ->orderByDesc('unit_to') 
                ->first();

        } else {

            $validated = $request->validate([
                'phase' => [
                    'required',
                    'exists:tenant.master_setups,id',
                    function ($attribute, $value, $fail) {
                        $exists = MasterSetup::where('id', $value)
                            ->where('master_setup_type_id', 3)
                            ->where('is_active', 1)
                            ->whereNull('deleted_at')
                            ->exists();

                        if (!$exists) {
                            $fail('The selected phase must be an active Phase record.');
                        }
                    },
                ],
                'capacity' => [
                    'required',
                    'exists:tenant.master_setups,id',
                    function ($attribute, $value, $fail) {
                        $exists = MasterSetup::where('id', $value)
                            ->where('master_setup_type_id', 4)
                            ->where('is_active', 1)
                            ->whereNull('deleted_at')
                            ->exists();

                        if (!$exists) {
                            $fail('The selected capacity must be an active Capacity record.');
                        }
                    },
                ],
                'purpose' => [
                    'required',
                    'exists:tenant.master_setups,id',
                    function ($attribute, $value, $fail) {
                        $exists = MasterSetup::where('id', $value)
                            ->where('master_setup_type_id', 1)
                            ->where('is_active', 1)
                            ->whereNull('deleted_at')
                            ->exists();

                        if (!$exists) {
                            $fail('The selected purpose must be an active Purpose record.');
                        }
                    },
                ],
            ]);

            $record = RateAndCapacity::where('phase_id', $validated['phase'])
                ->where('capacity_id', $validated['capacity'])
                ->where('purpose_id', $validated['purpose'])
                ->where('tariff_setup_id', $ruleId)
                ->orderByDesc('unit_to')
                ->first();
        }

        $charges = [
            'minimum_demand' => $record ? $record->minimum_demand : 0,
            'subsidy_charge' => $record ? $record->subsidy_charge : 0,
            'service_charge' => $record ? $record->service_charge : 0,
            'other_charge' => $record ? $record->other_charge : 0,
        ];

        return response()->json([
            'message' => 'Charges retrieved successfully',
            'data' => $charges,
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'message' => collect($e->errors())->flatten()->first(),
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while retrieving charges',
            'error' => $e->getMessage(),
        ], 500);
    }
}
    public function getRatesByPhaseCapacityPurpose(Request $request)
    {
        try {
             if (TenantRuntimeHelper::isRuntimeKhanepani()) {
                 $activeTariff = TariffSetup::all()->where('is_active', 1)->firstOrFail();

            $records = RateAndCapacity::query()
                ->where('tariff_setup_id', $activeTariff->id)
                ->orderBy('unit_from', 'ASC')
                ->get();
             }else{
            $validator = Validator::make($request->all(), [
                'phase_id' => [
                    'required',
                    'exists:tenant.master_setups,id',
                    function ($attribute, $value, $fail) {
                        $exists = MasterSetup::where('id', $value)
                            ->where('master_setup_type_id', 3)
                            ->whereNull('deleted_at')
                            ->exists();
                        if (!$exists) {
                            $fail('The selected phase must be an active Phase record in master_setups.');
                        }
                    },
                ],
                'capacity_id' => [
                    'required',
                    'exists:tenant.master_setups,id',
                    function ($attribute, $value, $fail) {
                        $exists = MasterSetup::where('id', $value)
                            ->where('master_setup_type_id', 4)
                            ->whereNull('deleted_at')
                            ->exists();
                        if (!$exists) {
                            $fail('The selected capacity must be an active Capacity record in master_setups.');
                        }
                    },
                ],
                'purpose_id' => [
                    'required',
                    'exists:tenant.master_setups,id',
                    function ($attribute, $value, $fail) {
                        $exists = MasterSetup::where('id', $value)
                            ->where('master_setup_type_id', 1)
                            ->whereNull('deleted_at')
                            ->exists();
                        if (!$exists) {
                            $fail('The selected purpose must be an active Purpose of Use record in master_setups.');
                        }
                    },
                ],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $activeTariff = TariffSetup::all()->where('is_active', 1)->firstOrFail();

            $records = RateAndCapacity::query()
                ->where('tariff_setup_id', $activeTariff->id)
                ->where('phase_id', $request->phase_id)
                ->where('capacity_id', $request->capacity_id)
                ->where('purpose_id', $request->purpose_id)
                ->orderBy('unit_from', 'ASC')
                ->get();
        }


            return response()->json([
                'tariff_setup' => $activeTariff->toArray(),
                'records' => $records->toArray(),
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'No active tariff setup found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving Rate and Capacity records',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    public function createRateAndCapacity(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('create rates and capacities')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $allRecords = RateAndCapacity::get()->toArray();

            $activeTariff = TariffSetup::where('is_active', 1)->first();
            if (!$activeTariff) {
                return response()->json(['message' => 'No active tariff setup found'], 422);
            }
            $ruleId = $activeTariff->id;



         if (TenantRuntimeHelper::isRuntimeKhanepani()) {
            $validated = $request->validate([
                'unit_from' => [
                    'required',
                    'numeric',
                    'gte:0',
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                        $unitFrom = (float) $value;
                        $lastRecord = RateAndCapacity::where('tariff_setup_id', $ruleId)
                            ->orderByDesc('unit_to')
                            ->first();

                        if ($lastRecord) {
                            $expectedUnitFrom = (float) $lastRecord->unit_to;
                            if (abs($unitFrom - $expectedUnitFrom) > 0.1) {
                                $fail("The unit_from must equal the previous unit_to ($expectedUnitFrom) for this Rule Setup.");
                            }
                        } elseif ($unitFrom !== 0.0) {
                            $fail('The unit_from must be 0 for a new combination.');
                        }
                    },
                ],
                'unit_to' => [
                    'required',
                    'numeric',
                    'max:100000',
                    'gt:unit_from',
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                        $unitFrom = (float) $request->input('unit_from');
                        $unitTo = (float) $value;

                        $overlappingRecords = RateAndCapacity::where('tariff_setup_id', $ruleId)
                            ->where(function ($query) use ($unitFrom, $unitTo) {
                                $query->where('unit_from', '<', $unitTo)
                                    ->where('unit_to', '>', $unitFrom);
                            })
                            ->get();

                        if ($overlappingRecords->isNotEmpty()) {
                            $fail('The unit range overlaps with an existing active record for this Rule Setup.');
                        }
                    },
                ],
                
                'rate_per_unit' => [
                    'required',
                    'numeric',
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                         if ($ruleId != 4 && $value <= 0) {
                            $fail('The rate_per_unit must be greater than 0.');
                        }

                        if ($ruleId == 4 && $value < 0) {
                            $fail('The rate_per_unit cannot be negative.');
                        }
                        $isLumpsum = $request->input('is_lumpsum', 0);

                        
                        $records = RateAndCapacity::where('tariff_setup_id', $ruleId)
                        ->where('is_lumpsum', $isLumpsum) 
                        ->orderBy('unit_from')
                        ->get();

                        $count = $records->count();
                        $previous = $records->last();

                        if ($ruleId == 4 && $isLumpsum == 1) {

                            // 1st record → allow
                            if ($count == 0) {
                                return;
                            }

                            $first = $records->first();
                            $previous = $records->last();

                            if ($count == 1) {
                                if ($value > (float) $first->rate_per_unit) {
                                    $fail("The rate_per_unit for the second record cannot be greater than the first record ({$first->rate_per_unit}).");
                                }
                                return;
                            }
                            // 3rd onward → enforce rule
                            if ($previous && $value <= (float) $previous->rate_per_unit) {
                                $fail("The rate_per_unit must be greater than the previous record's rate_per_unit ({$previous->rate_per_unit}).");
                            }

                        } else {
                            if ($previous && $value <= (float) $previous->rate_per_unit) {
                                $fail("The rate_per_unit must be greater than the previous record's rate_per_unit ({$previous->rate_per_unit}).");
                            }
                        }
                    },
                ],
                // 'rate_per_unit' => [
                //         'required',
                //         'numeric',
                //         function ($attribute, $value, $fail) use ($request, $ruleId) {

                //             $isLumpsum = $request->input('is_lumpsum', 0);

                //             if ($ruleId != 4 && $value <= 0) {
                //                 $fail('The rate_per_unit must be greater than 0.');
                //             }

                //             if ($ruleId == 4 && $value < 0) {
                //                 $fail('The rate_per_unit cannot be negative.');
                //             }

                //             $records = RateAndCapacity::where('tariff_setup_id', $ruleId)
                //                 ->where('is_lumpsum', $isLumpsum)
                //                 ->orderBy('unit_from')
                //                 ->get();

                //             $count = $records->count();

                //             //  Rule: tariff 4 + lumpsum special logic
                //             if ($ruleId == 4 && $isLumpsum == 1) {

                //                 // First record → allow anything valid (0 allowed)
                //                 if ($count == 0) {
                //                     return;
                //                 }

                //                 $first = $records->first();

                //                 // Second record → must NOT exceed first
                //                 if ($count == 1) {
                //                     if ($value > (float) $first->rate_per_unit) {
                //                         $fail("Second rate cannot be greater than first ({$first->rate_per_unit}).");
                //                     }
                //                     return;
                //                 }

                //                 // Third+ record → must increase from last
                //                 $last = $records->last();

                //                 if ($last && $value <= (float) $last->rate_per_unit) {
                //                     $fail("Rate must be greater than previous ({$last->rate_per_unit}).");
                //                 }

                //                 return;
                //             }

                //             $last = $records->last();

                //             if ($last && $value <= (float) $last->rate_per_unit) {
                //                 $fail("Rate must be greater than previous ({$last->rate_per_unit}).");
                //             }
                //         },
                //     ],
                'minimum_demand' => [
                    'required',
                    'numeric',
                    'gte:0',
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                        $existing = RateAndCapacity::where('tariff_setup_id', $ruleId)
                          ->latest('id')->first();
                        if ($existing && abs((float) $value - (float) $existing->minimum_demand) > 0.1) {
                            $fail("The minimum_demand must match the existing active value ({$existing->minimum_demand}).");
                        }
                    },
                ],
                'subsidy_charge' => [
                    'nullable',
                    'numeric',
                    'gte:0',
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                        $existing = RateAndCapacity::where('tariff_setup_id', $ruleId)
                          ->latest('id')->first();
                        if ($existing && $value !== null && abs((float) $value - (float) $existing->subsidy_charge) > 0.1) {
                            $fail("The subsidy_charge must match the existing active value ({$existing->subsidy_charge}).");
                        }
                    },
                ],
                'service_charge' => [
                    'nullable',
                    'numeric',
                    'gte:0',
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                        $existing = RateAndCapacity::where('tariff_setup_id', $ruleId)
                           ->latest('id')->first();
                        if ($existing && $value !== null && abs((float) $value - (float) $existing->service_charge) > 0.1) {
                            $fail("The service_charge must match the existing active value ({$existing->service_charge}).");
                        }
                    },
                ],
                'is_lumpsum' => ['nullable', 'boolean'],
                'other_charge' => [
                    'nullable',
                    'numeric',
                    'gte:0',
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                        $existing = RateAndCapacity::where('tariff_setup_id', $ruleId)
                         ->latest('id')->first();
                        if ($existing && $value !== null && abs((float) $value - (float) $existing->other_charge) > 0.1) {
                            $fail("The other_charge must match the existing active value ({$existing->other_charge}).");
                        }
                    },
                ],
            ]);
          } else {
            $validated = $request->validate([
                'unit_from' => [
                    'required',
                    'numeric',
                    'gte:0',
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                        $unitFrom = (float) $value;

                        $phase = $request->input('phase_id');
                        $capacity = $request->input('capacity_id');
                        $purpose = $request->input('purpose_id');

                        $lastRecord = RateAndCapacity::where('phase_id', $phase)
                            ->where('capacity_id', $capacity)
                            ->where('purpose_id', $purpose)
                            ->where('tariff_setup_id', $ruleId)
                            ->orderByDesc('unit_to')
                            ->first();

                        if ($lastRecord) {
                            $expectedUnitFrom = (float) $lastRecord->unit_to;
                            if (abs($unitFrom - $expectedUnitFrom) > 0.1) {
                                $fail("The unit_from must equal the previous unit_to ($expectedUnitFrom) for this phase, capacity, purpose, and tariff_setup_id.");
                            }
                        } elseif ($unitFrom !== 0.0) {
                            $fail('The unit_from must be 0 for a new phase, capacity, purpose, and tariff_setup_id combination.');
                        }
                    },
                ],
                'unit_to' => [
                    'required',
                    'numeric',
                    'max:100000',
                    'gt:unit_from',
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                        $unitFrom = (float) $request->input('unit_from');
                        $unitTo = (float) $value;
                        $phase = $request->input('phase_id');
                        $capacity = $request->input('capacity_id');
                        $purpose = $request->input('purpose_id');

                        $overlappingRecords = RateAndCapacity::where('phase_id', $phase)
                            ->where('capacity_id', $capacity)
                            ->where('purpose_id', $purpose)
                            ->where('tariff_setup_id', $ruleId)
                            ->where(function ($query) use ($unitFrom, $unitTo) {
                                $query->where('unit_from', '<', $unitTo)
                                    ->where('unit_to', '>', $unitFrom);
                            })
                            ->get();

                        if ($overlappingRecords->isNotEmpty()) {
                            $fail('The unit range overlaps with an existing active record for this phase, capacity, purpose, and tariff_setup_id.');
                        }
                    },
                ],
                'phase_id' => [
                    'required',
                    'exists:tenant.master_setups,id',
                    function ($attribute, $value, $fail) {
                        if (!MasterSetup::where('id', $value)->where('master_setup_type_id', 3)->where('is_active', 1)->exists()) {
                            $fail('The selected phase must be an active Phase record in master_setups.');
                        }
                    },
                ],
                'capacity_id' => [
                    'required',
                    'exists:tenant.master_setups,id',
                    function ($attribute, $value, $fail) {
                        if (!MasterSetup::where('id', $value)->where('master_setup_type_id', 4)->where('is_active', 1)->exists()) {
                            $fail('The selected capacity must be an active Capacity record in master_setups.');
                        }
                    },
                ],
                'purpose_id' => [
                    'required',
                    'exists:tenant.master_setups,id',
                    function ($attribute, $value, $fail) {
                        if (!MasterSetup::where('id', $value)->where('master_setup_type_id', 1)->where('is_active', 1)->exists()) {
                            $fail('The selected purpose must be an active Purpose of Use record in master_setups.');
                        }
                    },
                ],
               
                 'rate_per_unit' => [
                    'required',
                    'numeric',
                    
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                         if ($ruleId != 4 && $value <= 0) {
                                $fail('The rate_per_unit must be greater than 0.');
                            }

                            if ($ruleId == 4 && $value < 0) {
                                $fail('The rate_per_unit cannot be negative.');
                            }

                        $phase = $request->input('phase_id');
                        $capacity = $request->input('capacity_id');
                        $purpose = $request->input('purpose_id');
                        $isLumpsum = $request->input('is_lumpsum', 0);

                        $records = RateAndCapacity::where('phase_id', $phase)
                            ->where('capacity_id', $capacity)
                            ->where('purpose_id', $purpose)
                            ->where('tariff_setup_id', $ruleId)
                            ->where('is_lumpsum', $isLumpsum)
                            ->orderBy('unit_from')
                            ->get();

                        $count = $records->count();

                        if ($ruleId == 4 && $isLumpsum == 1) {

                            // 1st record
                            if ($count == 0) {
                                return;
                            }

                            $first = $records->first();
                            $previous = $records->last();

                            // 2nd record → must NOT exceed first
                            if ($count == 1) {
                                if ($value > (float) $first->rate_per_unit) {
                                    $fail("The rate_per_unit for the second record cannot be greater than the first record ({$first->rate_per_unit}).");
                                }
                                return;
                            }

                            // 3rd+ → must increase from previous
                            if ($previous && $value <= (float) $previous->rate_per_unit) {
                                $fail("The rate_per_unit must be greater than the previous record's rate_per_unit ({$previous->rate_per_unit}).");
                            }

                        } else {
                            $previous = $records->last();

                            if ($previous && $value <= (float) $previous->rate_per_unit) {
                                $fail("The rate_per_unit must be greater than the previous record's rate_per_unit ({$previous->rate_per_unit}).");
                            }
                        }
                    },
                ],
                // 'rate_per_unit' => [
                //         'required',
                //         'numeric',
                //         function ($attribute, $value, $fail) use ($request, $ruleId) {

                //             $isLumpsum = $request->input('is_lumpsum', 0);

                //             // BASIC RULE
                //             if ($ruleId != 4 && $value <= 0) {
                //                 $fail('The rate_per_unit must be greater than 0.');
                //             }

                //             if ($ruleId == 4 && $value < 0) {
                //                 $fail('The rate_per_unit cannot be negative.');
                //             }

                //             $phase = $request->input('phase_id');
                //             $capacity = $request->input('capacity_id');
                //             $purpose = $request->input('purpose_id');

                //             $records = RateAndCapacity::where('phase_id', $phase)
                //                 ->where('capacity_id', $capacity)
                //                 ->where('purpose_id', $purpose)
                //                 ->where('tariff_setup_id', $ruleId)
                //                 ->where('is_lumpsum', $isLumpsum)
                //                 ->orderBy('unit_from')
                //                 ->get();

                //             $count = $records->count();

                //             // SPECIAL CASE: tariff 4 + lumpsum
                //             if ($ruleId == 4 && $isLumpsum == 1) {

                //                 if ($count == 0) {
                //                     return;
                //                 }

                //                 $first = $records->first();

                //                 if ($count == 1) {
                //                     if ($value > (float) $first->rate_per_unit) {
                //                         $fail("Second rate cannot be greater than first ({$first->rate_per_unit}).");
                //                     }
                //                     return;
                //                 }

                //                 $last = $records->last();

                //                 if ($last && $value <= (float) $last->rate_per_unit) {
                //                     $fail("Rate must be greater than previous ({$last->rate_per_unit}).");
                //                 }

                //                 return;
                //             }

                //             // NORMAL RULE
                //             $last = $records->last();

                //             if ($last && $value <= (float) $last->rate_per_unit) {
                //                 $fail("Rate must be greater than previous ({$last->rate_per_unit}).");
                //             }
                //         },
                //     ],
                'minimum_demand' => [
                    'required',
                    'numeric',
                    'gte:0',
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                        $phase = $request->input('phase_id');
                        $capacity = $request->input('capacity_id');
                        $purpose = $request->input('purpose_id');
                        $existing = RateAndCapacity::where('phase_id', $phase)
                            ->where('capacity_id', $capacity)
                            ->where('purpose_id', $purpose)
                            ->where('tariff_setup_id', $ruleId)
                            ->latest('id')->first();
                        if ($existing && abs((float) $value - (float) $existing->minimum_demand) > 0.1) {
                            $fail("The minimum_demand must match the existing active value ({$existing->minimum_demand}).");
                        }
                    },
                ],
                'subsidy_charge' => [
                    'nullable',
                    'numeric',
                    'gte:0',
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                        $phase = $request->input('phase_id');
                        $capacity = $request->input('capacity_id');
                        $purpose = $request->input('purpose_id');
                        $existing = RateAndCapacity::where('phase_id', $phase)
                            ->where('capacity_id', $capacity)
                            ->where('purpose_id', $purpose)
                            ->where('tariff_setup_id', $ruleId)
                            ->latest('id')->first();
                        if ($existing && $value !== null && abs((float) $value - (float) $existing->subsidy_charge) > 0.1) {
                            $fail("The subsidy_charge must match the existing active value ({$existing->subsidy_charge}).");
                        }
                    },
                ],
                'service_charge' => [
                    'nullable',
                    'numeric',
                    'gte:0',
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                        $phase = $request->input('phase_id');
                        $capacity = $request->input('capacity_id');
                        $purpose = $request->input('purpose_id');
                        $existing = RateAndCapacity::where('phase_id', $phase)
                            ->where('capacity_id', $capacity)
                            ->where('purpose_id', $purpose)
                            ->where('tariff_setup_id', $ruleId)
                            ->latest('id')->first();
                        if ($existing && $value !== null && abs((float) $value - (float) $existing->service_charge) > 0.1) {
                            $fail("The service_charge must match the existing active value ({$existing->service_charge}).");
                        }
                    },
                ],
                'is_lumpsum' => ['nullable', 'boolean'],
                'other_charge' => [
                    'nullable',
                    'numeric',
                    'gte:0',
                    function ($attribute, $value, $fail) use ($request, $ruleId) {
                        $phase = $request->input('phase_id');
                        $capacity = $request->input('capacity_id');
                        $purpose = $request->input('purpose_id');
                        $existing = RateAndCapacity::where('phase_id', $phase)
                            ->where('capacity_id', $capacity)
                            ->where('purpose_id', $purpose)
                            ->where('tariff_setup_id', $ruleId)
                            ->latest('id')->first();
                        if ($existing && $value !== null && abs((float) $value - (float) $existing->other_charge) > 0.1) {
                            $fail("The other_charge must match the existing active value ({$existing->other_charge}).");
                        }
                    },
                ],
            ]);
        }
            $validated['subsidy_charge'] = $validated['subsidy_charge'] ?? 0;
            $validated['service_charge'] = $validated['service_charge'] ?? 0;
            $validated['other_charge'] = $validated['other_charge'] ?? 0;
            $validated['is_lumpsum'] = $validated['is_lumpsum'] ?? 0;
            $validated['tariff_setup_id'] = TariffSetup::where('is_active', 1)->value('id');

            $rateAndCapacity = RateAndCapacity::create($validated);


            return response()->json([
                'message' => "Rate and Capacity created successfully",
                'data' => $rateAndCapacity->toArray()
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
                'message' => 'An error occurred while creating the rate and capacity',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function editRateAndCapacity(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('edit rates and capacities')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {

            $activeTariff = TariffSetup::where('is_active', 1)->first();
            if (!$activeTariff) {
                return response()->json(['message' => 'No active tariff setup found'], 422);
            }
            $ruleId = $activeTariff->id;

            $currentRecord = RateAndCapacity::findOrFail($id);
            if (TenantRuntimeHelper::isRuntimeKhanepani()) {
                $latestRecord = RateAndCapacity::where('tariff_setup_id', $currentRecord->tariff_setup_id)
                            ->whereNull('deleted_at')
                            ->orderByDesc('id')
                            ->first();

                        if (!$latestRecord || $latestRecord->id !== $currentRecord->id) {
                            return response()->json([
                                'message' => 'Only the latest record for this Rule setup can be edited.'
                            ], 422);
                        }
                        $unitFrom = (float) $currentRecord->unit_from;
                        $unitTo = (float) $currentRecord->unit_to;

                        $lowerBound = $unitFrom == 0 ? 1 : $unitFrom + 1;
                        $upperBound = $unitTo;
                        $validated = $request->validate([

                            'unit_from' => [
                                'sometimes',
                                'numeric',
                                function ($attribute, $value, $fail) use ($request, $id, $currentRecord, $ruleId) {
                                    $unitFrom = (float) $value;
                                    if ($unitFrom < 0) {
                                        $fail('The unit_from must be a non-negative number.');
                                        return;
                                    } $ruleIdIn = $request->input('tariff_setup_id', $currentRecord->tariff_setup_id);

                                    $prev = RateAndCapacity::where('tariff_setup_id', $ruleIdIn)
                                        ->where('id', '<', $id)
                                        ->orderBy('unit_to', 'DESC') 
                                        ->first();

                                    if ($prev) {
                                        $expected = (float) $prev->unit_to;
                                        if (abs($unitFrom - $expected) > 0.1) {
                                            $fail("The unit_from must match the previous record's unit_to ($expected).");
                                        }
                                    } else {
                                        if (abs($unitFrom - 0) > 0.1) {
                                            $fail('The unit_from must be 0 for the first record.');
                                        }
                                    }
                                },
                            ],

                            'unit_to' => [
                                'sometimes',
                                'numeric',
                                'max:100000',
                                function ($attribute, $value, $fail) use ($request, $currentRecord) {
                                    $unitFrom = (float) $request->input('unit_from', $currentRecord->unit_from);
                                    $unitTo = (float) $value;

                                    if ($unitTo <= $unitFrom) {
                                        $fail('The unit_to must be greater than unit_from.');
                                        return;
                                    }

                                    $ruleIdIn = $request->input('tariff_setup_id', $currentRecord->tariff_setup_id);

                                    $overlap = RateAndCapacity::where('tariff_setup_id', $ruleIdIn)
                                        ->where('id', '!=', $currentRecord->id)
                                        ->where('unit_from', '<', $unitTo)
                                        ->where('unit_to', '>', $unitFrom)
                                        ->exists();

                                    if ($overlap) {
                                        $fail('The unit range overlaps with another active record.');
                                    }
                                },
                            ],
                            // 'rate_per_unit' => [
                            //     'sometimes',
                            //     'numeric',
                            //     'gt:0',
                            //     function ($attribute, $value, $fail) use ($request, $currentRecord, $ruleId) {
                            //         $newRate = (float) $value;
                            //         $ruleIdIn = $request->input('tariff_setup_id', $currentRecord->tariff_setup_id);

                            //         $previous = RateAndCapacity::where('tariff_setup_id', $ruleIdIn)
                            //             ->where('id', '<', $currentRecord->id)
                            //             ->orderByDesc('id')
                            //             ->first();

                            //         if ($previous && $newRate <= (float) $previous->rate_per_unit) {
                            //             $fail("The rate_per_unit must be greater than the previous record's rate ({$previous->rate_per_unit}).");
                            //         }

                            //     },
                            // ],
                           'rate_per_unit' => [
                                        'sometimes',
                                        'numeric',
                                        'gt:0',
                                        function ($attribute, $value, $fail) use ($request, $currentRecord) {

                                            $newRate = (float) $value;
                                            $ruleIdIn = $request->input('tariff_setup_id', $currentRecord->tariff_setup_id);
                                            $isLumpsum = $request->input('is_lumpsum', $currentRecord->is_lumpsum);

                                            $records = RateAndCapacity::where('tariff_setup_id', $ruleIdIn)
                                                ->where('is_lumpsum', $isLumpsum)
                                                ->where('id', '!=', $currentRecord->id)
                                                ->orderBy('unit_from')
                                                ->get();

                                            $count = $records->count();

                                            if ($ruleIdIn == 4 && $isLumpsum == 1) {

                                                // 1st record
                                                if ($count == 0) {
                                                    return;
                                                }

                                                $first = $records->first();
                                                $previous = $records->last();

                                                // 2nd record → must NOT exceed first
                                                if ($count == 1) {
                                                    if ($newRate > (float) $first->rate_per_unit) {
                                                        $fail("The rate_per_unit for the second record cannot be greater than the first record ({$first->rate_per_unit}).");
                                                    }
                                                    return;
                                                }

                                                // 3rd+ → must be greater than previous
                                                if ($previous && $newRate <= (float) $previous->rate_per_unit) {
                                                    $fail("The rate_per_unit must be greater than the previous record's rate_per_unit ({$previous->rate_per_unit}).");
                                                }

                                            } else {
                                                $previous = $records->last();

                                                if ($previous && $newRate <= (float) $previous->rate_per_unit) {
                                                    $fail("The rate_per_unit must be greater than the previous record's rate_per_unit ({$previous->rate_per_unit}).");
                                                }
                                            }
                                        },
                                    ],

                            'minimum_demand' => [
                                'required',
                                'numeric',
                                'gte:0',
                            ],

                            'subsidy_charge' => [
                                'nullable',
                                'numeric',
                                'gte:0',
                            ],

                            'service_charge' => [
                                'nullable',
                                'numeric',
                                'gte:0',
                            ],

                            'other_charge' => [
                                'nullable',
                                'numeric',
                                'gte:0',
                            
                            ],
                            'is_lumpsum' => ['sometimes', 'boolean'],

                            'is_active' => 'sometimes|boolean',
                        ]);


                        $updateData = [];
                        $optionalFields = [
                            'unit_from',
                            'unit_to',
                            'rate_per_unit',
                            'minimum_demand',
                            'subsidy_charge',
                            'service_charge',
                            'other_charge',
                            'is_active'
                        ];
                                }else{
                                $latestRecord = RateAndCapacity::where('phase_id', $currentRecord->phase_id)
                                    ->where('capacity_id', $currentRecord->capacity_id)
                                    ->where('purpose_id', $currentRecord->purpose_id)
                                    ->where('tariff_setup_id', $currentRecord->tariff_setup_id)
                                    ->whereNull('deleted_at')
                                    ->orderByDesc('id')
                                    ->first();

                                if (!$latestRecord || $latestRecord->id !== $currentRecord->id) {
                                    return response()->json([
                                        'message' => 'Only the latest record for this phase, capacity, purpose, and tariff setup can be edited.'
                                    ], 422);
                                }
                                $unitFrom = (float) $currentRecord->unit_from;
                                $unitTo = (float) $currentRecord->unit_to;

                                $lowerBound = $unitFrom == 0 ? 1 : $unitFrom + 1;
                                $upperBound = $unitTo;

                                $matchingCustomerIds = MeterIssue::where('phase_id', $currentRecord->phase_id)
                                    ->where('capacity_id', $currentRecord->capacity_id)
                                    ->where('purpose_id', $currentRecord->purpose_id)
                                    ->pluck('id')
                                    ->toArray();

                                $meterReadingExists = false;
                                if (!empty($matchingCustomerIds)) {
                                    $meterReadingExists = MeterReadingEntry::withoutTrashed()
                                        ->whereIn('meter_issue_id', $matchingCustomerIds)
                                        ->where('tariff_setup_id', $ruleId)
                                        ->where('total_unit', '>=', $lowerBound)  // ← FIXED
                                        ->where('total_unit', '<=', $upperBound) // ← FIXED
                                        ->exists();
                                }

                                // if ($meterReadingExists) {
                                //     return response()->json([
                                //         'message' => 'Cannot edit this rate slab. It is already used in one or more meter readings.'
                                //     ], 422);
                                // }


                                $validated = $request->validate([

                                    'unit_from' => [
                                        'sometimes',
                                        'numeric',
                                        function ($attribute, $value, $fail) use ($request, $id, $currentRecord, $ruleId) {
                                            $unitFrom = (float) $value;
                                            if ($unitFrom < 0) {
                                                $fail('The unit_from must be a non-negative number.');
                                                return;
                                            }

                                            $phase = $request->input('phase_id', $currentRecord->phase_id);
                                            $capacity = $request->input('capacity_id', $currentRecord->capacity_id);
                                            $purpose = $request->input('purpose_id', $currentRecord->purpose_id);
                                            $ruleIdIn = $request->input('tariff_setup_id', $currentRecord->tariff_setup_id);

                                            $prev = RateAndCapacity::where('phase_id', $phase)
                                                ->where('capacity_id', $capacity)
                                                ->where('purpose_id', $purpose)
                                                ->where('tariff_setup_id', $ruleIdIn)
                                                ->where('id', '<', $id)
                                                ->orderBy('unit_to', 'DESC') // ← FIXED
                                                ->first();

                                            if ($prev) {
                                                $expected = (float) $prev->unit_to;
                                                if (abs($unitFrom - $expected) > 0.1) {
                                                    $fail("The unit_from must match the previous record's unit_to ($expected).");
                                                }
                                            } else {
                                                if (abs($unitFrom - 0) > 0.1) {
                                                    $fail('The unit_from must be 0 for the first record.');
                                                }
                                            }
                                        },
                                    ],

                                    'unit_to' => [
                                        'sometimes',
                                        'numeric',
                                        'max:100000',
                                        function ($attribute, $value, $fail) use ($request, $currentRecord) {
                                            $unitFrom = (float) $request->input('unit_from', $currentRecord->unit_from);
                                            $unitTo = (float) $value;

                                            if ($unitTo <= $unitFrom) {
                                                $fail('The unit_to must be greater than unit_from.');
                                                return;
                                            }

                                            $phase = $request->input('phase_id', $currentRecord->phase_id);
                                            $capacity = $request->input('capacity_id', $currentRecord->capacity_id);
                                            $purpose = $request->input('purpose_id', $currentRecord->purpose_id);
                                            $ruleIdIn = $request->input('tariff_setup_id', $currentRecord->tariff_setup_id);

                                            $overlap = RateAndCapacity::where('phase_id', $phase)
                                                ->where('capacity_id', $capacity)
                                                ->where('purpose_id', $purpose)
                                                ->where('tariff_setup_id', $ruleIdIn)
                                                ->where('id', '!=', $currentRecord->id)
                                                ->where('unit_from', '<', $unitTo)
                                                ->where('unit_to', '>', $unitFrom)
                                                ->exists();

                                            if ($overlap) {
                                                $fail('The unit range overlaps with another active record.');
                                            }
                                        },
                                    ],


                                    'phase_id' => [
                                        'required',
                                        'exists:tenant.master_setups,id',
                                        function ($attribute, $value, $fail) {
                                            if (!MasterSetup::where('id', $value)->where('master_setup_type_id', 3)->where('is_active', 1)->exists()) {
                                                $fail('The selected phase must be an active Phase record in master_setups.');
                                            }
                                        },
                                    ],
                                    'capacity_id' => [
                                        'required',
                                        'exists:tenant.master_setups,id',
                                        function ($attribute, $value, $fail) {
                                            if (!MasterSetup::where('id', $value)->where('master_setup_type_id', 4)->where('is_active', 1)->exists()) {
                                                $fail('The selected capacity must be an active Capacity record in master_setups.');
                                            }
                                        },
                                    ],
                                    'purpose_id' => [
                                        'required',
                                        'exists:tenant.master_setups,id',
                                        function ($attribute, $value, $fail) {
                                            if (!MasterSetup::where('id', $value)->where('master_setup_type_id', 1)->where('is_active', 1)->exists()) {
                                                $fail('The selected purpose must be an active Purpose of Use record in master_setups.');
                                            }
                                        },
                                    ],


                                    // 'rate_per_unit' => [
                                    //     'sometimes',
                                    //     'numeric',
                                    //     'gt:0',
                                    //     function ($attribute, $value, $fail) use ($request, $currentRecord, $ruleId) {
                                    //         $newRate = (float) $value;

                                    //         $phase = $request->input('phase_id', $currentRecord->phase_id);
                                    //         $capacity = $request->input('capacity_id', $currentRecord->capacity_id);
                                    //         $purpose = $request->input('purpose_id', $currentRecord->purpose_id);
                                    //         $ruleIdIn = $request->input('tariff_setup_id', $currentRecord->tariff_setup_id);

                                    //         $previous = RateAndCapacity::where('phase_id', $phase)
                                    //             ->where('capacity_id', $capacity)
                                    //             ->where('purpose_id', $purpose)
                                    //             ->where('tariff_setup_id', $ruleIdIn)
                                    //             ->where('id', '<', $currentRecord->id)
                                    //             ->orderByDesc('id')
                                    //             ->first();

                                    //         if ($previous && $newRate <= (float) $previous->rate_per_unit) {
                                    //             $fail("The rate_per_unit must be greater than the previous record's rate ({$previous->rate_per_unit}).");
                                    //         }

                                    //         // If no previous record → any positive value is allowed (already enforced by 'gt:0')
                                    //     },
                                    // ],
                                    'rate_per_unit' => [
                                        'sometimes',
                                        'numeric',
                                        'gt:0',
                                        function ($attribute, $value, $fail) use ($request, $currentRecord) {

                                            $newRate = (float) $value;

                                            $phase = $request->input('phase_id', $currentRecord->phase_id);
                                            $capacity = $request->input('capacity_id', $currentRecord->capacity_id);
                                            $purpose = $request->input('purpose_id', $currentRecord->purpose_id);
                                            $ruleIdIn = $request->input('tariff_setup_id', $currentRecord->tariff_setup_id);
                                            $isLumpsum = $request->input('is_lumpsum', $currentRecord->is_lumpsum);

                                                 $records = RateAndCapacity::where('phase_id', $phase)
                                                ->where('capacity_id', $capacity)
                                                ->where('purpose_id', $purpose)
                                                ->where('tariff_setup_id', $ruleIdIn)
                                                ->where('is_lumpsum', $isLumpsum)
                                                ->where('id', '!=', $currentRecord->id)
                                                ->orderBy('unit_from')
                                                ->get();

                                            $count = $records->count();

                                            if ($ruleIdIn == 4 && $isLumpsum == 1) {

                                                // 1st record
                                                if ($count == 0) {
                                                    return;
                                                }

                                                $first = $records->first();
                                                $previous = $records->last();

                                                // 2nd record → must NOT exceed first
                                                if ($count == 1) {
                                                    if ($newRate > (float) $first->rate_per_unit) {
                                                        $fail("The rate_per_unit for the second record cannot be greater than the first record ({$first->rate_per_unit}).");
                                                    }
                                                    return;
                                                }

                                                // 3rd+ → must be greater than previous
                                                if ($previous && $newRate <= (float) $previous->rate_per_unit) {
                                                    $fail("The rate_per_unit must be greater than the previous record's rate_per_unit ({$previous->rate_per_unit}).");
                                                }

                                            } else {
                                                $previous = $records->last();

                                                if ($previous && $newRate <= (float) $previous->rate_per_unit) {
                                                    $fail("The rate_per_unit must be greater than the previous record's rate_per_unit ({$previous->rate_per_unit}).");
                                                }
                                            }
                                        },
                                    ],


                                    'minimum_demand' => [
                                        'required',
                                        'numeric',
                                        'gte:0',
                                        function ($attribute, $value, $fail) use ($request, $ruleId, $id) {
                                            $phase = $request->input('phase_id');
                                            $capacity = $request->input('capacity_id');
                                            $purpose = $request->input('purpose_id');

                                            $existing = RateAndCapacity::where('phase_id', $phase)
                                                ->where('capacity_id', $capacity)
                                                ->where('purpose_id', $purpose)
                                                ->where('tariff_setup_id', $ruleId)
                                                ->where('id', '!=', $id)
                                                ->first();

                                            if ($existing && abs((float) $value - (float) $existing->minimum_demand) > 0.1) {
                                                $fail("The minimum_demand must match the existing active value ({$existing->minimum_demand}).");
                                            }
                                        },
                                    ],

                                    'subsidy_charge' => [
                                        'nullable',
                                        'numeric',
                                        'gte:0',
                                        function ($attribute, $value, $fail) use ($request, $ruleId, $id) {
                                            $phase = $request->input('phase_id');
                                            $capacity = $request->input('capacity_id');
                                            $purpose = $request->input('purpose_id');

                                            $existing = RateAndCapacity::where('phase_id', $phase)
                                                ->where('capacity_id', $capacity)
                                                ->where('purpose_id', $purpose)
                                                ->where('tariff_setup_id', $ruleId)
                                                ->where('id', '!=', $id)
                                                ->first();

                                            if ($existing && $value !== null && abs((float) $value - (float) $existing->subsidy_charge) > 0.1) {
                                                $fail("The subsidy_charge must match the existing active value ({$existing->subsidy_charge}).");
                                            }
                                        },
                                    ],

                                    'service_charge' => [
                                        'nullable',
                                        'numeric',
                                        'gte:0',
                                        function ($attribute, $value, $fail) use ($request, $ruleId, $id) {
                                            $phase = $request->input('phase_id');
                                            $capacity = $request->input('capacity_id');
                                            $purpose = $request->input('purpose_id');

                                            $existing = RateAndCapacity::where('phase_id', $phase)
                                                ->where('capacity_id', $capacity)
                                                ->where('purpose_id', $purpose)
                                                ->where('tariff_setup_id', $ruleId)
                                                ->where('id', '!=', $id)
                                                ->first();

                                            if ($existing && $value !== null && abs((float) $value - (float) $existing->service_charge) > 0.1) {
                                                $fail("The service_charge must match the existing active value ({$existing->service_charge}).");
                                            }
                                        },
                                    ],

                                    'other_charge' => [
                                        'nullable',
                                        'numeric',
                                        'gte:0',
                                        function ($attribute, $value, $fail) use ($request, $ruleId, $id) {
                                            $phase = $request->input('phase_id');
                                            $capacity = $request->input('capacity_id');
                                            $purpose = $request->input('purpose_id');

                                            $existing = RateAndCapacity::where('phase_id', $phase)
                                                ->where('capacity_id', $capacity)
                                                ->where('purpose_id', $purpose)
                                                ->where('tariff_setup_id', $ruleId)
                                                ->where('id', '!=', $id)
                                                ->first();

                                            if ($existing && $value !== null && abs((float) $value - (float) $existing->other_charge) > 0.1) {
                                                $fail("The other_charge must match the existing active value ({$existing->other_charge}).");
                                            }
                                        },
                                    ],

                                    'is_active' => 'sometimes|boolean',
                                ]);


                                $updateData = [];
                                $optionalFields = [
                                    'unit_from',
                                    'unit_to',
                                    'phase_id',
                                    'capacity_id',
                                    'purpose_id',
                                    'rate_per_unit',
                                    'minimum_demand',
                                    'subsidy_charge',
                                    'service_charge',
                                    'other_charge',
                                    'is_active'
                                ];
                    }
            foreach ($optionalFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $validated[$field] ?? (
                        in_array($field, ['subsidy_charge', 'service_charge', 'other_charge']) ? 0 : null
                    );
                }
            }
            $updateData['tariff_setup_id'] = $ruleId;


            $currentRecord->fill($updateData);
            $saved = $currentRecord->save();


            return response()->json([
                'message' => 'Rate and Capacity updated successfully',
                'data' => $currentRecord->toArray()
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
                'message' => 'An error occurred while updating the rate and capacity',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteRateAndCapacity(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('delete rates and capacities')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $rateAndCapacity = RateAndCapacity::findOrFail($id);
             $ruleId = $rateAndCapacity->tariff_setup_id;
            if (TenantRuntimeHelper::isRuntimeKhanepani()) {
                    $latestRecord = RateAndCapacity::where('tariff_setup_id', $ruleId)
                            ->whereNull('deleted_at')
                            ->orderByDesc('id')
                            ->first();

                        if (!$latestRecord || $latestRecord->id !== $rateAndCapacity->id) {
                            return response()->json([
                                'message' => 'Only the latest record for this Rule Setup can be deleted.'
                            ], 422);
                        }

                        $unitFrom = (float) $rateAndCapacity->unit_from;
                        $unitTo = (float) $rateAndCapacity->unit_to;
                        $lowerBound = $unitFrom == 0 ? 1 : $unitFrom + 1;
                        $upperBound = $unitTo;

                        $matchingCustomerIds = MeterIssue::query()
                            ->pluck('id')
                            ->toArray();
            }else{
            $phase = $rateAndCapacity->phase_id;
            $capacity = $rateAndCapacity->capacity_id;
            $purpose = $rateAndCapacity->purpose_id;

            $latestRecord = RateAndCapacity::where('phase_id', $phase)
                ->where('capacity_id', $capacity)
                ->where('purpose_id', $purpose)
                ->where('tariff_setup_id', $ruleId)
                ->orderByDesc('id')
                ->first();

            if (!$latestRecord || $latestRecord->id !== $rateAndCapacity->id) {
                return response()->json([
                    'message' => 'Only the latest record for this phase, capacity, and purpose can be deleted.'
                ], 422);
            }

            $unitFrom = (float) $rateAndCapacity->unit_from;
            $unitTo = (float) $rateAndCapacity->unit_to;
            $lowerBound = $unitFrom == 0 ? 1 : $unitFrom + 1;
            $upperBound = $unitTo;

            $matchingCustomerIds = MeterIssue::where('phase_id', $phase)
                ->where('capacity_id', $capacity)
                ->where('purpose_id', $purpose)
                ->pluck('id')
                ->toArray();
        }

            // $meterReadingExists = false;
            // if (!empty($matchingCustomerIds)) {
            //     $meterReadingExists = MeterReadingEntry::withoutTrashed()
            //         ->whereIn('meter_issue_id', $matchingCustomerIds)
            //         ->where('tariff_setup_id', $ruleId)
            //         ->whereRaw('CAST(total_unit AS DECIMAL(15,2)) >= ?', [$lowerBound])
            //         ->whereRaw('CAST(total_unit AS DECIMAL(15,2)) <= ?', [$upperBound])
            //         ->exists();
            // }
            $meterReadingExists = false;

            if (!empty($matchingCustomerIds)) {
                $meterReadingExists = MeterReadingEntry::withoutTrashed()
                    ->whereIn('meter_issue_id', $matchingCustomerIds)
                    ->where('tariff_setup_id', $ruleId)
                    ->where('total_unit', '>=', $lowerBound)
                    ->where('total_unit', '<', $upperBound) // safer
                    ->exists();
            }

            if ($meterReadingExists) {
                return response()->json([
                    'message' => 'Cannot delete this rate slab. It is already used in one or more meter readings.'
                ], 422);
            }

            $rateAndCapacity->delete();

            return response()->json(['message' => 'Rate and Capacity deleted successfully']);

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();


            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the rate and capacity',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}