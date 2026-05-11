<?php

namespace App\Services;

use App\Helpers\NepaliCalendar;

class DateConverterService
{
    /**
     * Convert AD (Y-m-d or datetime) → BS (Y-m-d)
     */
    public function adToBs(?string $adDate): ?string
    {
        if (!$adDate) {
            return null;
        }

        // Handle full datetime like: 2026-03-24T18:15:00.000000Z
        $formatted = date('Y-m-d', strtotime($adDate));

        return NepaliCalendar::adToBs($formatted);
    }

    /**
     * Convert BS → AD
     */
    public function bsToAd(?string $bsDate): ?string
    {
        if (!$bsDate) {
            return null;
        }

        return NepaliCalendar::bsToAd($bsDate);
    }

    /**
     * AD → BS with Nepali digits
     */
    public function adToBsNepali(?string $adDate): ?string
    {
        $bs = $this->adToBs($adDate);

        return $bs
            ? NepaliCalendar::numberToNepali($bs)
            : null;
    }
}