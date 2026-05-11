<?php

namespace App\Services;

use App\Models\MemberEntry;
use App\Models\MeterIssue;
use Exception;

class MeterIssueResolverService
{
    /**
     * Get active meter_issue_id for a given member_no
     *
     * @param string $memberNo
     * @return int
     * @throws Exception
     */
    public function getMeterIssueIdFromMemberNo(string $memberNo): int
    {
        $memberEntry = MemberEntry::on('tenant')
            ->where('member_no', $memberNo)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->first();

        if (!$memberEntry) {
            throw new Exception("Member number '{$memberNo}' not found or inactive.");
        }

        $meterIssue = MeterIssue::on('tenant')
            ->where('member_entry_id', $memberEntry->id)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->first();

        if (!$meterIssue) {
            throw new Exception("No active meter issue found for member number '{$memberNo}'.");
        }

        return $meterIssue->id;
    }
}