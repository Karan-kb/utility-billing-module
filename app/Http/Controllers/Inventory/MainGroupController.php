<?php


namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;

use App\Http\Requests\MainGroup\StoreRequest;
use App\Http\Requests\MainGroup\UpdateRequest;
use App\Models\AccountGroup;
use App\Models\MainGroup;
use App\Models\SubGroup;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Traits\ProtectedIds;

class MainGroupController extends Controller
{
    use ProtectedIds;

    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $group = MainGroup::on('tenant')->create($validated);

            return response()->json([
                'message' => 'Main Group created successfully',
                'data' => $group
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(["error" => "Main Group not Found !!"], 404);
        } catch (QueryException $e) {
            return response()->json(["error" => "Database error occurred !!"], 500);
        } catch (\Exception $e) {
            return response()->json(["error" => "An unexpected error occurred !!"], 500);
        }
    }


    public function update(UpdateRequest $request, $id): JsonResponse
    {
        try {
            $group = MainGroup::on('tenant')->findOrFail($id);
            if ($this->isProtectedId($id, 1, 4)) {
                return response()->json([
                    'message' => 'This main group cannot be updated.'
                ], 403);
            }

            if ($this->checkIfUsed($id)) {
                return response()->json([
                    'error' => 'Cannot modify. The item has already been used'
                ], 406);
            }

            $group->update($request->validated());

            return response()->json([
                'message' => 'Main Group updated successfully',
                'data' => $group
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(["error" => "Main Group not Found !!"], 404);
        } catch (QueryException $e) {
            return response()->json(["error" => "Database error occurred !!"], 500);
        } catch (\Exception $e) {
            return response()->json(["error" => "An unexpected error occurred !!"], 500);
        }
    }

    public function index(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view main groups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $search = $request->query('search');

            $query = MainGroup::withoutTrashed()->orderBy('created_at', 'desc');

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                        ->orWhere('name', 'like', "%{$search}%");
                });
            }

            $entries = $query->paginate(10);

            return response()->json($entries);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing other charges',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function show(Request $request, $id): JsonResponse
    {

        // if (!$request->user()->hasOrganizationPermission('view main groups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $group = MainGroup::findOrFail($id);
            return response()->json($group);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Main Group not found!!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        // if (!$request->user()->hasOrganizationPermission('delete main groups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        try {
            if ($this->isProtectedId($id, 1, 4)) {
                return response()->json([
                    'message' => 'This main group cannot be deleted.'
                ], 403);
            }
            if ($this->checkIfUsed($id))
                return response()->json(['error' => 'Cannot not modify. The item has already been used'], 406);

            $group = MainGroup::findOrFail($id);
            $group->delete();
            return response()->json(['message' => 'Main Group deleted!!']);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Main Group not found!!'], 404);
        } catch (QueryException $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }


    private function checkIfUsed($id): bool
    {
        return SubGroup::withoutTrashed()->where('main_group_id', $id)->exists();

    }


    public function toggleActive(Request $request, $id): JsonResponse
    {
        // if (!$request->user()->hasOrganizationPermission('edit main groups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            if ($this->checkIfUsed($id))
                return response()->json(['error' => 'Cannot not modify. The item has already been used'], 406);
            $group = MainGroup::on('tenant')->findOrFail($id);

            if ($this->checkIfUsed($id)) {
                return response()->json([
                    'error' => 'Cannot modify. The item has already been used'
                ], 406);
            }

            $group->is_active = !$group->is_active;
            $group->save();

            return response()->json([
                'message' => "Main Group {$group->name} active status updated to " . ($group->is_active ? 'active' : 'inactive'),
                'is_active' => $group->is_active
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Main Group not found!!'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred!!'], 500);
        }
    }

}
