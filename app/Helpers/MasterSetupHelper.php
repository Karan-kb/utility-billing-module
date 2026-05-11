<?php

namespace App\Helpers;

use App\Models\MasterSetup;

class MasterSetupHelper
{
    public static function ensureDefaultMasterSetupsExist(): void
    {
        self::insertIfMissing(1, [
            'Residential' => 'आवासीय',
            'Immigration' => 'आप्रवासन',
            'Mill' => 'मिल',
            'Udyog' => 'उद्योग',
            'School' => 'स्कूल',
            'Irrigation'  => 'सिंचाई',
        ]);

        self::insertIfMissing(3, [
            'Single Phase' => 'एक फेज',
            'Three Phase' => 'थ्री फेज़',
        ]);

        self::insertIfMissing(4, [
            '6 amp' => '६ ए यम पी',
            '10 amp' => '१० ए यम पी',
            '16 amp' => '१६ ए यम पी',
            '32 amp' => '३२ ए यम पी',
            '2 HP' => '२ एच पी',
            '5 HP' => '५ एच पी',
            '10 HP' => '१० एच पी',
            '15 HP' => '१५ एच पी',
            '25 HP' => '२५ एच पी',
        ]);
        self::insertIfMissing(7, [
            'Agriculture' => 'कृषि',
            'Business' => 'व्यवसाय',
            'Organization' => 'संगठन',
        ]);
    }

    private static function insertIfMissing(int $typeId, array $items): void
    {
        $lastOrder = MasterSetup::where('master_setup_type_id', $typeId)
            ->whereNull('deleted_at')
            ->max('order_no');

        $order = ($lastOrder ?: 0) + 1;

        foreach ($items as $en => $np) {
            $exists = MasterSetup::where('master_setup_type_id', $typeId)
                ->where('name_en', $en)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                MasterSetup::create([
                    'master_setup_type_id' => $typeId,
                    'name_en' => $en,
                    'name_np' => $np,
                    'order_no' => $order++,
                    'is_active' => true,
                ]);
            }
        }
    }
}
