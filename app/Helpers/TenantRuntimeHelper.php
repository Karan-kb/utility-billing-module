<?php

namespace App\Helpers;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

class TenantRuntimeHelper
{
    /**
     * Get software type based on logged-in user
     * 0 = Bidut, 1 = Khanepani
     */
    public static function currentSoftwareType(): int
    {
        $user = Auth::user();

        if (!$user || !$user->company_id) {
            return 0; // default Bidut
        }

        static $type = null;

        if ($type === null) {
            $type = Tenant::where('company_id', $user->company_id)
                ->value('software_type') ?? 0;
        }

        return $type;
    }

    public static function isRuntimeBidut(): bool
    {
        return self::currentSoftwareType() === 0;
    }

    public static function isRuntimeKhanepani(): bool
    {
        return self::currentSoftwareType() === 1;
    }
}