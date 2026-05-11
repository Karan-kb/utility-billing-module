<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\AccountGroup;
use App\Models\MainGroup;
use App\Models\SubGroup;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\SubGroup\StoreRequest;
use App\Http\Requests\SubGroup\UpdateRequest;
use App\Traits\ProtectedIds;

class SubGroupController extends Controller
{
    use ProtectedIds;

    public function maingroupList(Request $request)
    {

        try {
            $mainGroups = MainGroup::whereNull('deleted_at')
                ->where('is_active', true) // Filter in the database query
                ->get(['id', 'name'])
                ->map(fn($mainGroup) => ['id' => $mainGroup->id, 'name' => $mainGroup->name])
                ->values()
                ->toArray();
            return response()->json([
                "message" => "Main Group List Received !!",
                "data" => $mainGroups
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(["error" => "Main Group not Found !!"], 404);
        } catch (QueryException $e) {
            return response()->json(["error" => "Database error occurred !!"], 500);
        } catch (\Exception $e) {
            return response()->json(["error" => "An unexpected error occurred !!"], 500);
        }
    }


    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $group = SubGroup::create($request->validated());
            return response()->json([
                'message' => 'Sub Group created successfully',
                'data' => $group
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

    public function update(UpdateRequest $request, $id): JsonResponse
    {
        try {
            if ($this->isProtectedId($id, 1, 11)) {
                return response()->json([
                    'message' => 'This sub group cannot be updated.'
                ], 403);
            }
            $group = SubGroup::findOrFail($id);

            if ($this->checkIfUsed($id)) {
                return response()->json(['error' => 'Cannot modify. The item has already been used'], 406);
            }

            $group->update($request->validated());
            return response()->json([
                'message' => 'Sub Group updated successfully',
                'data' => $group
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Sub Group not found!!'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

    private function checkIfUsed($id): bool
    {
        if (AccountGroup::withoutTrashed()->where('sub_group_id', $id)->first()) {
            return true;
        }
        return false;

    }


    public function index(Request $request): JsonResponse
    {
        // if (!$request->user()->hasOrganizationPermission('view sub groups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $search = $request->query('search');

            $query = SubGroup::with('mainGroup:id,name')
                ->withoutTrashed()
                ->orderBy('main_group_id', 'asc')
                ->orderBy('id', 'asc');

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                        ->orWhere('name', 'like', "%{$search}%");
                });
            }

            $subGroups = $query->paginate(10);

            // Basic transformation
            $transformed = $subGroups->getCollection()->map(function ($subGroup) {
                return [
                    'id' => $subGroup->id,
                    'name' => $subGroup->name,
                    'main_group_id' => optional($subGroup->mainGroup)->id,
                    'main_group_name' => optional($subGroup->mainGroup)->name,
                    'is_active' => $subGroup->is_active,
                ];
            });

            $subGroups->setCollection($transformed);

            return response()->json($subGroups);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing sub groups',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getSubGroup(Request $request): JsonResponse
    {

        try {
            $subGroups = SubGroup::with('mainGroup:id,name')
                ->withoutTrashed()
                ->orderBy('main_group_id', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            // Basic transformation
            $transformed = $subGroups->map(function ($subGroup) {
                return [
                    'id' => $subGroup->id,
                    'name' => $subGroup->name,
                    'main_group_id' => optional($subGroup->mainGroup)->id,
                    'main_group_name' => optional($subGroup->mainGroup)->name,
                    'is_active' => $subGroup->is_active,
                ];
            });

            // Return as plain array
            return response()->json($transformed);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing sub groups',
                'error' => $e->getMessage(),
            ], 500);
        }
    }







    public function getById(Request $request, $id): JsonResponse
    {

        // if (!$request->user()->hasOrganizationPermission('view sub groups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        try {
            $group = SubGroup::findOrFail($id);
            return response()->json($group);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Sub Group not found!!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }




    public function destroy(Request $request, $id): JsonResponse
    {

        // if (!$request->user()->hasOrganizationPermission('delete sub groups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        try {
            if ($this->isProtectedId($id, 1, 11)) {
                return response()->json([
                    'message' => 'This sub group cannot be deleted.'
                ], 403);
            }
            if ($this->checkIfUsed($id))
                return response()->json(['error' => 'Cannot not modify. The item has already been used'], 406);

            $group = SubGroup::findOrFail($id);
            $group->delete();
            return response()->json(['message' => 'Sub Group deleted!!']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Sub Group not found!!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }
    public function toggleActive(Request $request, $id): JsonResponse
    {
        // if (!$request->user()->hasOrganizationPermission('edit sub groups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $group = SubGroup::findOrFail($id);

            if ($this->checkIfUsed($id)) {
                return response()->json(['error' => 'Cannot modify. The item has already been used'], 406);
            }

            $group->is_active = !$group->is_active;
            $group->save();

            return response()->json([
                'message' => "Sub Group setup {$group->name} active status updated to " . ($group->is_active ? 'active' : 'inactive'),
                'is_active' => $group->is_active
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Sub Group not found!!'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }
    /**
     * Get all sub groups for a given main group ID
     */
    public function getSubGroupsByMainGroup(Request $request, $mainGroupId): JsonResponse
    {
        // if (!$request->user()->hasOrganizationPermission('view sub groups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $mainGroup = MainGroup::findOrFail($mainGroupId);

            $subGroups = SubGroup::where('main_group_id', $mainGroupId)
                ->whereNull('deleted_at')
                ->get(['id', 'name']);

            return response()->json([
                'message' => "Sub Groups for Main Group {$mainGroup->name}",
                'data' => $subGroups
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Main Group not found!!'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }


}
