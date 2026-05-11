<?php

namespace App\Http\Controllers\Information;

use App\Http\Controllers\Controller;
use App\Models\DepositEntry;
use App\Models\DepositReturn;
use App\Models\OpeningMeterDepositEntry;
use App\Models\MemberEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DepositDetailsController extends Controller
{
    /**
     * Get full deposit history for a given customer_id
     */
    public function show(Request $request)
    {
        

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

            $opening = OpeningMeterDepositEntry::on('tenant')
                ->whereNull('deleted_at')
                ->where('member_entry_id', $customer_id)
                ->orderByDesc('created_at')
                ->first();

            $entries = DepositEntry::on('tenant')
                ->whereNull('deleted_at')
                ->where('member_entry_id', $customer_id)
                ->orderByDesc('created_at')
                ->get();

            $returns = DepositReturn::on('tenant')
                ->whereNull('deleted_at')
                ->where('member_entry_id', $customer_id)
                ->orderByDesc('created_at')
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
                'opening_deposit',
                'deposit_entries',
                'deposit_returns'
            ];

            $data = [
                'opening_deposit' => $opening ? $toKeyValue($opening->toArray()) : [],
                'deposit_entries' => $entries->map(fn($entry) => $toKeyValue($entry->toArray()))->toArray(),
                'deposit_returns' => $returns->map(fn($return) => $toKeyValue($return->toArray()))->toArray(),
            ];

            return response()->json([
                'message' => 'Deposit history retrieved successfully',
                'heading' => $heading,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving deposit details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
