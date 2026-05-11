<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\RebateDiscount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RebateDiscountController extends Controller
{
    public function create(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('create rebate discount')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|in:percent,fixed',
                'value' => 'required|numeric|min:0',
                'min_units' => 'nullable|integer|min:0',
                'max_units' => 'nullable|integer|min:0|gte:min_units',
                'description' => 'nullable|string',
                'status' => 'nullable|boolean',
            ]);

            $rebateDiscount = RebateDiscount::create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'value' => $validated['value'],
                'min_units' => $validated['min_units'] ?? null,
                'max_units' => $validated['max_units'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? 1,
            ]);

            return response()->json([
                'message' => 'Rebate discount created successfully',
                'data' => $rebateDiscount,
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
                'message' => 'An error occurred while creating the discount and fine',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function edit(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('edit rebate discount')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $rebateDiscount = RebateDiscount::find($id);
        if (!$rebateDiscount) {
            return response()->json(['message' => 'Rebate discount not found'], 404);
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|in:percent,fixed',
                'value' => 'required|numeric|min:0',
                'min_units' => 'nullable|integer|min:0',
                'max_units' => 'nullable|integer|min:0|gte:min_units',
                'description' => 'nullable|string',
                'status' => 'nullable|boolean',
            ]);

            $rebateDiscount->update([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'value' => $validated['value'],
                'min_units' => $validated['min_units'] ?? null,
                'max_units' => $validated['max_units'] ?? null,
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'] ?? $rebateDiscount->status,
            ]);

            return response()->json([
                'message' => 'Rebate discount updated successfully',
                'data' => $rebateDiscount,
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
                'message' => 'An error occurred while updating the rebate discount',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function list(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view rebate discount')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $query = RebateDiscount::query();

            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $sortBy = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->get('per_page', 10);
            $rebateDiscounts = $query->paginate($perPage);

            return response()->json([
                'message' => 'Rebate discounts retrieved successfully',
                'data' => $rebateDiscounts,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching rebate discounts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getById(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('view rebate discount')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $rebateDiscount = RebateDiscount::find($id);

        if (!$rebateDiscount) {
            return response()->json(['message' => 'Rebate discount not found'], 404);
        }

        return response()->json([
            'message' => 'Rebate discount retrieved successfully',
            'data' => $rebateDiscount,
        ], 200);
    }

    public function delete(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('delete rebate discount')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $rebateDiscount = RebateDiscount::find($id);

        if (!$rebateDiscount) {
            return response()->json(['message' => 'Rebate discount not found'], 404);
        }

        try {
            $rebateDiscount->delete();

            return response()->json([
                'message' => 'Rebate discount deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the rebate discount',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}