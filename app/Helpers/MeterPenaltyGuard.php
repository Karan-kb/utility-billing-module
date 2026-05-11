<?php

namespace App\Helpers;

use App\Models\Fine;
use App\Models\MeterReadingEntry;
use App\Services\MeterNetDueGuardService;
use Illuminate\Support\Facades\Log;

class MeterPenaltyGuard
{
    /**
     * Determine whether fine / blacklist processing should stop
     */
    public static function shouldStopProcessing(MeterReadingEntry $entry): bool
    {
        // Stop if FINAL status exists
        $hasFinalStatus = Fine::withoutTrashed()
            ->where('meter_reading_entry_id', $entry->id)
            ->where('status', 3)
            ->exists();

        if ($hasFinalStatus)
            return true;

        // Stop if net due <= 0
        $netDueGuard = app(MeterNetDueGuardService::class);
        if ($netDueGuard->shouldStop($entry))
            return true;

        return false;
    }

}
