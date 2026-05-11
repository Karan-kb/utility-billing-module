<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\OpeningMeterDepositEntry;
use App\Models\DepositEntry;
use App\Models\DepositReturn;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use App\Models\MemberEntry;
use App\Models\MeterDepositTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepositReportController extends Controller
{



    // public function index(Request $request)
    // {
    //     if (!$request->user()->hasOrganizationPermission('view meter deposit report')) {
    //         return response()->json(['message' => 'Unauthorized'], 403);
    //     }

    //     try {
    //         $validated = $request->validate([
    //             'date_from' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
    //             'date_to' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
    //             'search' => 'nullable|string|max:255',
    //         ]);

    //         $dateFrom = $validated['date_from'] ?? null;
    //         $dateTo = $validated['date_to'] ?? null;
    //         $search = $validated['search'] ?? null;


    //         $openingEntries = OpeningMeterDepositEntry::withoutTrashed()
    //             ->with([
    //                 'member' => function ($q) {
    //                     $q->select('member_entry_id', 'customer_name_en', 'customer_name_np')
    //                         ->whereNull('deleted_at')
    //                         ->where('is_active', 1);
    //                 },
    //                 'meterIssueNo:customer_id,meter_no'
    //             ])
    //             ->when($dateFrom && $dateTo, function ($q) use ($dateFrom, $dateTo) {
    //                 $q->whereBetween('date_in_ad', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
    //             })
    //             ->when($search, function ($q) use ($search) {
    //                 $q->where('member_entry_id', 'like', "%{$search}%")
    //                     ->orWhereHas('member', function ($sub) use ($search) {
    //                         $sub->where('customer_name_en', 'like', "%{$search}%")
    //                             ->orWhere('customer_name_np', 'like', "%{$search}%");
    //                     })
    //                     ->orWhereHas('meterIssueNo', function ($sub) use ($search) {
    //                         $sub->where('meter_no', 'like', "%{$search}%");
    //                     });
    //             })
    //             ->get();



    //         $depositEntries = DepositEntry::withoutTrashed()
    //             ->with([
    //                 'member' => function ($q) {
    //                     $q->select('member_entry_id', 'customer_name_en', 'customer_name_np')
    //                         ->whereNull('deleted_at')
    //                         ->where('is_active', 1);
    //                 },
    //                 'meterIssue:customer_id,meter_no',
    //                 'bank:id,name_en,name_np'
    //             ])
    //             ->when($dateFrom && $dateTo, function ($q) use ($dateFrom, $dateTo) {
    //                 $q->whereBetween('date_in_ad', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
    //             })
    //             ->when($search, function ($q) use ($search) {
    //                 $q->where('member_entry_id', 'like', "%{$search}%")
    //                     ->orWhere('voucher_no', 'like', "%{$search}%")
    //                     ->orWhereHas('member', function ($sub) use ($search) {
    //                         $sub->where('customer_name_en', 'like', "%{$search}%")
    //                             ->orWhere('customer_name_np', 'like', "%{$search}%");
    //                     });
    //             })
    //             ->get();

    //         $returnEntries = DepositReturn::withoutTrashed()
    //             ->with([
    //                 'member' => function ($q) {
    //                     $q->select('member_entry_id', 'customer_name_en', 'customer_name_np')
    //                         ->whereNull('deleted_at')
    //                         ->where('is_active', 1);
    //                 },
    //                 'meterIssueNumber:customer_id,meter_no',
    //                 'bankDetails:id,name_en,name_np'
    //             ])
    //             ->when($dateFrom && $dateTo, function ($q) use ($dateFrom, $dateTo) {
    //                 $q->whereBetween('date_in_ad', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);
    //             })
    //             ->when($search, function ($q) use ($search) {
    //                 $q->where('member_entry_id', 'like', "%{$search}%")
    //                     ->orWhere('voucher_no', 'like', "%{$search}%")
    //                     ->orWhereHas('member', function ($sub) use ($search) {
    //                         $sub->where('customer_name_en', 'like', "%{$search}%")
    //                             ->orWhere('customer_name_np', 'like', "%{$search}%");
    //                     });
    //             })
    //             ->get();


    //         // Merge all entries
    //         $allEntries = collect()
    //             ->merge($openingEntries->map(function ($e) {
    //                 $array = $e->toArray();
    //                 $array['meter_no'] = $e->meterIssueNo?->meter_no;
    //                 $array['customer_name_en'] = $e->member?->customer_name_en;
    //                 $array['customer_name_np'] = $e->member?->customer_name_np;
    //                 return array_merge($array, ['_type' => 'opening']);
    //             }))
    //             ->merge($depositEntries->map(function ($e) {
    //                 $array = $e->toArray();
    //                 $array['meter_no'] = $e->meterIssue?->meter_no;
    //                 $array['customer_name_en'] = $e->member?->customer_name_en;
    //                 $array['customer_name_np'] = $e->member?->customer_name_np;
    //                 return array_merge($array, ['_type' => 'deposit']);
    //             }))
    //             ->merge($returnEntries->map(function ($e) {
    //                 $array = $e->toArray();
    //                 $array['meter_no'] = $e->meterIssueNumber?->meter_no;
    //                 $array['customer_name_en'] = $e->member?->customer_name_en;
    //                 $array['customer_name_np'] = $e->member?->customer_name_np;
    //                 return array_merge($array, ['_type' => 'return']);
    //             }));

    //         $grouped = $allEntries->groupBy('member_entry_id')->map(function ($items, $customerId) {
    //             $first = $items->first();

    //             $opening = $items->where('_type', 'opening')->map(fn($x) => collect($x)->except('_type'))->values();
    //             $deposit = $items->where('_type', 'deposit')->map(fn($x) => collect($x)->except('_type'))->values();
    //             $return = $items->where('_type', 'return')->map(fn($x) => collect($x)->except('_type'))->values();

    //             $openingTotal = $opening->sum('meter_deposit_amount');
    //             $depositTotal = $deposit->sum('meter_deposit_amount');
    //             $returnTotal = $return->sum('return_deposit_amount');

    //             $customer_name_en = $first['member']['customer_name_en'] ?? null;
    //             $customer_name_np = $first['member']['customer_name_np'] ?? null;

    //             $meter_no = $first['meter_issue']['meter_no'] ?? $first['meter_no'] ?? null;

    //             return [
    //                 'member_entry_id' => $customerId,
    //                 'customer_name_en' => $customer_name_en,
    //                 'customer_name_np' => $customer_name_np,
    //                 'meter_no' => $meter_no,
    //                 'opening' => $opening,
    //                 'deposit' => $deposit,
    //                 'return' => $return,
    //                 'total' => ($openingTotal + $depositTotal) - $returnTotal,
    //             ];
    //         })->values();


    //         return response()->json($grouped, 200);

    //     } catch (ValidationException $e) {
    //         return response()->json([
    //             'message' => collect($e->errors())->flatten()->first(),
    //             'errors' => $e->errors(),
    //         ], 422);
    //     } catch (\Exception $e) {

    //         return response()->json([
    //             'message' => 'An error occurred while retrieving meter deposit report',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }


protected $memberInfoService;

public function __construct(
    \App\Services\MemberInfoFromMeterIssueService $memberInfoService
) {
    $this->memberInfoService = $memberInfoService;
}
public function index(Request $request)
{
    try {
        $validated = $request->validate([
            'date_from' => [
                'nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/',
                function ($attribute, $value, $fail) {
                    if (!strtotime($value)) $fail('The date_from must be a valid date.');
                },
            ],
            'date_to' => [
                'nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/',
                function ($attribute, $value, $fail) {
                    if (!strtotime($value)) $fail('The date_to must be a valid date.');
                },
            ],
            'search' => 'nullable|string|max:255',
        ]);

        $query = MeterDepositTransaction::withoutTrashed()
            ->whereIn('transaction_type', [1, 2, 4])
            ->with([
                'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np',
                'meterIssue:id,meter_no',
            ]);

        if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
            $query->whereBetween('date_in_ad', [
                $validated['date_from'],
                $validated['date_to'],
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

            $memberInfo = $this->memberInfoService->getMemberInfo($entry->meter_issue_id);

            $type = match((int) $entry->transaction_type) {
                1 => 'deposit',
                2 => 'return',
                4 => 'opening',
            };

            $base = [
                'id'               => $entry->id,
                'meter_issue_id'   => $entry->meter_issue_id,
                'voucher_no'       => $entry->voucher_no,
                'transaction_type' => $entry->transaction_type,
                'date_in_bs'       => $entry->date_in_bs,
                'date_in_ad'       => $entry->date_in_ad,
                'meter_no'         => $memberInfo['meter_no'],
                'member_no'        => $memberInfo['member_no'],
                'customer_name_en' => $memberInfo['customer_name_en'],
                'customer_name_np' => $memberInfo['customer_name_np'],
                'created_at'       => $entry->created_at,
                'updated_at'       => $entry->updated_at,
                '_type'            => $type,
            ];

            // Opening & Deposit
            if ($type !== 'return') {
                return array_merge($base, [
                    'meter_deposit_amount' => $entry->amount,
                ]);
            }

            // Return only
            return array_merge($base, [
                'return_deposit_amount' => $entry->amount,
                'service_charge'        => $entry->service_charge,
                'meter_deposit_amount'  => $entry->amount + $entry->service_charge,
            ]);
        });

        $grouped = $entries->groupBy('member_no')->map(function ($items, $memberNo) {

            $first = $items->first();

            $opening = $items->where('_type', 'opening')->map(fn($x) => collect($x)->except('_type'))->values();
            $deposit = $items->where('_type', 'deposit')->map(fn($x) => collect($x)->except('_type'))->values();
            $return  = $items->where('_type', 'return')->map(fn($x) => collect($x)->except('_type'))->values();

            $openingTotal = $opening->sum('meter_deposit_amount');
            $depositTotal = $deposit->sum('meter_deposit_amount');
            $returnTotal  = $return->sum('meter_deposit_amount');

            return [
                'member_no'        => $memberNo,
                'customer_name_en' => $first['customer_name_en'],
                'customer_name_np' => $first['customer_name_np'],
                'meter_no'         => $first['meter_no'],
                'opening'          => $opening,
                'deposit'          => $deposit,
                'return'           => $return,
                'total'            => ($openingTotal + $depositTotal) - $returnTotal,
            ];
        })->values();

        return response()->json([
            'message' => 'Meter deposit report retrieved successfully',
            'data'    => $grouped,
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'message' => collect($e->errors())->flatten()->first(),
            'errors'  => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while retrieving meter deposit report',
            'error'   => $e->getMessage(),
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
            'date_from' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'date_to' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:1000',
            'page' => 'nullable|integer|min:1',
        ]);

        $dateFrom = $validated['date_from'] ?? null;
        $dateTo   = $validated['date_to'] ?? null;
        $search   = $validated['search'] ?? null;
        $perPage  = $validated['per_page'] ?? 15;
        $page     = $validated['page'] ?? 1;

        $query = MeterDepositTransaction::withoutTrashed()
            ->whereIn('transaction_type', [1, 2, 4])
            ->with([
                'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np',
                'meterIssue:id,meter_no',
            ]);

        if ($dateFrom && $dateTo) {
            $query->whereBetween('date_in_ad', [$dateFrom, $dateTo]);
        }

        if ($search) {
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

            $memberInfo = $this->memberInfoService->getMemberInfo($entry->meter_issue_id);

            $type = match((int) $entry->transaction_type) {
                1 => 'deposit',
                2 => 'return',
                4 => 'opening',
            };

            $base = [
                'id'               => $entry->id,
                'meter_issue_id'   => $entry->meter_issue_id,
                'voucher_no'       => $entry->voucher_no,
                'transaction_type' => $entry->transaction_type,
                'date_in_bs'       => $entry->date_in_bs,
                'date_in_ad'       => $entry->date_in_ad,
                'meter_no'         => $memberInfo['meter_no'],
                'member_no'        => $memberInfo['member_no'],
                'customer_name_en' => $memberInfo['customer_name_en'],
                'customer_name_np' => $memberInfo['customer_name_np'],
                'created_at'       => $entry->created_at,
                'updated_at'       => $entry->updated_at,
                '_type'            => $type,
            ];

            if ($type !== 'return') {
                return array_merge($base, [
                    'meter_deposit_amount' => $entry->amount,
                ]);
            }

            return array_merge($base, [
                'return_deposit_amount' => $entry->amount,
                'service_charge'        => $entry->service_charge,
                'meter_deposit_amount'  => $entry->amount + $entry->service_charge,
            ]);
        });

        $grouped = $entries->groupBy('member_no')->map(function ($items, $memberNo) {

            $first = $items->first();

            $opening = $items->where('_type', 'opening')->map(fn($x) => collect($x)->except('_type'))->values();
            $deposit = $items->where('_type', 'deposit')->map(fn($x) => collect($x)->except('_type'))->values();
            $return  = $items->where('_type', 'return')->map(fn($x) => collect($x)->except('_type'))->values();

            $openingTotal = $opening->sum('meter_deposit_amount');
            $depositTotal = $deposit->sum('meter_deposit_amount');
            $returnTotal  = $return->sum('return_deposit_amount');

            return [
                'member_no'        => $memberNo,
                'customer_name_en' => $first['customer_name_en'],
                'customer_name_np' => $first['customer_name_np'],
                'meter_no'         => $first['meter_no'],
                'opening'          => $opening,
                'deposit'          => $deposit,
                'return'           => $return,
                'total'            => ($openingTotal + $depositTotal) - $returnTotal,
            ];
        })->values();

        $paginated = new LengthAwarePaginator(
            $grouped->forPage($page, $perPage),
            $grouped->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'message' => 'Meter deposit report retrieved successfully',
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'message' => collect($e->errors())->flatten()->first(),
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while retrieving paginated meter deposit report',
            'error' => $e->getMessage(),
        ], 500);
    }
}


}
