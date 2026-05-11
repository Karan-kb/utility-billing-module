<?php

namespace App\Services;

use App\Models\ChangeMeter;
use App\Models\MeterReadingEntry;
use App\Models\MeterIssue;

class KnowMeterStartService
{
    /**
     * Get the starting meter number for a given MeterIssue.
     *
     * @param  int  $meterIssueId
     * @return int|null
     */
    public function getMeterStartNo(int $meterIssueId): ?int
    {
        $meterIssue = MeterIssue::find($meterIssueId);
        if (!$meterIssue) {
            return null;
        }

        // Latest ChangeMeter record (even if used_by is not null)
        $latestChangeMeter = ChangeMeter::where('meter_issue_id', $meterIssueId)
            ->whereNull('deleted_at')
            ->orderBy('id', 'desc')
            ->first();

        // Latest MeterReadingEntry
        $latestReading = MeterReadingEntry::where('meter_issue_id', $meterIssueId)
            ->whereNull('deleted_at')
            ->where('entry_type', 1)
            ->orderBy('reading_date_in_ad', 'desc')
            ->orderBy('id', 'desc')
            ->first();

             if (!$latestReading) {

            $previousIssue = MeterIssue::withoutTrashed()
                ->where('transferred_to', $meterIssueId)
                ->where('is_active', 0)
                ->first();

            if ($previousIssue) {

                $previousReading = MeterReadingEntry::where('meter_issue_id', $previousIssue->id)
                    ->whereNull('deleted_at')
                    ->where('entry_type', 1)
                    ->orderBy('reading_date_in_ad', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($previousReading) {
                    return $previousReading->current_unit;
                }

                return $previousIssue->meter_start_no;
            }
        }

        if ($latestChangeMeter && is_null($latestChangeMeter->used_by)) {
            // If there is a ChangeMeter not yet used
            return $meterIssue->meter_start_no;
        } elseif ($latestReading) {
            // If ChangeMeter was used or no unused ChangeMeter, use last current_unit
            return $latestReading->current_unit;
        } else {
            // No readings yet, fallback to meter start no
            return $meterIssue->meter_start_no;
        }
    }
}