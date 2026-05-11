<?php

namespace App\Services;

use App\Models\MeterReadingEntry;
use App\Models\MahasulReceiptEntry;

class MeterReadingFindService
{
    /**
     * Get meter reading entry IDs between previous and current Mahasul receipt
     *
     * @param int $meterIssueId
     * @param int $currentReceiptId
     * @return \Illuminate\Support\Collection
     */
    public function getMeterReadingIdsBetweenReceipts(int $meterIssueId, int $currentReceiptId)
    {
        // Get current Mahasul receipt
        $currentReceipt = MahasulReceiptEntry::findOrFail($currentReceiptId);

        // Find previous Mahasul receipt for the same meter_issue_id
        $previousReceipt = MahasulReceiptEntry::where('meter_issue_id', $meterIssueId)
            ->where('created_at', '<', $currentReceipt->created_at)
            ->where('is_cancel', 0)
            ->orderBy('created_at', 'desc')
            ->first();

        // Query meter readings in the range
        $query = MeterReadingEntry::where('meter_issue_id', $meterIssueId);

        if ($previousReceipt) {
            $query->where('created_at', '>', $previousReceipt->created_at)
                  ->where('created_at', '<=', $currentReceipt->created_at);
        } else {
            $query->where('created_at', '<=', $currentReceipt->created_at);
        }

        // Return only the IDs
        return $query->pluck('id');
    }
}
