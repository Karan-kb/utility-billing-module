<?php
// app/Http/Controllers/MunicipalityController.php
namespace App\Http\Controllers\Backend;
use App\Http\Controllers\Controller;

use App\Models\Municipality;
use App\Models\Province;
use App\Models\TenantProvince;

class MunicipalityController extends Controller
{
    public function getByDistrict($districtId)
    {
        $municipalities = Municipality::where('district_id', $districtId)
            ->orderBy('name_en')
            ->get();

        return response()->json([
            'district_id' => $districtId,
            'municipalities' => $municipalities
        ]);
    }

}
