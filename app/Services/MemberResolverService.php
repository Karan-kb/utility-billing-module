<?php

namespace App\Services;

use App\Models\MeterIssue;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MemberResolverService
{
    /**
     * Resolve member_entry_id from meter_issue_id
     *
     * @throws ModelNotFoundException
     */
    public function getMemberEntryIdFromMeterIssue(int $meterIssueId): int
    {
        $meterIssue = MeterIssue::select('id', 'member_entry_id')
            ->where('id', $meterIssueId)
            ->whereNull('deleted_at')
            ->first();

        if (!$meterIssue || !$meterIssue->member_entry_id) {
            throw new ModelNotFoundException(
                "Member not found for meter_issue_id: {$meterIssueId}"
            );
        }

        return $meterIssue->member_entry_id;
    }
}
