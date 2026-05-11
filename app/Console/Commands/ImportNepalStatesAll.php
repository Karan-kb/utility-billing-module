<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Sagautam5\LocalStateNepal\Entities\Province as PackageProvince;
use App\Models\Province;
use App\Models\District;
use App\Models\Municipality;

class ImportNepalStatesAll extends Command
{
    protected $signature = 'import:nepal-states-all';
    protected $description = 'Import Nepal provinces, districts & municipalities in EN and NP without package_id';

    public function handle()
    {
        // ----------------------------------------
        // 1) IMPORT ENGLISH (SET ID AS PRIMARY KEY)
        // ----------------------------------------
        $provinceEN = new PackageProvince('en');
        $provincesEN = $provinceEN->getProvincesWithDistrictsWithMunicipalities();

        foreach ($provincesEN as $prov) {
            Province::updateOrCreate(
                ['id' => $prov->id],
                [
                    'name_en' => $prov->name,
                ]
            );

            foreach ($prov->districts as $dist) {
                District::updateOrCreate(
                    ['id' => $dist->id],
                    [
                        'province_id' => $prov->id,
                        'name_en' => $dist->name,
                    ]
                );

                foreach ($dist->municipalities as $muni) {
                    Municipality::updateOrCreate(
                        ['id' => $muni->id],
                        [
                            'district_id' => $dist->id,
                            'name_en' => $muni->name,
                            // 'type' => property_exists($muni, 'type') ? $muni->type : null,
                        ]
                    );
                }
            }
        }

        // -------------------------------
        // 2) IMPORT NEPALI (UPDATE ONLY)
        // -------------------------------
        $provinceNP = new PackageProvince('np');
        $provincesNP = $provinceNP->getProvincesWithDistrictsWithMunicipalities();

        foreach ($provincesNP as $prov) {
            Province::where('id', $prov->id)->update([
                'name_np' => $prov->name
            ]);

            foreach ($prov->districts as $dist) {
                District::where('id', $dist->id)->update([
                    'name_np' => $dist->name,
                ]);

                foreach ($dist->municipalities as $muni) {
                    Municipality::where('id', $muni->id)->update([
                        'name_np' => $muni->name,
                    ]);
                }
            }
        }

        $this->info("Imported Nepal states (EN+NP) successfully");

        return Command::SUCCESS;
    }
}
