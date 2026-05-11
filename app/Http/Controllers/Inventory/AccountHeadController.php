<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;

use App\Http\Requests\AccountHead\StoreRequest;
use App\Http\Requests\AccountHead\UpdateRequest;
use App\Models\AccountGroup;
use App\Models\AccountHead;
use App\Models\OtherIncomeSetup;
use App\Models\VoucherSummary;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Traits\ProtectedIds;

class AccountHeadController extends Controller
{
    use ProtectedIds;

    /**
     * Display a listing of account heads with optional search and pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */

    public function index(Request $request): JsonResponse
    {
        // if (!$request->user()->hasOrganizationPermission('view account heads')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $query = AccountHead::with(['accountGroup:id,name'])
                ->whereNull('deleted_at');

            if ($request->filled('search')) {
                $keywords = $request->input('search');
                $query->where(function ($q) use ($keywords) {
                    $q->where('name', 'LIKE', "%{$keywords}%")
                        ->orWhere('name_np', 'LIKE', "%{$keywords}%");
                });
            }
            $entries = $query->paginate(10);

            $entries->getCollection()->transform(function ($item) {
                $isProtected = $this->isProtectedId($item->id, 1, 56);
                $isUsed = $this->checkIfUsed($item->id);
                $item->delete_status = (!$isProtected && !$isUsed);
                // $item->delete_status = (!$isProtected);

                return $item;
            });
            return response()->json($entries);


        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!'], 500);
        }
    }
    public function getAccountHead(Request $request): JsonResponse
    {


        try {
            $entries = AccountHead::with(['accountGroup:id,name'])
                ->whereNull('deleted_at')
                ->where('is_active', 1)
                ->get();

            $entries->transform(function ($item) {
                $isProtected = $this->isProtectedId($item->id, 1, 56);
                $isUsed = $this->checkIfUsed($item->id);
                $item->delete_status = (!$isProtected && !$isUsed);
                return $item;
            });

            return response()->json($entries);

        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!'], 500);
        }
    }


    public function update(UpdateRequest $request, $id): JsonResponse
    {


        try {
            if ($this->checkIfUsed($id)) {
                return response()->json(['error' => 'Cannot modify. The account head has already been used.'], 406);
            }
            if ($this->isProtectedId($id, 1, 56)) {
                return response()->json([
                    'message' => 'This account head cannot be updated.'
                ], 403);
            }
            $accountHead = AccountHead::on('tenant')->findOrFail($id);

            $accountHead->update($request->validated());

            $accountHead->load('accountGroup:id,name');

            return response()->json([
                'message' => 'Account Head updated successfully!',
                'data' => $accountHead
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Account Head not found!!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $accountHead = AccountHead::on('tenant')->create($request->validated());

            return response()->json([
                'message' => 'Account Head created successfully!',
                'data' => $accountHead
            ], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Account Head not found!!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }


    public function getByID(Request $request, $id): JsonResponse
    {
        try {
            $account_head = AccountHead::findOrFail($id);
            return response()->json($account_head);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Account Head not found!!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        // if (!$request->user()->hasOrganizationPermission('delete account heads')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        try {
            if ($this->isProtectedId($id, 1, 56)) {
                return response()->json([
                    'message' => 'This account head cannot be deleted.'
                ], 403);
            }
            if ($this->checkIfUsed($id))
                return response()->json(['error' => 'Cannot not delete. The item has already been used'], 406);

            $account_head = AccountHead::findOrFail($id);
            $account_head->delete();
            return response()->json(['message' => 'Account Head deleted!!']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Account Head not found!!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

    public function toggleActive(Request $request, $id): JsonResponse
    {
        try {
            if ($this->checkIfUsed($id))
                return response()->json(['error' => 'Cannot not modify. The item has already been used'], 406);
            $group = AccountHead::on('tenant')->findOrFail($id);

            $group->is_active = !$group->is_active;
            $group->save();


            return response()->json([
                'message' => "Account Head {$group->name} active status updated to " . ($group->is_active ? 'active' : 'inactive'),
                'is_active' => $group->is_active
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Account Head not found!!'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

    public function getByAccountGroup(Request $request, $subGroupId): JsonResponse
    {
        try {
            $groups = AccountHead::on('tenant')
                ->where('account_group_id', $subGroupId)
                ->where('is_active', 1)
                ->orderBy('name')
                ->get(['id', 'name', 'name_np']);

            return response()->json([
                'message' => 'Account Heads fetched successfully',
                'data' => $groups
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

    public function list( $subGroupId): JsonResponse
    {       
        try {
            $groups = AccountHead::on('tenant')
                ->where('account_group_id', $subGroupId)
                ->where('is_active', 1)
                ->orderBy('name')
                ->get(['id', 'name', 'name_np']);

            return response()->json([
                'message' => 'Account Heads fetched successfully',
                'data' => $groups
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

    private function checkIfUsed($id): bool
    {
        return OtherIncomeSetup::withoutTrashed()->where('account_head_id', $id)->exists();
    }
}
