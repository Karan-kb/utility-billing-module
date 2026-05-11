<?php


namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Fine;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FineController extends Controller
{


    public function index(Request $request): JsonResponse
    {

        try {
            $query = Fine::with(['memberEntry:id,name', 'meterReadingEntry:id,meter_issue_id']);

            if ($request->has('keywords')) {
                $query->where('name', 'LIKE', '%' . $request->input('keywords') . '%');
            }

            return response()->json($query->paginate(10));

        }catch(ModelNotFoundException $e){
            return response()->json(['error' => 'Resource not found!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!'], 500);
        }

    }




}
