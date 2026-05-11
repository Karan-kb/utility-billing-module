<?php

namespace App\Services;

use App\Models\CustomerTransaction;
use App\Models\MeterReadingEntry;

class CustomerChargeService
{
    public function getTotalCharges(int $memberId, int $meterIssueId, bool $excludeType5 = false): array
    {
        $meterReadingIds = MeterReadingEntry::where('meter_issue_id', $meterIssueId)
            ->pluck('id');

        $transactions = CustomerTransaction::where('member_entry_id', $memberId)
            ->where(function ($query) use ($meterReadingIds) {
                $query->whereIn('reference_id', $meterReadingIds)
                      ->orWhereIn('transaction_type', [1, 2, 5]);
            })
            ->get();

        // Apply exclusion ONLY when required
        if ($excludeType5) {
            $transactions = $transactions->filter(function ($tx) {
                return $tx->transaction_type != 5;
            });
        }

        $totals = [
            1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0,
            6 => 0, 7 => 0, 8 => 0, 9 => 0, 10 => 0, 11 => 0
        ];

        // foreach ($transactions as $tx) {
        //     $multiplier = 0;

        //     if (in_array($tx->transaction_type, [1, 5]) && $tx->direction === 'DR') {
        //         $multiplier = 1;
        //     } elseif ($tx->transaction_type === 2 && $tx->direction === 'CR') {
        //         $multiplier = -1;
        //     }

        //     if ($multiplier !== 0) {
        //         $totals[$tx->charge_type] += $tx->amount * $multiplier;
        //     }
        // }
foreach ($transactions as $tx) {
    $multiplier = 0;

    if (in_array($tx->transaction_type, [1, 5]) && $tx->direction === 'DR') {
        $multiplier = 1;
    } elseif ($tx->transaction_type === 2 && $tx->direction === 'CR') {
        $multiplier = -1;
    }

    if ($multiplier !== 0) {
        // Initialize key if it doesn't exist
        if (!isset($totals[$tx->charge_type])) {
            $totals[$tx->charge_type] = 0;
        }
        $totals[$tx->charge_type] += $tx->amount * $multiplier;
    }
}
        foreach ($totals as $key => $value) {
            $totals[$key] = max(0, $value);
        }

        return $totals;
    }
}