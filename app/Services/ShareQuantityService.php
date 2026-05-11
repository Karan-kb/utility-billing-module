<?php

namespace App\Services;

use App\Models\MemberEntry;
use App\Models\ShareTransaction;
use App\Models\ShareOpeningEntry;

class ShareQuantityService
{
    /**
     * Get existing share quantity and amount for a member_entry_id
     *
     * @param int $memberEntryId
     * @param float $shareUnitPrice Default share unit price
     * @return array
     */
    public function getExistingShares(int $memberEntryId, $shareUnitPrice = 100): array
    {
        // Total purchased shares (transaction_type = 0)
        $totalSharePurchased = ShareTransaction::withoutTrashed()
            ->where('member_entry_id', $memberEntryId)
            ->where('transaction_type', 1)
            ->where('is_cancel', 0)
            ->sum('share_quantity');

        // Total opening shares
        $totalOpeningShares = ShareTransaction::withoutTrashed()
            ->where('member_entry_id', $memberEntryId)
            ->where('transaction_type', 3)
            ->where('is_cancel', 0)
            ->sum('share_quantity');

        // Total returned shares (transaction_type = 1)
        $totalReturned = ShareTransaction::withoutTrashed()
            ->where('member_entry_id', $memberEntryId)
            ->where('transaction_type', 2)
            ->where('is_cancel', 0)
            ->sum('share_quantity');

        // Existing share quantity
        $existingShareQty = max(0, ($totalSharePurchased + $totalOpeningShares) - $totalReturned);





        return [
            'existing_share_quantity' => $existingShareQty,
            'existing_share_amount' => $existingShareQty * $shareUnitPrice,
        ];


    }

    public function getExistingSharesforReturn(int $memberEntryId, $serviceCharge, $shareUnitPrice = 100): array
    {
        // Total purchased shares (transaction_type = 0)
        $totalSharePurchased = ShareTransaction::withoutTrashed()
            ->where('member_entry_id', $memberEntryId)
            ->where('transaction_type', 1)
            ->where('is_cancel', 0)
            ->sum('share_quantity');

        // Total opening shares
        $totalOpeningShares = ShareTransaction::withoutTrashed()
            ->where('member_entry_id', $memberEntryId)
            ->where('transaction_type', 3)
            ->where('is_cancel', 0)
            ->sum('share_quantity');

        // Total returned shares (transaction_type = 1)
        $totalReturned = ShareTransaction::withoutTrashed()
            ->where('member_entry_id', $memberEntryId)
            ->where('transaction_type', 2)
            ->where('is_cancel', 0)
            ->sum('share_quantity');

        // Existing share quantity
        $existingShareQty = max(0, ($totalSharePurchased + $totalOpeningShares) - $totalReturned);





        return [
            'existing_share_quantity' => $existingShareQty,
            'existing_share_amount' => ($existingShareQty * $shareUnitPrice) - $serviceCharge,
        ];


    }
}
