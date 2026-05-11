<?php
// app/Http/Controllers/DistrictController.php
namespace App\Http\Controllers\Backend;
use App\Http\Controllers\Controller;

use App\Models\FiscalYear;
use App\Helpers\Helper;
use GuzzleHttp\Psr7\Query;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Doctrine\DBAL\Query\QueryException;

class FiscalYearController extends Controller
{
    public function index()
    {

        try {
            $fiscalYears = FiscalYear::select('id', 'year_en')->get();

            $fiscalYearList = $fiscalYears->map(function ($fy) {
                return [
                    'id' => $fy->id,
                    'name' => $fy->year_en,
                ];
            });

            $currentFiscalYear = FiscalYear::where('status', 1)
                ->select('id', 'year_en')
                ->first();

            $currentFiscalYear = $currentFiscalYear ? [
                'id' => $currentFiscalYear->id,
                'name' => $currentFiscalYear->year_en,
            ] : null;

            return response()->json([
                'message' => 'Fiscal Years fetched successfully.',
                'data' => [
                    'current_fiscal_year' => $currentFiscalYear,
                    'data' => $fiscalYearList,
                    
                ],
               
            ]);


        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Fiscal Years not found !!'], 404);
        } catch (QueryException $e) {

            return response()->json(['message' => 'Database error occurred !!'], 500);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while fetching Fiscal Years.'], 500);
        }
    }



}
