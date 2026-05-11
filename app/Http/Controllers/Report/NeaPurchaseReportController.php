<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\NEAPurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NeaPurchaseReportController extends Controller
{
    public function index(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view nea purchase report')) {
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
                            $fail("The $attribute must be a valid date.");
                        }
                    },
                ],
                'date_to' => [
                    'nullable',
                    'string',
                    'regex:/^\d{4}-\d{2}-\d{2}$/',
                    function ($attribute, $value, $fail) {
                        if (!strtotime($value)) {
                            $fail("The $attribute must be a valid date.");
                        }
                    },
                ],
                'search' => 'nullable|string|max:255',
                'per_page' => 'nullable|integer|min:1|max:1000',
            ]);

            $perPage = $request->input('per_page', 10);

            $query = NEAPurchase::on('tenant')
                ->withoutTrashed()
                ->with('transformer:id,name_en,name_np')
                ->select('nea_purchases.*');


                if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween('nea_purchases.date_in_ad', [
                    $validated['date_from'],
                    $validated['date_to']
                ]);
            }


            if ($request->filled('search')) {
                $search = $validated['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('nea_purchases.id', 'like', "%{$search}%")
                        ->orWhere('nea_purchases.month', 'like', "%{$search}%")
                        ->orWhereHas('transformer', function ($sub) use ($search) {
                            $sub->where('name_en', 'like', "%{$search}%");
                            $sub->orWhere('name_np', 'like', "%{$search}%");
                        });
                });
            }


            $entries = $query->paginate($perPage)->through(function ($purchase) {
                return [
                    'id'            => $purchase->id,
                    'date_in_bs'    => $purchase->date_in_bs,
                    'date_in_ad'    => $purchase->date_in_ad,
                    'month'         => $purchase->month,
                    'transformer_id'=> $purchase->transformer_id,
                    'transformer_name_en'   => $purchase->transformer ? $purchase->transformer->name_en : null,
                    'transformer_name_np'   => $purchase->transformer ? $purchase->transformer->name_np : null,
                    'total_units'   => $purchase->total_units,
                    'amount'        => $purchase->amount,
                    'created_at'    => $purchase->created_at,
                    'updated_at'    => $purchase->updated_at,
                ];
            });

            return response()->json([
                'message' => 'NEA purchase records retrieved successfully',
            ] + $entries->toArray());

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            return response()->json([
                'message' => collect($allErrors)->flatten()->first(),
                'errors'  => $allErrors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving NEA purchase records',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function indexWithPagination(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view nea purchase report')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $validated = $request->validate([
                'date_from' => 'nullable|date',
                'date_to'   => 'nullable|date',
                'search'    => 'nullable|string|max:255',
                'per_page'  => 'nullable|integer|min:1|max:1000',
            ]);

            $perPage = $request->input('per_page', 10);

            $query = NEAPurchase::on('tenant')
                ->withoutTrashed()
                ->with('transformer:id,name_en,name_np');


                if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween('nea_purchases.date_in_ad', [
                    $validated['date_from'],
                    $validated['date_to']
                ]);
            }


            if ($request->filled('search')) {
                $search = $validated['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('nea_purchases.id', 'like', "%{$search}%")
                        ->orWhere('nea_purchases.month', 'like', "%{$search}%")
                        ->orWhereHas('transformer', function ($sub) use ($search) {
                            $sub->where('name_en', 'like', "%{$search}%");
                            $sub->orWhere('name_np', 'like', "%{$search}%");
                        });
                });
            }

            $entries = $query->paginate($perPage);

            $entries->getCollection()->transform(function ($purchase) {
                return [
                    'id'            => $purchase->id,
                    'date_in_bs'    => $purchase->date_in_bs,
                    'date_in_ad'    => $purchase->date_in_ad,
                    'month'         => $purchase->month,
                    'transformer_id'=> $purchase->transformer_id,
                    // 'transformer'   => $purchase->transformer ? $purchase->transformer->name : null,
                    'transformer_name_en'   => $purchase->transformer ? $purchase->transformer->name_en : null,
                    'transformer_name_np'   => $purchase->transformer ? $purchase->transformer->name_np : null,
                    'total_units'   => $purchase->total_units,
                    'amount'        => $purchase->amount,
                    'created_at'    => $purchase->created_at,
                    'updated_at'    => $purchase->updated_at,
                ];
            });


            return response()->json([
                'message' => 'NEA purchase records retrieved successfully',
            ] + $entries->toArray());

        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving paginated change meter report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}