<?php


namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountGroup\StoreRequest;
use App\Http\Requests\AccountGroup\UpdateRequest;
use App\Models\AccountGroup;
use App\Models\AccountHead;
use App\Models\MainGroup;
use App\Models\VoucherSummary;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\ProtectedIds;

class AccountGroupController extends Controller
{
    use ProtectedIds;


    private function checkIfUsed($id): bool
    {
        return AccountHead::withoutTrashed()->where('account_group_id', $id)->exists();
    }

    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $group = AccountGroup::create($request->validated());
            return response()->json([
                'message' => 'Account Group created successfully',
                'data' => $group
            ], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Account Group not found!!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

    public function update(UpdateRequest $request, $id): JsonResponse
    {
        try {
            if ($this->isProtectedId($id, 1, 60)) {
                return response()->json([
                    'message' => 'This account group cannot be updated.'
                ], 403);
            }
            $group = AccountGroup::on('tenant')->findOrFail($id);

            if ($this->checkIfUsed($id)) {
                return response()->json(['error' => 'Cannot modify. The item has already been used'], 406);
            }

            $group->update($request->validated());

            return response()->json([
                'message' => 'Sub Group updated successfully',
                'data' => $group
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Account Group not found!!'], 404);

        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }


    public function index(Request $request): JsonResponse
    {


        // if (!$request->user()->hasOrganizationPermission('view account groups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        try {
            $query = AccountGroup::with(['subGroup:id,name']);

            if ($request->has('search')) {
                $query->where('name', 'LIKE', '%' . $request->input('search') . '%');
            }
            $entries = $query->paginate(10);
            $entries->getCollection()->transform(function ($item) {

                $isProtected = $this->isProtectedId($item->id, 1, 60);
                $isUsed = $this->checkIfUsed($item->id);

                // delete_status = true ONLY when both are false
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


    public function getAccountGroup(Request $request): JsonResponse
    {

        try {
            $entries = AccountGroup::with(['subGroup:id,name'])
                ->get();

            $entries->transform(function ($item) {
                $isProtected = $this->isProtectedId($item->id, 1, 60);
                $isUsed = $this->checkIfUsed($item->id);

                // delete_status = true ONLY when both are false
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

    public function getById(Request $request, $id): JsonResponse
    {
        // if (!$request->user()->hasOrganizationPermission('view account groups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        try {
            $group = AccountGroup::findOrFail($id);
            return response()->json($group);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Account Group not found!!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        // if (!$request->user()->hasOrganizationPermission('delete account groups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        try {
            if ($this->isProtectedId($id, 1, 60)) {
                return response()->json([
                    'message' => 'This account group cannot be deleted.'
                ], 403);
            }
            if ($this->checkIfUsed($id))
                return response()->json(['error' => 'Cannot not modify. The item has already been used'], 406);

            $group = AccountGroup::findOrFail($id);
            $group->delete();
            return response()->json(['message' => 'Account Group deleted!!']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Account Group not found!!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

    public function toggleActive(Request $request, $id): JsonResponse
    {
        try {
            if ($this->checkIfUsed($id))
                return response()->json(['error' => 'Cannot not modify. The item has already been used'], 406);
            $group = AccountGroup::on('tenant')->findOrFail($id);

            $group->is_active = !$group->is_active;
            $group->save();


            return response()->json([
                'message' => "Account Group {$group->name} active status updated to " . ($group->is_active ? 'active' : 'inactive'),
                'is_active' => $group->is_active
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Account Group not found!!'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

    public function getBySubGroup(Request $request, $subGroupId): JsonResponse
    {
        try {
            $groups = AccountGroup::on('tenant')
                ->where('sub_group_id', $subGroupId)
                ->orderBy('name')
                ->get(['id', 'name', 'is_active']);

            return response()->json([
                'message' => 'Account Groups fetched successfully',
                'data' => $groups
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }
}
