<?php

namespace App\Helpers;

if (!function_exists('isBidut')) {
    function isBidut(): bool
    {
        // Default to 0 (Bidut) if software_type not set
        return config('tenant.software_type', 0) === 0;
    }
}

if (!function_exists('isKhanepani')) {
    function isKhanepani(): bool
    {
        return config('tenant.software_type', 0) === 1;
    }
}