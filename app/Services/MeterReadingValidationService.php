<?php

namespace App\Services;

use App\Models\MeterReadingEntry;
use App\Models\MeterIssue;
use Illuminate\Validation\ValidationException;

class MeterReadingValidationService
{
     public function validateCreation(int $meterIssueId, int $month, string $readingDateBs, int $fiscalYearId): void
    {

        if ($month < 1 || $month > 12) {
            throw ValidationException::withMessages([
                'reading_month_in_bs' => 'Reading month must be between 1 (Baishakh) and 12 (Chaitra).'
            ]);
        }

        $meterIssue = MeterIssue::withTrashed()->find($meterIssueId);
        if (!$meterIssue) {
            throw ValidationException::withMessages([
                'meter_issue_id' => 'Meter issue not found.'
            ]);
        }

        $skipMonthLoop = ($meterIssue->purpose_id == 6);

        $hasCurrentIssueReadings = MeterReadingEntry::withoutTrashed()
            ->where('meter_issue_id', $meterIssueId)
            ->where('entry_type', 1)
            ->exists();

        $meterIssueIds = [$meterIssueId];

        if (!$hasCurrentIssueReadings) {

            $previousIssue = MeterIssue::withTrashed()
                ->where('transferred_to', $meterIssueId)
                ->first();

            if ($previousIssue) {
                $meterIssueIds[] = $previousIssue->id;
            }
        }

        $exists = MeterReadingEntry::withoutTrashed()
            ->whereIn('meter_issue_id', $meterIssueIds)
            ->where('reading_month_in_bs', $month)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('entry_type', 1)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'reading_month_in_bs' =>
                    'A meter reading already exists for this meter, month and fiscal year.'
            ]);
        }

            if ($hasCurrentIssueReadings) {
            // Once new meter has its own record, follow its own loop only
            $previousEntry = MeterReadingEntry::withoutTrashed()
                ->where('meter_issue_id', $meterIssueId)
                ->where('entry_type', 1)
                ->orderBy('reading_date_in_bs', 'desc')
                ->first();
        } else {
            // Before first reading of new meter, check both (transfer case)
            $previousEntry = MeterReadingEntry::withoutTrashed()
                ->whereIn('meter_issue_id', $meterIssueIds)
                ->where('entry_type', 1)
                ->orderBy('reading_date_in_bs', 'desc')
                ->first();
        }
        if ($previousEntry) {

            if ($readingDateBs < $previousEntry->reading_date_in_bs) {
                throw ValidationException::withMessages([
                    'reading_date_in_bs' =>
                        'Reading date cannot be earlier than the previous meter reading date (' .
                        $previousEntry->reading_date_in_bs . ').'
                ]);
            }
                $nepaliMonths = [
                    1 => 'Baishakh',
                    2 => 'Jestha',
                    3 => 'Ashadh',
                    4 => 'Shrawan',
                    5 => 'Bhadra',
                    6 => 'Ashwin',
                    7 => 'Kartik',
                    8 => 'Mangsir',
                    9 => 'Poush',
                    10 => 'Magh',
                    11 => 'Falgun',
                    12 => 'Chaitra',
                ];
            if (!$skipMonthLoop) {

                $lastMonth = (int) $previousEntry->reading_month_in_bs;

                $expectedMonth = $lastMonth == 12 ? 1 : $lastMonth + 1;

                if ($month != $expectedMonth) {

                    $lastMonthName = $nepaliMonths[$lastMonth] ?? $lastMonth;
                    $expectedMonthName = $nepaliMonths[$expectedMonth] ?? $expectedMonth;

                    throw ValidationException::withMessages([
                        'reading_month_in_bs' =>
                            "Invalid reading month sequence. After {$lastMonthName}, the next reading must be {$expectedMonthName}."
                    ]);
                }
            }
        }
    }
   
}