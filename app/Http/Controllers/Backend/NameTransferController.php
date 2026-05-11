<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\TenantRuntimeHelper;
use App\Http\Controllers\Controller;
use App\Models\MahasulReceiptEntry;
use App\Models\MemberEntry;
use App\Models\MeterIssue;
use App\Models\NameTransferEntry;
use App\Models\MeterReadingEntry;
use App\Services\MemberTransferService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\FindTotalDueAmountServiceAndFindAdvancePayment;

class NameTransferController extends Controller
{

    public function listtransferMeterIssue(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view transfer entries')) {
        //     return response()->json(['message' => 'Unauthorized.'], 403);
        // }

        try {
            $searchTerm = $request->input('search');

            $entries = NameTransferEntry::query()
                ->with([
                    'previousMember' => function ($q) {
                        $q->withTrashed(); // <-- Important
                    },
                    'newMember' => function ($q) {
                        $q->withTrashed(); // <-- Important
                    },
                ])
                ->withTrashed(false)
                ->when($searchTerm, function ($query) use ($searchTerm) {
                    $query->where(function ($q) use ($searchTerm) {
                        $q->whereHas('previousMember', function ($sub) use ($searchTerm) {
                            $sub->withTrashed()
                                ->where('member_entry_id', 'like', "%{$searchTerm}%")
                                ->orWhere('customer_name_en', 'like', "%{$searchTerm}%")
                                ->orWhere('customer_name_np', 'like', "%{$searchTerm}%");
                        })
                            ->orWhereHas('newMember', function ($sub) use ($searchTerm) {
                                $sub->where('member_entry_id', 'like', "%{$searchTerm}%")
                                    ->orWhere('customer_name_en', 'like', "%{$searchTerm}%")
                                    ->orWhere('customer_name_np', 'like', "%{$searchTerm}%");
                            })
                            ->orWhereHas('meterIssue', function ($sub) use ($searchTerm) {
                                $sub->where('meter_no', 'like', "%{$searchTerm}%");
                            });
                    });
                });

            $result = $entries->paginate(10)->through(function ($entry) {

                $previousMeterIssue = MeterIssue::where('member_entry_id', $entry->previous_member_entry_id)
                    ->latest('id')
                    ->first();

               $data = [
                'id' => $entry->id,

                'previous_member_entry_id' => $entry->previous_member_entry_id,
                'previous_customer_name_en' => $entry->previousMember?->customer_name_en,
                'previous_customer_name_np' => $entry->previousMember?->customer_name_np,
                'previous_member_no' => $entry->previousMember?->member_no,

                'new_member_entry_id' => $entry->new_member_entry_id,
                'new_customer_name_en' => $entry->newMember?->customer_name_en,
                'new_customer_name_np' => $entry->newMember?->customer_name_np,
                'new_member_no' => $entry->newMember?->member_no,

                'meter_no' => $previousMeterIssue?->meter_no,
                'transformer_id' => $previousMeterIssue?->transformer_id,
                'meter_start_no' => $previousMeterIssue?->meter_start_no,
                'meter_issue_record' => $previousMeterIssue?->meter_issue_record,
                'area_id' => $previousMeterIssue?->area_id,

                'created_at' => $entry->created_at,
                'updated_at' => $entry->updated_at,
            ];

            if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
                $data['phase_id'] = $previousMeterIssue?->phase_id;
                $data['capacity_id'] = $previousMeterIssue?->capacity_id;
            }

            return $data;
        });


            return response()->json($result);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'An error occurred while retrieving transfer entries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





    public function getById(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('view transfer entries')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $entry = NameTransferEntry::withoutTrashed()
                ->with([
                    'previousMember',
                    'newMember'
                ])
                ->findOrFail($id);

            $previousMeterIssue = MeterIssue::where('member_entry_id', $entry->previous_member_entry_id)
                ->latest('id')
                ->first();

             $data = [
            'id' => $entry->id,

            'previous_member_entry_id' => $entry->previous_member_entry_id,
            'previous_customer_name_en' => $entry->previousMember?->customer_name_en,
            'previous_customer_name_np' => $entry->previousMember?->customer_name_np,
            'previous_member_no' => $entry->previousMember?->member_no,

            'new_member_entry_id' => $entry->new_member_entry_id,
            'new_customer_name_en' => $entry->newMember?->customer_name_en,
            'new_customer_name_np' => $entry->newMember?->customer_name_np,
            'new_member_no' => $entry->newMember?->member_no,

            'meter_no' => $previousMeterIssue?->meter_no,
            'transformer_id' => $previousMeterIssue?->transformer_id,
            'meter_start_no' => $previousMeterIssue?->meter_start_no,
            'meter_issue_record' => $previousMeterIssue?->meter_issue_record,
            'area_id' => $previousMeterIssue?->area_id,

            'created_at' => $entry->created_at,
            'updated_at' => $entry->updated_at,
        ];

        if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
            $data['phase_id'] = $previousMeterIssue?->phase_id;
            $data['capacity_id'] = $previousMeterIssue?->capacity_id;
        }

            return response()->json(['name_transfer' => $data], 200);

        } catch (ModelNotFoundException $e) {

            return response()->json([
                'message' => 'Name Transfer entry not found or already deleted',
            ], 404);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'An error occurred while fetching the Name Transfer entry',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }




    // public function searchCustomerDetails(Request $request)
    // {
    //     try {
    //         $searchTerm = $request->input('search');

    //         $meterIssues = MeterIssue::where('is_active', 1)
    //             ->when($searchTerm, function ($query, $searchTerm) {
    //                 $query->whereHas('memberEntry', function ($q) use ($searchTerm) {
    //                     $q->where('member_no', 'like', "%{$searchTerm}%")
    //                         ->orWhere('customer_name_en', 'like', "%{$searchTerm}%")
    //                         ->orWhere('customer_name_np', 'like', "%{$searchTerm}%");
    //                 });
    //             })
    //             ->with(['memberEntry:id,customer_name_en,customer_name_np'])
    //             ->select(
    //                 'id',
    //                 'member_entry_id',
    //                 'meter_no',
    //                 'area_id',
    //                 'transformer_id',
    //                 'purpose_id',
    //                 'phase_id'
    //             )
    //             ->get();
    //                     $dueService = new FindTotalDueAmountServiceAndFindAdvancePayment();

    //         $customers = $meterIssues->map(function ($issue) use ($dueService) {
    //             $memberEntry = $issue->memberEntry;

    //             $latestReading = MeterReadingEntry::where('meter_issue_id', $issue->id)
    //                 ->whereNull('deleted_at')
    //                 ->latest('reading_date_in_ad')
    //                 ->first();
    //           $totalDue = $dueService->getTotalDueAmountByMemberEntry($issue->member_entry_id);

    //             return [
    //                 'member_entry_id' => $issue->member_entry_id,
    //                 'customer_name_en' => $memberEntry?->customer_name_en,
    //                 'customer_name_np' => $memberEntry?->customer_name_np,
    //                 'meter_no' => $issue->meter_no,
    //                 'area_id' => $issue->area_id,
    //                 'transformer_id' => $issue->transformer_id,
    //                 'purpose_id' => $issue->purpose_id,
    //                 'phase_id' => $issue->phase_id,
    //                 'latest_reading' => $latestReading->reading_date_in_ad ?? null,
    //                 'total_due_amount' => $totalDue,
    //             ];
    //         });

    //         if ($customers->isEmpty()) {
    //             return response()->json([
    //                 'message' => 'No active, non-deleted records found for the provided search term.',
    //                 'data' => [],
    //             ], 200);
    //         }

    //         return response()->json([
    //             'message' => 'Customer details retrieved successfully',
    //             'data' => $customers,
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'An error occurred while searching customer details',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }


public function searchCustomerDetails(Request $request)
{
    try {
        $searchTerm = $request->input('search');

        $selectColumns = [
            'id',
            'member_entry_id',
            'meter_no',
            'area_id',
            'transformer_id',
        ];

        if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
            $selectColumns = array_merge($selectColumns, [
                'purpose_id',
                'phase_id',
            ]);
        }

        $meterIssues = MeterIssue::where('is_active', 1)
            ->when($searchTerm, function ($query, $searchTerm) {
                $query->whereHas('memberEntry', function ($q) use ($searchTerm) {
                    $q->where('member_no', 'like', "%{$searchTerm}%")
                      ->orWhere('customer_name_en', 'like', "%{$searchTerm}%")
                      ->orWhere('customer_name_np', 'like', "%{$searchTerm}%");
                });
            })
            ->with(['memberEntry:id,customer_name_en,customer_name_np'])
            ->select($selectColumns)
            ->get();

        $dueService = new FindTotalDueAmountServiceAndFindAdvancePayment();

        $customers = $meterIssues->map(function ($issue) use ($dueService) {
            $memberEntry = $issue->memberEntry;

            $latestReading = MeterReadingEntry::where('meter_issue_id', $issue->id)
                ->whereNull('deleted_at')
                ->latest('reading_date_in_ad')
                ->first();

            $totalDue = $dueService->getTotalDueAmountByMemberEntry($issue->member_entry_id);

            $data = [
                'member_entry_id' => $issue->member_entry_id,
                'customer_name_en' => $memberEntry?->customer_name_en,
                'customer_name_np' => $memberEntry?->customer_name_np,
                'meter_no' => $issue->meter_no,
                'area_id' => $issue->area_id,
                'transformer_id' => $issue->transformer_id,
                'latest_reading' => $latestReading->reading_date_in_ad ?? null,
                'total_due_amount' => $totalDue,
            ];

            if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
                $data['purpose_id'] = $issue->purpose_id;
                $data['phase_id'] = $issue->phase_id;
            }

            return $data;
        });

        if ($customers->isEmpty()) {
            return response()->json([
                'message' => 'No active, non-deleted records found for the provided search term.',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'message' => 'Customer details retrieved successfully',
            'data' => $customers,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while searching customer details',
            'error' => $e->getMessage(),
        ], 500);
    }
}



    public function transferMeterIssue(Request $request, MemberTransferService $transferService)
    {
        try {
            

            $validated = $request->validate([
                'previous_member_entry_id' => 'required|integer',
                'new_member_entry_id' => 'required|integer|different:previous_member_entry_id',
            ]);

            $transferService->transfer(
                $validated['previous_member_entry_id'],
                $validated['new_member_entry_id']
            );

            return response()->json([
                'message' => 'Transfer completed successfully.',
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Record not found: ' . $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while transferring the meter issue',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function membersWithoutMeterIssue(Request $request)
    {
        try {
            $searchTerm = $request->input('search');

            $transferredMemberIds = NameTransferEntry::on('tenant')
                ->pluck('new_member_entry_id')
                ->toArray();

            $members = MemberEntry::on('tenant')
               
                ->where('is_active', 1)
                ->doesntHave('meterIssues')
                ->whereNotIn('id', $transferredMemberIds)
                ->when($searchTerm, function ($query, $searchTerm) {
                    $query->where('member_no', 'like', "%{$searchTerm}%")
                        ->orWhere('customer_name_en', 'like', "%{$searchTerm}%")
                        ->orWhere('customer_name_np', 'like', "%{$searchTerm}%");
                })
                ->select('id', 'member_no', 'customer_name_en', 'customer_name_np')
                ->get();

            if ($members->isEmpty()) {
                return response()->json([
                    'message' => 'No active members without meter issues found.',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'message' => 'Members without meter issues retrieved successfully',
                'data' => $members,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving members without meter issues',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
