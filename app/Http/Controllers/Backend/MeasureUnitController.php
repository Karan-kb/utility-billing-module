<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\MeasureUnit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Log;

class MeasureUnitController extends Controller
{

    public function create(Request $request): JsonResponse
    {

        // if (!$request->user()->hasOrganizationPermission('create measure units')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        try {
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('measure_units')->whereNull('deleted_at'),
                ],
                'is_active' => 'boolean|required',
                'is_primary' => 'boolean',
                'quantity' => 'integer',
                'symbol' => 'string|max:255',
            ]);

            if (!empty($validated['is_primary'])) {
                MeasureUnit::where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $validated['is_primary'] = $validated['is_primary'] ?? false;
            $validated['is_active'] = $validated['is_active'] ?? true;

            $item = MeasureUnit::create($validated);
            return response()->json($item, 201);

        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred while creating the measure unit.'], 500);
        }
    }
    public function list(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view measure units')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }


        try {
            $entries = MeasureUnit::withoutTrashed();
            return response()->json($entries->paginate(10));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing measure units',
                'error' => $e->getMessage(),
            ], 500);
        }
    }







    public function edit(Request $request, $id): JsonResponse
    {
        
        try {
            $item = MeasureUnit::findOrFail($id);

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('measure_units')
                        ->ignore($id)
                        ->whereNull('deleted_at'),
                ],
                'is_active' => 'boolean|sometimes',
                'is_primary' => 'boolean|sometimes',
                'quantity' => 'integer|sometimes',
                'symbol' => 'string|max:255|sometimes',
            ]);

            if (isset($validated['is_primary']) && $validated['is_primary'] === true) {
                MeasureUnit::where('id', '!=', $id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $item->update($validated);
            $item->refresh();

            return response()->json($item);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Item not found!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred while updating the measure unit.'], 500);
        }
    }


    public function getById(Request $request, $id)
    {



        try {
            $entries = MeasureUnit::withoutTrashed()->findOrFail($id);
            return response()->json(['measure_unit' => $entries], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Measure unit not found or already deleted',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the measure unit',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    public function toggleActiveStatus(Request $request, $id)
    {

        try {
            $measureunit = MeasureUnit::withoutTrashed()->findOrFail($id);

            $measureunit->is_active = !$measureunit->is_active;
            $measureunit->save();


            return response()->json([
                'message' => "Measure unit {$measureunit->customer_name_en} active status updated to " . ($measureunit->is_active ? 'active' : 'inactive'),
                'is_active' => $measureunit->is_active
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Measure unit not found or already deleted'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while toggling the measure unit active status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $item = MeasureUnit::findOrFail($id);
            $item->delete();
            return response()->json(['message' => 'Unit of Measurement deleted!!']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Item not found!!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }
}
