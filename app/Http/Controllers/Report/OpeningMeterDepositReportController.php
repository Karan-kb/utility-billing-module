<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\MemberEntry;
use App\Models\MasterSetup;
use App\Models\MeterDepositTransaction;
use App\Models\MeterIssue;
use App\Models\OpeningMeterDepositEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OpeningMeterDepositReportController extends Controller
{
    protected $memberInfoService;

    public function __construct(
        \App\Services\MemberInfoFromMeterIssueService $memberInfoService
    ) {
        $this->memberInfoService = $memberInfoService;
    }

    public function index(Request $request)
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

            ]);

              $query = MeterDepositTransaction::query()
                ->where('transaction_type', 4) 
                ->with([
                    'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np',
                    'meterIssue:id,meter_no',
                ]);

            if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
                $query->whereBetween('date_in_ad', [
                    $validated['date_from'],
                    $validated['date_to']
                ]);
            }

            if (!empty($validated['search'])) {
                $search = $validated['search'];

                $query->where(function ($q) use ($search) {
                    $q->whereHas('meterIssue', function ($mq) use ($search) {
                        $mq->where('meter_no', 'like', "%{$search}%");
                    })
                    ->orWhereHas('meterIssue.memberEntry', function ($mq) use ($search) {
                        $mq->where('member_no', 'like', "%{$search}%")
                           ->orWhere('customer_name_en', 'like', "%{$search}%")
                           ->orWhere('customer_name_np', 'like', "%{$search}%");
                    });
                });
            }

            $entries = $query->get()->map(function ($entry) {

                $memberInfo = $this->memberInfoService
                    ->getMemberInfo($entry->meter_issue_id);

                return [
                    'id' => $entry->id,
                    'meter_issue_id' => $entry->meter_issue_id,
                    'voucher_no' => $entry->voucher_no,
                    'transaction_type' => $entry->transaction_type,

                    'date_in_bs' => $entry->date_in_bs,
                    'date_in_ad' => $entry->date_in_ad,

                    'meter_deposit_amount' => $entry->amount,

                    'meter_no' => $memberInfo['meter_no'],
                    'member_no' => $memberInfo['member_no'],
                    'customer_name_en' => $memberInfo['customer_name_en'],
                    'customer_name_np' => $memberInfo['customer_name_np'],

                    'created_at' => $entry->created_at,
                    'updated_at' => $entry->updated_at,
                ];
            });

            return response()->json([
                'message' => 'Opening meter deposits retrieved successfully',
                'data' => $entries->values()->toArray()
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving opening meter deposits',
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
                'per_page' => 'nullable|integer|min:1|max:1000',
            ]);

           $perPage = $request->input('per_page', 10);

        $query = MeterDepositTransaction::query()
            ->where('transaction_type', 4)
            ->with([
                'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np',
                'meterIssue:id,meter_no',
            ]);

        if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
            $query->whereBetween('date_in_ad', [
                $validated['date_from'],
                $validated['date_to']
            ]);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($q) use ($search) {
                $q->whereHas('meterIssue', function ($mq) use ($search) {
                    $mq->where('meter_no', 'like', "%{$search}%");
                })
                ->orWhereHas('meterIssue.memberEntry', function ($mq) use ($search) {
                    $mq->where('member_no', 'like', "%{$search}%")
                       ->orWhere('customer_name_en', 'like', "%{$search}%")
                       ->orWhere('customer_name_np', 'like', "%{$search}%");
                });
            });
        }
        $paginated = $query->paginate($perPage)->through(function ($entry) {

            $memberInfo = $this->memberInfoService
                ->getMemberInfo($entry->meter_issue_id);

            return [
                'id' => $entry->id,
                'meter_issue_id' => $entry->meter_issue_id,
                'voucher_no' => $entry->voucher_no,
                'transaction_type' => $entry->transaction_type,
                'date_in_bs' => $entry->date_in_bs,
                'date_in_ad' => $entry->date_in_ad,
                'meter_deposit_amount' => $entry->amount,
                'meter_no' => $memberInfo['meter_no'],
                'member_no' => $memberInfo['member_no'],
                'customer_name_en' => $memberInfo['customer_name_en'],
                'customer_name_np' => $memberInfo['customer_name_np'],
                'created_at' => $entry->created_at,
                'updated_at' => $entry->updated_at,
            ];
        });

        return response()->json([
            'message' => 'Opening meter deposits retrieved successfully',
            'data' => $paginated
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'message' => collect($e->errors())->flatten()->first(),
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while retrieving opening meter deposits',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}
