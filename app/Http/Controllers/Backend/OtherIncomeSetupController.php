<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\OtherIncomeSetup\StoreRequest;
use App\Http\Requests\OtherIncomeSetup\UpdateRequest;
use App\Models\AccountHead;
use App\Models\OtherIncomeReceiptContent;
use App\Models\OtherIncomeSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;


class OtherIncomeSetupController extends Controller
{
    public function getById(Request $request, $id)
    {
        

        try {
            $entry = AccountHead::on('tenant')->withoutTrashed()->findOrFail($id);

            return response()->json([
                'other_charges' => $entry
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Other charges not found or already deleted',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the other charges',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function listOtherCharges(Request $request)
    {
        

        try {
            $search = $request->search;



            $query = AccountHead::on('tenant')
                ->where('type', 1)
                ->withoutTrashed()
                ->orderBy('created_at', 'asc');

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('name_np', 'like', "%{$search}%");
                });
            }

            $entries = $query->paginate(10);

            return response()->json($entries);

        } catch (\Exception $e) {


            return response()->json([
                'message' => 'An error occurred while listing other charges !',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function listOtherCharge(Request $request)
    {
        

        try {
            $search = $request->query('search');

            $query = AccountHead::on('tenant')
                ->withoutTrashed()
                ->where('type', 1)
                ->orderBy('created_at', 'desc');

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('name_np', 'like', "%{$search}%");
                });
            }

            $entries = $query->get();


            return response()->json([
                'message' => 'All other charges retrieved successfully.',
                'data' => $entries
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'An error occurred while listing other charges.',
                'error_details' => $e->getMessage(),
            ], 500);
        }
    }


    public function store(StoreRequest $request)
    {
        try {
            DB::beginTransaction();

            $accountHead = AccountHead::create([
                'name' => $request->name,
                'name_np' => $request->name_np,
                'type' => 1,
                'account_group_id' => 35,
                'amount' => $request->amount,
                'is_active' => 1,
            ]);



            DB::commit();

            return response()->json([
                'message' => "Other charge  created successfully!.",
                'data' => $accountHead
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while creating the other charge.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function update(UpdateRequest $request, $id)
    {
        try {
            if ($this->checkIfUsed($id)) {
                return response()->json(['error' => 'Cannot modify. The item has already been used'], 406);
            }
            DB::beginTransaction();


            $accountHead = AccountHead::find($id);

            if ($accountHead) {
                $accountHead->update([
                    'name' => $request->name,
                    'name_np' => $request->name_np,
                    'type' => 1,
                    'amount' => $request->amount,
                    'is_active' => 1,
                ]);
            }




            $accountHead->refresh(); // ensure fresh values

            DB::commit();

            return response()->json([
                'message' => "Other charge  updated successfully.",
                'data' => $accountHead
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while updating the other charge.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function destroy(Request $request, $id)
    {
        

        try {
            if ($this->checkIfUsed($id)) {
                return response()->json(['error' => 'Cannot delete. The item has already been used'], 406);
            }
            $accountHead = AccountHead::withoutTrashed()->findOrFail($id);

            if (!$accountHead) {
                return response()->json([
                    'message' => 'Other charge not found or already deleted'
                ], 404);
            }

            $accountHead->delete();



            return response()->json([
                'message' => "Other charge deleted successfully"
            ], 200);

        } catch (ValidationException $e) {

            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();

            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors
            ], 422);

        } catch (ModelNotFoundException) {

            return response()->json([
                'message' => 'Other charge not found or already deleted',
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'An error occurred while deleting the other charge !',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    private function checkIfUsed($id): bool
    {
        return OtherIncomeReceiptContent::withoutTrashed()->where('account_head_id', $id)->exists();
    }

    public function toggleActiveStatus(Request $request, $id)
    {
        try {
            $accountHead = AccountHead::on('tenant')->findOrFail($id);

            $accountHead->is_active = $request->is_active;
            $accountHead->update();

            return response()->json([
                'message' => "Other charge {$accountHead->name} active status updated to " . ($accountHead->is_active ? 'active' : 'inactive'),
                'is_active' => $accountHead->is_active
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Other charge not found or already deleted'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while toggling the other charge active status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
