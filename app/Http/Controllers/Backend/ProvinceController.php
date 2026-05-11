<?php
// app/Http/Controllers/ProvinceController.php
namespace App\Http\Controllers\Backend;
use App\Http\Controllers\Controller;

use App\Models\Province;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    public function index()
    {
        $provinces = Province::all();
        return response()->json($provinces);
    }

    public function show($id)
    {
        $province = Province::with('districts.municipalities')->findOrFail($id);
        return response()->json($province);
    }
}
