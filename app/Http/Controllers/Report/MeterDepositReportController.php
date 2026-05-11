<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\DepositEntry;
use App\Models\MemberEntry;
use App\Models\MasterSetup;
use App\Models\MeterDepositTransaction;
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

class MeterDepositReportController extends Controller
{
    protected $paymentService;
    protected $memberInfoService;

    public function __construct(
        \App\Services\PaymentService $paymentService,
        \App\Services\MemberInfoFromMeterIssueService $memberInfoService
    ) {
        $this->paymentService = $paymentService;
        $this->memberInfoService = $memberInfoService;
    }
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
            ]);

              $query = MeterDepositTransaction::query()
            ->where('transaction_type', 1)
            ->with([
                'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np',
                'meterIssue:id,meter_no',
                'payments.bank:id,name,name_np'
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

        $depositEntries = $query->get()->map(function ($transaction) {

            $memberInfo = $this->memberInfoService
                ->getMemberInfo($transaction->meter_issue_id);

            $paymentData = $this->paymentService
                ->transformPayments($transaction->payments);

            return array_merge([
                'id' => $transaction->id,
                'meter_issue_id' => $transaction->meter_issue_id,
                'voucher_no' => $transaction->voucher_no,
                'transaction_type' => $transaction->transaction_type,

                'date_in_bs' => $transaction->date_in_bs,
                'date_in_ad' => $transaction->date_in_ad,

                'meter_deposit_amount' => $transaction->amount,

                'meter_no' => $memberInfo['meter_no'],
                'member_no' => $memberInfo['member_no'],
                'customer_name_en' => $memberInfo['customer_name_en'],
                'customer_name_np' => $memberInfo['customer_name_np'],

                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at,
            ], $paymentData);
        });

        return response()->json([
            'message' => 'Meter deposit report retrieved successfully',
            'data' => $depositEntries->values()->toArray()
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'message' => collect($e->errors())->flatten()->first(),
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error retrieving meter deposit report',
            'error' => $e->getMessage()
        ], 500);
    }
}



    public function indexWithPagination(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view meter deposit report')) {
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

            $perPage = $validated['per_page'] ?? 15;

            $query = MeterDepositTransaction::query()
                ->where('transaction_type', 1) 
                ->with([
                    'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np',
                    'meterIssue:id,meter_no',
                    'payments.bank:id,name,name_np'
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

            $transactions = $query->paginate($perPage);
              $transactions->getCollection()->transform(function ($transaction) {

                $memberInfo = $this->memberInfoService
                    ->getMemberInfo($transaction->meter_issue_id);

                $paymentData = $this->paymentService
                    ->transformPayments($transaction->payments);

                return array_merge([
                    'id' => $transaction->id,
                    'meter_issue_id' => $transaction->meter_issue_id,
                    'voucher_no' => $transaction->voucher_no,
                    'transaction_type' => $transaction->transaction_type,

                    'date_in_bs' => $transaction->date_in_bs,
                    'date_in_ad' => $transaction->date_in_ad,

                    //'deposit_amount' => $transaction->amount + ($transaction->service_charge ?? 0),
                    'meter_deposit_amount' => $transaction->amount,
                    //'service_charge' => $transaction->service_charge,

                    'meter_no' => $memberInfo['meter_no'],
                    'member_no' => $memberInfo['member_no'],
                    'customer_name_en' => $memberInfo['customer_name_en'],
                    'customer_name_np' => $memberInfo['customer_name_np'],

                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                ], $paymentData);
            });

            return response()->json([
                'message' => 'Meter deposit report retrieved successfully',
            ] + $transactions->toArray());

        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving meter deposit report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
