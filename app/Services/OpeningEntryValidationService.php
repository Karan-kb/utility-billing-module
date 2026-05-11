<?php

namespace App\Services;

use App\Models\MeterReadingEntry;
use Illuminate\Validation\ValidationException;

class OpeningEntryValidationService
{
    /**
     * Check if an opening mahasul or advance can be created for a given meter_issue_id.
     *
     * @param int $meterIssueId
     * @throws \Exception if  exists
     */

public function validateOpening(int $meterIssueId)
{
    $existingOpening = MeterReadingEntry::withoutTrashed()
        ->where('meter_issue_id', $meterIssueId)
        ->where('entry_type', 1)
        ->exists();

    if ($existingOpening) {
        throw ValidationException::withMessages([
            'meter_issue_id' => [
                'Cannot create Opening Mahasul or Opening Advance because this meter issue already has a meter reading entry.'
            ]
        ]);
    }

    return true;
}
}