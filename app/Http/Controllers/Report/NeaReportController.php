<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\NEAPaymentEntry;
use App\Models\NEAPurchase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NeaReportController extends Controller
{
    /**
     * Combined NEA report (purchases + payments) without totals
     */
    // public function index(Request $request)
    // {
    //     if (!$request->user()->hasOrganizationPermission('view nea report')) {
    //         return response()->json(['message' => 'Unauthorized'], 403);
    //     }

    //     try {
    //         $validated = $request->validate([
    //             'date_from' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
    //             'date_to'   => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
    //             'month'     => ['nullable', 'integer', 'between:1,12'],
    //             'search'    => 'nullable|string|max:255',
    //         ]);

    //         $dateFrom = $validated['date_from'] ?? null;
    //         $dateTo   = $validated['date_to'] ?? null;
    //         $month    = $validated['month'] ?? null;
    //         $search   = $validated['search'] ?? null;

    //         // Purchases
    //         $purchases = NEAPurchase::withoutTrashed()
    //             ->join('master_setup as ms', 'nea_purchase.transformer_id', '=', 'ms.id')
    //             ->select('nea_purchase.*', 'ms.name_en as transformer_name_en', 'ms.name_np as transformer_name_np')
    //             ->when($dateFrom && $dateTo, fn($q) =>
    //                 $q->whereBetween('nea_purchase.date_in_ad', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
    //             )
    //             ->when($month, fn($q) => $q->where('month', $month))
    //             ->when($search, function ($q) use ($search) {
    //                 $q->where(function ($q2) use ($search) {
    //                     $q2->where('ms.name_en', 'like', "%{$search}%")
    //                        ->orWhere('ms.name_np', 'like', "%{$search}%")
    //                        ->orWhere('nea_purchase.transformer_id', 'like', "%{$search}%");
    //                 });
    //             })
    //             ->get()
    //             ->map(fn($e) => array_merge($e->toArray(), ['_type' => 'purchase']));

    //         $payments = NEAPaymentEntry::withoutTrashed()
    //             ->join('master_setup as ms', 'nea_payments.transformer_id', '=', 'ms.id')
    //             ->select('nea_payments.*', 'ms.name_en as transformer_name_en', 'ms.name_np as transformer_name_np')
    //             ->when($dateFrom && $dateTo, fn($q) =>
    //                 $q->whereBetween('nea_payments.date_in_ad', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
    //             )
    //             ->when($month, fn($q) => $q->where('month', $month))
    //             ->when($search, function ($q) use ($search) {
    //                 $q->where(function ($q2) use ($search) {
    //                     $q2->where('ms.name_en', 'like', "%{$search}%")
    //                        ->orWhere('ms.name_np', 'like', "%{$search}%")
    //                        ->orWhere('nea_payments.transformer_id', 'like', "%{$search}%");
    //                 });
    //             })
    //             ->get()
    //             ->groupBy(fn($item) => $item->transformer_id.'-'.$item->month) // group by transformer + month
    //             ->map(function ($items) {
    //                 $first = $items->first();
    //                 return [
    //                     'id' => null,
    //                     'date_in_bs' => $first->date_in_bs,
    //                     'date_in_ad' => $first->date_in_ad,
    //                     'month' => $first->month,
    //                     'transformer_id' => $first->transformer_id,
    //                     'due_amount' => $items->sum(fn($x) => $x->due_amount),
    //                     'paid_amount' => $items->sum(fn($x) => $x->paid_amount),
    //                     'fine' => $items->sum(fn($x) => $x->fine),
    //                     'rebate' => $items->sum(fn($x) => $x->rebate),
    //                     'total_amount' => $items->sum(fn($x) => $x->total_amount),
    //                     'voucher_no' => implode(',', $items->pluck('voucher_no')->toArray()),
    //                     'payment_by_cash' => $first->payment_by_cash,
    //                     'payment_by_bank' => $first->payment_by_bank,
    //                     'cheque_no' => $first->cheque_no,
    //                     'bank_details' => $first->bank_details,
    //                     'cash_amount' => $first->cash_amount,
    //                     'bank_amount' => $first->bank_amount,
    //                     'is_active' => $first->is_active,
    //                     'deleted_at' => $first->deleted_at,
    //                     'created_at' => $first->created_at,
    //                     'updated_at' => $first->updated_at,
    //                     'transformer_name_en' => $first->transformer_name_en,
    //                     'transformer_name_np' => $first->transformer_name_np,
    //                     '_type' => 'payment',
    //                 ];
    //             })->values();

    //         $allEntries = collect()->merge($purchases)->merge($payments);

    //         $grouped = $allEntries->groupBy('transformer_id')->map(function ($items, $transformerId) {
    //             $first = $items->first();
    //             $purchases = $items->where('_type', 'purchase')->map(fn($x) => collect($x)->except('_type')->toArray())->values();
    //             $payments  = $items->where('_type', 'payment')->map(fn($x) => collect($x)->except('_type')->toArray())->values();

    //             return [
    //                 'transformer_id'      => $transformerId,
    //                 'transformer_name_en' => $first['transformer_name_en'],
    //                 'transformer_name_np' => $first['transformer_name_np'],
    //                 'purchases'           => $purchases,
    //                 'payments'            => $payments,
    //             ];
    //         })->values();

    //         return response()->json([
    //             'message' => 'NEA report retrieved successfully',
    //             'data'    => $grouped,
    //         ], 200);

    //     } catch (ValidationException $e) {
    //         return response()->json([
    //             'message' => collect($e->errors())->flatten()->first(),
    //             'errors'  => $e->errors(),
    //         ], 422);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'An error occurred while retrieving NEA report',
    //             'error'   => $e->getMessage(),
    //         ], 500);
    //     }
    // }


   public function index(Request $request)
{
    // if (!$request->user()->hasOrganizationPermission('view nea report')) {
    //     return response()->json(['message' => 'Unauthorized'], 403);
    // }

    try {
        $validated = $request->validate([
            'date_from' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'date_to'   => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'month'     => ['nullable', 'integer', 'between:1,12'],
            'search'    => 'nullable|string|max:255',
        ]);

        $dateFrom = $validated['date_from'] ?? null;
        $dateTo   = $validated['date_to'] ?? null;
        $month    = $validated['month'] ?? null;
        $search   = $validated['search'] ?? null;

       
        $purchases = NEAPurchase::withoutTrashed()
            ->with('transformer:id,name_en,name_np')
            ->when($dateFrom && $dateTo, fn($q) =>
                $q->whereBetween('date_in_ad', [$dateFrom, $dateTo])
            )
            ->when($month, fn($q) => $q->where('month', $month))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('transformer', function ($t) use ($search) {
                    $t->where('name_en', 'like', "%{$search}%")
                      ->orWhere('name_np', 'like', "%{$search}%");
                })->orWhere('transformer_id', 'like', "%{$search}%");
            })
            ->get()
            ->map(function ($e) {
                return [
                    'id' => $e->id,
                    'fiscal_year_id' => $e->fiscal_year_id,
                    'date_in_bs' => $e->date_in_bs,
                    'date_in_ad' => $e->date_in_ad,
                    'month' => $e->month,
                    'transformer_id' => $e->transformer_id,
                    'total_units' => $e->total_units,
                    'amount' => $e->amount,
                    'transformer_name_en' => $e->transformer?->name_en,
                    'transformer_name_np' => $e->transformer?->name_np,
                    'created_at' => $e->created_at,
                    'updated_at' => $e->updated_at,
                    '_type' => 'purchase',
                ];
            });

        $payments = NEAPaymentEntry::withoutTrashed()
            ->with('transformer:id,name_en,name_np')
            ->when($dateFrom && $dateTo, fn($q) =>
                $q->whereBetween('date_in_ad', [$dateFrom, $dateTo])
            )
            ->when($month, fn($q) => $q->where('month', $month))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('transformer', function ($t) use ($search) {
                    $t->where('name_en', 'like', "%{$search}%")
                      ->orWhere('name_np', 'like', "%{$search}%");
                })->orWhere('transformer_id', 'like', "%{$search}%");
            })
            ->get()
            ->groupBy(fn($item) => $item->transformer_id . '-' . $item->month)
            ->map(function ($items) {

                $first = $items->first();

                return [
                    'id' => $first->id,
                    'date_in_bs' => $first->date_in_bs,
                    'date_in_ad' => $first->date_in_ad,
                    'month' => $first->month,
                    'transformer_id' => $first->transformer_id,

                    'due_amount'    => $items->sum('due_amount'),
                    'paid_amount'   => $items->sum('paid_amount'),
                    'fine_amount'   => $items->sum('fine_amount'),
                    'rebate_amount' => $items->sum('rebate_amount'),
                    'total_amount'  => $items->sum('total_amount'),

                    'voucher_no' => implode(',', $items->pluck('voucher_no')->toArray()),

                    'transformer_name_en' => $first->transformer?->name_en,
                    'transformer_name_np' => $first->transformer?->name_np,

                    'created_at' => $first->created_at,
                    'updated_at' => $first->updated_at,

                    '_type' => 'payment',
                ];
            })->values();

        $allEntries = collect()
            ->merge($purchases)
            ->merge($payments);

        $grouped = $allEntries
            ->groupBy('transformer_id')
            ->map(function ($items, $transformerId) {

                $first = $items->first();

                $purchases = $items->where('_type', 'purchase')
                    ->map(fn($x) => collect($x)->except('_type')->toArray())
                    ->values();

                $payments = $items->where('_type', 'payment')
                    ->map(fn($x) => collect($x)->except('_type')->toArray())
                    ->values();

                return [
                    'transformer_id'      => $transformerId,
                    'transformer_name_en' => $first['transformer_name_en'],
                    'transformer_name_np' => $first['transformer_name_np'],
                    'purchases'           => $purchases,
                    'payments'            => $payments,
                ];
            })
            ->values();

        return response()->json([
            'message' => 'NEA report retrieved successfully',
            'data'    => $grouped,
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'message' => collect($e->errors())->flatten()->first(),
            'errors'  => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while retrieving NEA report',
            'error'   => $e->getMessage(),
        ], 500);
    }
}
    /**
     * Paginated NEA report with advanced search
     */
    public function indexWithPagination(Request $request)
{
    // if (!$request->user()->hasOrganizationPermission('view nea report')) {
    //     return response()->json(['message' => 'Unauthorized'], 403);
    // }

    try {
        $validated = $request->validate([
            'date_from' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'date_to'   => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'month'     => ['nullable', 'integer', 'between:1,12'],
            'search'    => 'nullable|string|max:255',
            'per_page'  => 'nullable|integer|min:1|max:1000',
            'page'      => 'nullable|integer|min:1',
        ]);

        $dateFrom = $validated['date_from'] ?? null;
        $dateTo   = $validated['date_to'] ?? null;
        $month    = $validated['month'] ?? null;
        $search   = $validated['search'] ?? null;
        $perPage  = $validated['per_page'] ?? 15;
        $page     = $validated['page'] ?? 1;

       
        $purchases = NEAPurchase::withoutTrashed()
            ->with('transformer:id,name_en,name_np')
            ->when($dateFrom && $dateTo, fn($q) =>
                $q->whereBetween('date_in_ad', [$dateFrom, $dateTo])
            )
            ->when($month, fn($q) => $q->where('month', $month))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('transformer', function ($t) use ($search) {
                    $t->where('name_en', 'like', "%{$search}%")
                      ->orWhere('name_np', 'like', "%{$search}%");
                })->orWhere('transformer_id', 'like', "%{$search}%");
            })
            ->get()
            ->map(function ($e) {
                return [
                    'id' => $e->id,
                    'fiscal_year_id' => $e->fiscal_year_id,
                    'date_in_bs' => $e->date_in_bs,
                    'date_in_ad' => $e->date_in_ad,
                    'month' => $e->month,
                    'transformer_id' => $e->transformer_id,
                    'total_units' => $e->total_units,
                    'amount' => $e->amount,
                    'transformer_name_en' => $e->transformer?->name_en,
                    'transformer_name_np' => $e->transformer?->name_np,
                    'created_at' => $e->created_at,
                    'updated_at' => $e->updated_at,
                    '_type' => 'purchase',
                ];
            });

        $payments = NEAPaymentEntry::withoutTrashed()
            ->with('transformer:id,name_en,name_np')
            ->when($dateFrom && $dateTo, fn($q) =>
                $q->whereBetween('date_in_ad', [$dateFrom, $dateTo])
            )
            ->when($month, fn($q) => $q->where('month', $month))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('transformer', function ($t) use ($search) {
                    $t->where('name_en', 'like', "%{$search}%")
                      ->orWhere('name_np', 'like', "%{$search}%");
                })->orWhere('transformer_id', 'like', "%{$search}%");
            })
            ->get()
            ->groupBy(fn($item) => $item->transformer_id . '-' . $item->month)
            ->map(function ($items) {

                $first = $items->first();

                return [
                    'id' => $first->id,
                    'date_in_bs' => $first->date_in_bs,
                    'date_in_ad' => $first->date_in_ad,
                    'month' => $first->month,
                    'transformer_id' => $first->transformer_id,

                    'due_amount'    => $items->sum('due_amount'),
                    'paid_amount'   => $items->sum('paid_amount'),
                    'fine_amount'   => $items->sum('fine_amount'),
                    'rebate_amount' => $items->sum('rebate_amount'),
                    'total_amount'  => $items->sum('total_amount'),

                    'voucher_no' => implode(',', $items->pluck('voucher_no')->toArray()),

                    'transformer_name_en' => $first->transformer?->name_en,
                    'transformer_name_np' => $first->transformer?->name_np,

                    'created_at' => $first->created_at,
                    'updated_at' => $first->updated_at,

                    '_type' => 'payment',
                ];
            })->values();

        
        $allEntries = collect()
            ->merge($purchases)
            ->merge($payments);

        $grouped = $allEntries
            ->groupBy('transformer_id')
            ->map(function ($items, $transformerId) {

                $first = $items->first();

                $purchases = $items->where('_type', 'purchase')
                    ->map(fn($x) => collect($x)->except('_type')->toArray())
                    ->values();

                $payments = $items->where('_type', 'payment')
                    ->map(fn($x) => collect($x)->except('_type')->toArray())
                    ->values();

                return [
                    'transformer_id'      => $transformerId,
                    'transformer_name_en' => $first['transformer_name_en'],
                    'transformer_name_np' => $first['transformer_name_np'],
                    'purchases'           => $purchases,
                    'payments'            => $payments,
                ];
            })
            ->values();

        $paginated = new LengthAwarePaginator(
            $grouped->forPage($page, $perPage),
            $grouped->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'message' => 'NEA report retrieved successfully',
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
                'last_page'    => $paginated->lastPage(),
            ],
        ], 200);

    } catch (ValidationException $e) {
        return response()->json([
            'message' => collect($e->errors())->flatten()->first(),
            'errors'  => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while retrieving paginated NEA report',
            'error'   => $e->getMessage(),
        ], 500);
    }
}
}
