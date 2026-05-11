<?php

namespace App\Http\Controllers\Information;

use App\Http\Controllers\Controller;
use App\Models\MemberEntry;
use App\Models\OtherIncomeReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OtherIncomeDetailsController extends Controller
{
    /**
     * Get full other income history for a given customer_id
     */
    public function show(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view other income details')) {
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

            $receipts = OtherIncomeReceipt::on('tenant')
                ->whereNull('deleted_at')
                ->where('member_entry_id', $customer_id)
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
                'other_income_receipts'
            ];

            $data = [
                'other_income_receipts' => $receipts->map(function ($receipt) use ($toKeyValue) {

                    $receiptKeyValue = $toKeyValue($receipt->toArray());

                    $incomeHeads = $receipt->incomeHeadsContent
                        ->pluck('income_head_en')
                        ->unique()
                        ->values()
                        ->toArray();

                    $receiptKeyValue[] = [
                        'key' => 'income_head',
                        'value' => $incomeHeads[0] ?? null,
                    ];

                    return $receiptKeyValue;
                })->toArray(),
            ];

            return response()->json([
                'message' => 'Other income history retrieved successfully',
                'heading' => $heading,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving other income details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
