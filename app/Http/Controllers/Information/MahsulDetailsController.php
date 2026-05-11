<?php

namespace App\Http\Controllers\Information;

use App\Http\Controllers\Controller;
use App\Models\MahasulReceiptEntry;
use App\Models\OpeningMahasulBalanceEntry;
use App\Models\MemberEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MahsulDetailsController extends Controller
{
    /**
     * Get full mahsul history for a given customer_id
     */
    public function show(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view mahsul details')) {
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

            $opening = OpeningMahasulBalanceEntry::on('tenant')
                ->whereNull('deleted_at')
                ->where('member_entry_id', $customer_id)
                ->orderByDesc('created_at')
                ->first();

            $receipts = MahasulReceiptEntry::on('tenant')
                ->where('member_entry_id', $customer_id)
                ->whereNull('deleted_at')
                ->orderByDesc('date_in_ad')
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
                'opening_mahsul_balance',
                'mahsul_receipts'
            ];

            $data = [
                'opening_mahsul_balance' => $opening ? $toKeyValue($opening->toArray()) : [],
                'mahsul_receipts' => $receipts->map(fn($receipt) => $toKeyValue($receipt->toArray()))->toArray(),
            ];

            return response()->json([
                'message' => 'Mahsul history retrieved successfully',
                'heading' => $heading,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
              return response()->json([
                'message' => 'An error occurred while retrieving mahsul details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
