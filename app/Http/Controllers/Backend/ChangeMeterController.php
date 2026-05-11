<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\TenantRuntimeHelper;
use App\Http\Controllers\Controller;
use App\Models\ChangeMeter;
use App\Models\MahasulReceiptEntry;
use App\Models\MeterIssue;
use App\Models\MemberEntry;
use App\Models\MeterReadingEntry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use App\Models\CustomerTransaction;
use App\Services\KnowMeterStartService;


class ChangeMeterController extends Controller
{
   public function searchCustomerDetails(Request $request)
{
    try {
        $searchTerm = $request->input('search');

        $selectColumns = [
            'id',
            'member_entry_id',
            'transformer_id',
            'meter_no',
            'meter_start_no',
            'issue_date_bs',
            'construct_company',
            'issue_meter_capacity',
            'reading_seal_no',
            'terminal_seal_no',
            'meter_box_seal_no',
            'pole_no',
            'pole_distance',
            'area_id',
            'is_active',
            'meter_issue_record',
            'issue_date_ad'
        ];

        if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
            $selectColumns = array_merge($selectColumns, [
                'phase_id',
                'capacity_id',
                'purpose_id',
            ]);
        }

        $customers = MeterIssue::where('is_active', 1)
            ->where(function ($query) use ($searchTerm) {
                $query->where('member_entry_id', 'like', "%{$searchTerm}%")
                      ->orWhereHas('memberEntry', function ($q) use ($searchTerm) {
                          $q->where('customer_name_en', 'like', "%{$searchTerm}%")
                            ->orWhere('member_no', 'like', "%{$searchTerm}%");
                      });
            })
            ->with([
                'memberEntry:id,member_no,customer_name_en,customer_name_np,is_disable',
            ])
            ->select($selectColumns)
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
                'member_entry_id' => $customer->member_entry_id,
                'member_no' => $customer->memberEntry->member_no ?? null,
                'customer_name_en' => $customer->memberEntry->customer_name_en ?? null,
                'customer_name_np' => $customer->memberEntry->customer_name_np ?? null,
                'is_disable' => $customer->memberEntry->is_disable ?? null,
                'meter_no' => $customer->meter_no,
                'meter_start_no' => $customer->meter_start_no,
            ];

            if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
                $data = array_merge($data, [
                    'phase_id' => $customer->phase_id,
                    'capacity_id' => $customer->capacity_id,
                    'purpose_id' => $customer->purpose_id,
                ]);
            }

            return $data;
        });

        return response()->json([
            'message' => 'Customer details retrieved successfully',
            'data' => $result,
        ], 200);

    } catch (\Exception $e) {
        Log::error('Error searching customer details: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json([
            'message' => 'An error occurred while searching customer details',
            'error' => $e->getMessage(),
        ], 500);
    }
}

    public function changeMeter(Request $request)
    {


        try {
            $validated = $request->validate([
                'date_in_bs' => [
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
                'date_in_ad' => [
                    'required',
                    'string',
                    'max:10',
                    'regex:/^\d{4}-\d{2}-\d{2}$/',
                    function ($attribute, $value, $fail) {
                        $today = date('Y-m-d');
                        if ($value > $today) {
                            $fail('The ' . $attribute . ' cannot be a future date.');
                        }
                    },
                ],
                'meter_no' => [
                    'required',
                    'string',
                    'max:15',
                    Rule::unique('tenant.meter_issues', 'meter_no')
                        ->ignore($request->meter_issue_id)
                        ->whereNull('deleted_at'),
                ],
                'meter_issue_id' => [
                    'required',
                    'integer',
                    Rule::exists('tenant.meter_issues', 'id')
                        ->where('deleted_at', null)
                        ->where('is_active', 1),
                    // Rule::unique('tenant.change_meters', 'meter_issue_id')
                    //     ->whereNull('deleted_at'),
                ],
                'construct_company' => 'nullable|string|max:50',
                'reading_seal_no' => 'nullable|string|max:20',
                'terminal_seal_no' => 'nullable|string|max:20',
                'meter_box_seal_no' => 'nullable|string|max:20',
                'meter_start_no' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();

            $meterIssue = MeterIssue::where('id', $validated['meter_issue_id'])
                ->where('is_active', 1)
                ->firstOrFail();

            $previousMeterNo = $meterIssue->meter_no;

            $meterIssue->update([
                'meter_no' => $validated['meter_no'],
                'meter_start_no' => $validated['meter_start_no'],
                'construct_company' => $validated['construct_company'],
                'reading_seal_no' => $validated['reading_seal_no'],
                'terminal_seal_no' => $validated['terminal_seal_no'],
                'meter_box_seal_no' => $validated['meter_box_seal_no'],
            ]);

            $changeMeter = ChangeMeter::create([
                'date_in_bs' => $validated['date_in_bs'],
                'date_in_ad' => $validated['date_in_ad'],
                'meter_issue_id' => $validated['meter_issue_id'],
                'meter_start_no' => $validated['meter_start_no'],
                'previous_meter_no' => $previousMeterNo,
                'construct_company' => $validated['construct_company'],
                'reading_seal_no' => $validated['reading_seal_no'],
                'terminal_seal_no' => $validated['terminal_seal_no'],
                'meter_box_seal_no' => $validated['meter_box_seal_no'],
            ]);

            DB::commit();

            return response()->json([
                'message' => "Meter changed successfully.",
                'data' => $changeMeter->toArray(),
            ], 200);

        } catch (ValidationException $e) {
            DB::rollBack();
            $errors = $e->errors();
            $first = collect($errors)->flatten()->first();
            Log::error('Validation error: ' . json_encode($errors, JSON_UNESCAPED_UNICODE));
            return response()->json(['message' => $first, 'errors' => $errors], 422);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('Active member or meter issue not found for customer_id: ' . $request->input('member_entry_id'));
            return response()->json([
                'message' => 'No active member or meter issue found for the provided customer_id.',
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error changing meter: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'An error occurred while changing the meter.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function list(Request $request)
    {


        try {
            $records = ChangeMeter::withoutTrashed()
                ->orderBy('created_at', 'desc')
                ->with([
                    'meterIssue' => function ($q) {
                        $q->select(
                            'id',
                            'member_entry_id',
                            'meter_no',
                            'issue_meter_capacity',
                            'area_id',
                            'transformer_id'
                        )->with([
                                    'memberEntry:id,member_no,customer_name_en,customer_name_np'
                                ]);
                    }
                ])
                ->paginate(10);

            $records->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'date_in_bs' => $item->date_in_bs,
                    'date_in_ad' => $item->date_in_ad,
                    'member_entry_id' => $item->meterIssue->member_entry_id ?? null,
                    'member_no' => $item->meterIssue->memberEntry->member_no ?? null,
                    'meter_issue_id' => $item->meter_issue_id,
                    'previous_meter_no' => $item->previous_meter_no,

                    // via meter issue
                    'customer_name_en' => $item->meterIssue->memberEntry->customer_name_en ?? null,
                    'customer_name_np' => $item->meterIssue->memberEntry->customer_name_np ?? null,
                    'issue_meter_capacity' => $item->meterIssue->issue_meter_capacity ?? null,
                    'area_id' => $item->meterIssue->area_id ?? null,
                    'transformer_id' => $item->meterIssue->transformer_id ?? null,

                    'meter_no' => $item->meterIssue->meter_no ?? null,
                    'construct_company' => $item->construct_company,
                    'reading_seal_no' => $item->reading_seal_no,
                    'terminal_seal_no' => $item->terminal_seal_no,
                    'meter_box_seal_no' => $item->meter_box_seal_no,
                    'meter_start_no' => $item->meter_start_no,
                    'deleted_at' => $item->deleted_at,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            });

            return response()->json(['data' => $records], 200);
        } catch (\Exception $e) {
            Log::error('Error listing change meter entries: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'An error occurred while listing change meter entries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getById(Request $request, $id, KnowMeterStartService $meterStartService)
    {


        try {
            $record = MeterIssue::with([
                'memberEntry:id,member_no,customer_name_en,customer_name_np',
                'transformer',
                'readingArea',
                'demandCapacity',
                'demandPhase',
                'purposeType'
            ])->findOrFail($id);


            $totalDue = CustomerTransaction::where('member_entry_id', $record->memberEntry->id)
                ->whereIn(
                    'reference_id',
                    MeterReadingEntry::where('meter_issue_id', $record->memberEntry->id)
                        ->whereIn('status', [0, 1])
                        ->pluck('id')
                )
                ->whereNotIn('charge_type', [9, 8, 7])
                ->selectRaw("
                                SUM(CASE WHEN direction = 'DR' THEN amount ELSE 0 END) -
                                SUM(CASE WHEN direction = 'CR' THEN amount ELSE 0 END) AS due
                            ")
                ->value('due');
            $totalDue = max(0, $totalDue);
            $meterStartNo = $meterStartService->getMeterStartNo($record->id);



            $data = [
                'id' => $record->id,
                'member_entry_id' => $record->memberEntry->id ?? null,
                'member_no' => $record->memberEntry->member_no ?? null,
                'customer_name_en' => $record->memberEntry->customer_name_en ?? null,
                'customer_name_np' => $record->memberEntry->customer_name_np ?? null,
                'issue_meter_capacity' => $record->issue_meter_capacity ?? null,
                'area' => $record->readingArea->name_en ?? null,
                'demand_capacity' => $record->demandCapacity->name_en ?? null,
                'demand_phase' => $record->demandPhase->name_en ?? null,
                'purpose_type' => $record->purposeType->name_en ?? null,
                'transformer' => $record->transformer->name_en ?? null,
                'meter_no' => $record->meter_no ?? null,
                'construct_company' => $record->construct_company,
                'reading_seal_no' => $record->reading_seal_no,
                'terminal_seal_no' => $record->terminal_seal_no,
                'meter_box_seal_no' => $record->meter_box_seal_no,
                'pole_no' => $record->pole_no,
                'pole_distance' => $record->pole_distance,
                'meter_start_no' => $meterStartNo,
                'total_due' => $totalDue ?? 0,
            ];


            return response()->json([
                'message' => 'Change meter record retrieved successfully',
                'data' => $data
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Change meter record not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error retrieving change meter record: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while retrieving the record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function edit(Request $request, $id)
    {


        try {
            $changeMeter = ChangeMeter::withoutTrashed()->findOrFail($id);

            $validated = $request->validate([
                'date_in_bs' => [
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
                'date_in_ad' => [
                    'required',
                    'string',
                    'max:10',
                    'regex:/^\d{4}-\d{2}-\d{2}$/',
                    function ($attribute, $value, $fail) {
                        $today = date('Y-m-d');
                        if ($value > $today) {
                            $fail('The ' . $attribute . ' cannot be a future date.');
                        }
                    },
                ],
                'meter_issue_id' => [
                    'required',
                    'integer',
                    Rule::exists('tenant.meter_issues', 'id')
                        ->where('deleted_at', null)
                        ->where('is_active', 1),
                    function ($attribute, $value, $fail) use ($changeMeter) {
                        if ($value != $changeMeter->meter_issue_id) {
                            $fail("The meter_issue_id cannot be changed once assigned.");
                        }
                    }
                ],
                'meter_no' => [
                    'required',
                    'string',
                    'max:15',
                    Rule::unique('tenant.meter_issues', 'meter_no')->whereNull('deleted_at'),
                ],
                'construct_company' => 'nullable|string|max:50',
                'reading_seal_no' => 'nullable|string|max:20',
                'terminal_seal_no' => 'nullable|string|max:20',
                'meter_box_seal_no' => 'nullable|string|max:20',
                'due_balance_amount' => 'nullable|numeric|min:0|max:999999999.9999',
                'meter_start_no' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();

            $meterIssue = MeterIssue::where('id', $validated['meter_issue_id'])
                ->where('is_active', 1)
                ->firstOrFail();

            $previousMeterNo = $meterIssue->meter_no;

            $meterIssue->update([
                'meter_no' => $validated['meter_no'],
                'meter_start_no' => $validated['meter_start_no'],
                'construct_company' => $validated['construct_company'],
                'reading_seal_no' => $validated['reading_seal_no'],
                'terminal_seal_no' => $validated['terminal_seal_no'],
                'meter_box_seal_no' => $validated['meter_box_seal_no'],
            ]);

            $changeMeter->update([
                'date_in_bs' => $validated['date_in_bs'],
                'date_in_ad' => $validated['date_in_ad'],
                'meter_issue_id' => $changeMeter->meter_issue_id,
                'meter_no' => $validated['meter_no'],
                'meter_start_no' => $validated['meter_start_no'],
                'previous_meter_no' => $previousMeterNo,
                'construct_company' => $validated['construct_company'],
                'reading_seal_no' => $validated['reading_seal_no'],
                'terminal_seal_no' => $validated['terminal_seal_no'],
                'meter_box_seal_no' => $validated['meter_box_seal_no'],
                'due_balance_amount' => number_format((float) ($validated['due_balance_amount'] ?? 0), 2, '.', ''),
            ]);

            DB::commit();

            return response()->json([
                'message' => "Change meter record updated successfully.",
                'data' => $changeMeter->toArray(),
            ], 200);

        } catch (ValidationException $e) {
            DB::rollBack();
            $errors = $e->errors();
            $firstError = collect($errors)->flatten()->first();
            return response()->json(['message' => $firstError, 'errors' => $errors], 422);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Change meter record or related entry not found.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while updating the change meter record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('delete change meter issues')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $changeMeter = ChangeMeter::withoutTrashed()->findOrFail($id);

            $changeMeter->delete();
            Log::info("Soft deleted change meter record with id = {$id}");

            return response()->json([
                'message' => 'Change meter record deleted successfully',
                'id' => $id,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting change meter record: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'An error occurred while deleting the change meter record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
