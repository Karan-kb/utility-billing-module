<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\NepaliCalendar;
use App\Http\Controllers\Controller;

use App\Http\Requests\NeaPurchase\StoreRequest;
use App\Http\Requests\NeaPurchase\UpdateRequest;
use App\Models\MasterSetup;
use App\Models\NEAPaymentEntry;
use App\Models\NEAPurchase;
use App\Models\Payment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class NEAPurchaseController extends Controller
{
     // Create new purchase

    public function create(StoreRequest $request)
    {
        try {

            $purchase = NeaPurchase::create($request->validated());

            return response()->json([
                'message' => 'NEA purchase record created successfully',
                'errors' => [],
                'data' => $purchase
            ], 201);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Failed to create NEA purchase record.',
                'errors' => ['exception' => [$e->getMessage()]],
            ], 500);
        }
    }
    // List all purchases
    public function list(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view nea purchase')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $search = $request->query('search');
            $fromDate = $request->query('from_date');
            $toDate = $request->query('to_date');

            $recordsQuery = NeaPurchase::withoutTrashed()
                ->orderBy('created_at', 'desc')
                ->with('transformer:id,name_en,name_np');

            if (!empty($search)) {
                $recordsQuery->where(function ($q) use ($search) {
                    $q->where('month', $search)
                        ->orWhereHas('transformer', function ($q) use ($search) {
                            $q->where('name_en', 'like', "%{$search}%")
                                ->orWhere('name_np', 'like', "%{$search}%");
                        });
                });
            }

            if (!empty($fromDate) && !empty($toDate)) {
                $recordsQuery->whereBetween('date_in_ad', [$fromDate, $toDate]);
            } elseif (!empty($fromDate)) {
                $recordsQuery->whereDate('date_in_ad', '>=', $fromDate);
            } elseif (!empty($toDate)) {
                $recordsQuery->whereDate('date_in_ad', '<=', $toDate);
            }

            $records = $recordsQuery->paginate(10);

            $records->getCollection()->transform(function ($record) {
                $record->transformer_name_en = $record->transformer->name_en ?? null;
                $record->transformer_name_np = $record->transformer->name_np ?? null;

                unset($record->transformer);

                return $record;
            });

            return response()->json([
                'nea_purchases' => $records
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch NEA purchases',
                'error' => $e->getMessage()
            ], 500);
        }
    }


   

    public function edit(UpdateRequest $request, $id)
    {
        try {
            $purchase = NeaPurchase::findOrFail($id);
            $purchase->update($request->validated());

            return response()->json([
                'message' => 'NEA purchase record updated successfully.',
                'errors' => [],
                'data' => $purchase
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'NEA purchase not found.',
                'errors' => ['id' => ['No NEA purchase exists with the provided ID.']]
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update NEA purchase record.',
                'errors' => ['exception' => [$e->getMessage()]],
            ], 500);
        }
    }


    public function delete(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('delete nea purchase')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        try {
            $purchase = NeaPurchase::findOrFail($id);
            $purchase->delete();
            return response()->json(['message' => 'Record deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Record not found'], 500);
        }
    }

    //Show one by ID
    public function getById($id)
    {
        try {
            $purchase = NeaPurchase::with('transformer')->findOrFail($id);
            return response()->json($purchase, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Record not found'], 404);
        }
    }

    public function listTransformers()
    {
        try {
            $transformers = MasterSetup::where('master_setup_type_id', 5)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->get();
            return response()->json($transformers, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to fetch transformers'], 500);
        }
    }
}
