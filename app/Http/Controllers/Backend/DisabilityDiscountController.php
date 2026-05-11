<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\DisabilityDiscount;
use App\Helpers\NepaliCalendar;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DisabilityDiscountController extends Controller
{
    /**
     * Get the diability discount record record.
     */
    public function get(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view disability discount')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        try {
            $record = DisabilityDiscount::first();

            if (!$record) {
                $record = DisabilityDiscount::create([
                    'units' => 0,
                    'is_applied' => true,
                ]);
            }

            return response()->json([
                'message' => 'Diability discount record retrieved successfully.',
                'data' => $record,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve diability discount record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the diability discount record record.
     */
    public function update(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('update disability discount')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        try {
            $validated = $request->validate([
                'units' => 'required|numeric|min:0',
                'is_applied' => 'required|boolean',
            ]);

            $record = DisabilityDiscount::first();

            if (!$record) {
                $record = DisabilityDiscount::create($validated);
            } else {
                $record->update($validated);
            }

            return response()->json([
                'message' => 'Diability discount record updated successfully.',
                'data' => $record,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update diability discount record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Toggle the apply_status of the disability discount record.
     */

    public function toggleApplyStatus(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('update disability discount')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $record = DisabilityDiscount::first();

            if (!$record) {
                $record = DisabilityDiscount::create([
                    'units' => 0,
                    'is_applied' => true,
                ]);
            } else {
                // Toggle the apply_status
                $record->apply_status = !$record->apply_status;
                $record->save();
            }

            return response()->json([
                'apply_status' => $record->apply_status,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'apply_status' => null,
            ], 500);
        }
    }





}
