<?php
// app/Http/Controllers/DistrictController.php
namespace App\Http\Controllers\Backend;
use App\Http\Controllers\Controller;

use App\Models\District;

class DistrictController extends Controller
{
    public function index()
    {
        return response()->json(District::all());
    }

    public function byProvince($provinceId)
    {
        $districts = District::where('province_id', $provinceId)->get();

        return response()->json([
            'province_id' => $provinceId,
            'districts' => $districts
        ]);
    }


}
