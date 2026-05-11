<?php

namespace App\Services;

use App\Models\MemberEntry;
use App\Models\MeterIssue;
use App\Models\VoucherSummaryDetail;
use Illuminate\Support\Facades\Log;

class MemberInfoFromMeterIssueService
{
    /**
     * Get meter + member information from meter_issue_id.
     */
    public function getMemberInfo(int $meterIssueId): array
    {
        // Load meter issue + member entry
        $meterIssue = MeterIssue::with('memberEntry')->find($meterIssueId);

        if (!$meterIssue) {
            return [
                'meter_no' => null,
                'member_no' => null,
                'customer_name_en' => null,
                'customer_name_np' => null,
                'pan_no' => null,
                'area_id' => null,
            ];
        }

        $member = $meterIssue->memberEntry;

        return [
            'meter_no' => $meterIssue->meter_no,    // ← NEW
            'area_id' => $meterIssue->area_id,
            'member_no' => $member?->member_no,
            'customer_name_en' => $member?->customer_name_en,
            'customer_name_np' => $member?->customer_name_np,
            'pan_no' => $member?->pan_no,
        ];
    }

    public function getMemberInfoFromMeterIssueIncludingTrashed(int $meterIssueId): array
{
    $meterIssue = MeterIssue::withTrashed()->with(['memberEntry' => function ($q) {
        $q->withTrashed();
    }])->find($meterIssueId);

    if (!$meterIssue) {
        return [
            'meter_no' => null,
            'member_no' => null,
            'customer_name_en' => null,
            'customer_name_np' => null,
            'pan_no' => null,
            'area_id' => null,
        ];
    }

    $member = $meterIssue->memberEntry;

    return [
        'meter_no' => $meterIssue->meter_no,
        'area_id' => $meterIssue->area_id,
        'member_no' => $member?->member_no,
        'customer_name_en' => $member?->customer_name_en,
        'customer_name_np' => $member?->customer_name_np,
        'pan_no' => $member?->pan_no,
    ];
}

public function getMemberInfoFromMemberEntryId(
    int $memberEntryId
): array {

    $member = MemberEntry::find($memberEntryId);

    if (!$member) {
        return [
            'member_no' => null,
            'customer_name_en' => null,
            'customer_name_np' => null,
            'pan_no' => null,
        ];
    }

    return [
        'member_no' => $member->member_no,
        'customer_name_en' => $member->customer_name_en,
        'customer_name_np' => $member->customer_name_np,
        'pan_no' => $member->pan_no,
    ];
}
}
