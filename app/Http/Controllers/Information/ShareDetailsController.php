<?php

namespace App\Http\Controllers\Information;

use App\Http\Controllers\Controller;
use App\Models\MemberEntry;
use App\Models\ShareEntry;
use App\Models\ShareOpeningEntry;
use App\Models\ShareReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShareDetailsController extends Controller
{
    /**
     * Get full share history for a given customer_id
     */
    public function show(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view share details')) {
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
                ->withoutTrashed()
                ->where('member_entry_id', $customer_id)
                ->first();


            $opening = ShareOpeningEntry::on('tenant')
                ->whereNull('deleted_at')
                ->where('member_entry_id', $customer_id)
                ->orderByDesc('created_at')
                ->first();

            $entries = ShareEntry::on('tenant')
                ->whereNull('deleted_at')
                ->where('member_entry_id', $customer_id)
                ->orderByDesc('created_at')
                ->get();

            $returns = ShareReturn::on('tenant')
                ->whereNull('deleted_at')
                ->where('member_entry_id', $customer_id)
                ->orderByDesc('created_at')
                ->get();

            $heading = [
                "customer_id",
                "customer_name_en",
                "customer_name_np",
                "opening_share",
                "share_entries",
                "share_returns"
            ];

            $toKeyValue = function ($obj) {
                $result = [];
                foreach ($obj as $key => $value) {
                    $result[] = [
                        'key' => $key,
                        'value' => $value
                    ];
                }
                return $result;
            };

            $data = [
                'customer' => [
                    [
                        'key' => 'member_entry_id',
                        'value' => $customer_id
                    ],
                    [
                        'key' => 'customer_name_en',
                        'value' => $member->customer_name_en ?? null
                    ],
                    [
                        'key' => 'customer_name_np',
                        'value' => $member->customer_name_np ?? null
                    ]
                ],
                'opening_share' => $opening ? $toKeyValue([
                    'date_in_bs' => $opening->date_in_bs,
                    'date_in_ad' => $opening->date_in_ad,
                    'share_quantity' => $opening->share_quantity,
                    'share_value' => $opening->share_value,
                    'total_amount' => $opening->total_amount,
                ]) : [],
                'share_entries' => $entries->map(function ($entry) use ($toKeyValue) {
                    return $toKeyValue([
                        'date_in_bs' => $entry->date_in_bs,
                        'date_in_ad' => $entry->date_in_ad,
                        'voucher_no' => $entry->voucher_no,
                        'share_quantity' => $entry->share_quantity,
                        'share_value' => $entry->share_value,
                        'total_amount' => $entry->total_amount,
                    ]);
                })->toArray(),
                'share_returns' => $returns->map(function ($return) use ($toKeyValue) {
                    return $toKeyValue([
                        'date_in_bs' => $return->date_in_bs,
                        'date_in_ad' => $return->date_in_ad,
                        'voucher_no' => $return->voucher_no,
                        'return_share_quantity' => $return->return_share_quantity,
                        'return_share_amount' => $return->return_share_amount,
                    ]);
                })->toArray(),
            ];

            return response()->json([
                'message' => 'Share history retrieved successfully',
                'heading' => $heading,
                'data' => $data
            ]);
        } catch (\Exception $e) {
           return response()->json([
                'message' => 'An error occurred while retrieving share details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
