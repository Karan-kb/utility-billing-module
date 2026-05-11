<?php

namespace App\Services;

use App\Models\CustomerTransaction;

class AdvanceReportService
{
    /**
     * Get all advance grouped by member
     */
    public function getAllAdvanceGrouped($memberNo = null)
    {
        $query = CustomerTransaction::selectRaw('
                member_entry_id,
                SUM(CASE WHEN direction = "CR" THEN amount ELSE 0 END) as total_cr,
                SUM(CASE WHEN direction = "DR" THEN amount ELSE 0 END) as total_dr
            ')
            ->where('charge_type', 9)
            ->whereNull('deleted_at')
            ->groupBy('member_entry_id');

        return $query;
    }

    /**
     * Calculate advance amount safely
     */
    public function calculateAdvance($cr, $dr): float
    {
        return round(max(0, (float)$cr - (float)$dr), 2);
    }
}