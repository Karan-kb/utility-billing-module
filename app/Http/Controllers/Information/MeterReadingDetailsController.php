<?php

namespace App\Http\Controllers\Information;

use App\Http\Controllers\Controller;
use App\Models\MeterReadingEntry;
use App\Models\MemberEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MeterReadingDetailsController extends Controller
{
    /**
     * Get full meter reading history for a given customer_id
     */
    public function show(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view meter reading details')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $customer_id = $request->query('member_entry_id');

            if (!$customer_id) {
                return response()->json([
                    'message' => 'customer_id is required'
                ], 422);
            }

            $member = MemberEntry::on('tenant')
                ->whereNull('deleted_at')
                ->where('member_entry_id', $customer_id)->first();

            $readings = MeterReadingEntry::on('tenant')
                ->whereNull('deleted_at')
                ->where('member_entry_id', $customer_id)
                ->orderByDesc('reading_date_in_ad')
                ->get();

            $toKeyValue = function ($array) {
                return collect($array)
                    ->filter(fn($value) => !is_null($value))
                    ->map(fn($value, $key) => ['key' => $key, 'value' => $value])
                    ->values()
                    ->toArray();
            };

            $heading = [
                'member_entry_id',
                'customer_name_en',
                'customer_name_np',
                'meter_readings'
            ];

            $data = [
                'meter_readings' => $readings->map(fn($reading) => $toKeyValue($reading->toArray()))->toArray(),
            ];

            return response()->json([
                'message' => 'Meter reading history retrieved successfully',
                'heading' => $heading,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
           return response()->json([
                'message' => 'An error occurred while retrieving meter reading details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
