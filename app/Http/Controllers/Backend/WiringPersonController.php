<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequest;
use App\Http\Requests\UpdateRequest;
use App\Http\Resources\WiringPersonResource;
use App\Models\WiringPerson;
use App\Services\WiringPersonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class WiringPersonController extends Controller
{
    protected WiringPersonService $service;

    public function __construct(WiringPersonService $service)
    {
        $this->service = $service;

        // Controller-level permission checks
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            $action = $request->route()->getActionMethod();

            $permissions = [
                'index' => 'view wiring persons',
                'show' => 'view wiring persons',
                'store' => 'create wiring persons',
                'update' => 'edit wiring persons',
                'destroy' => 'delete wiring persons',
            ];

            // if (isset($permissions[$action]) && !$user->hasPermissionTo($permissions[$action])) {
            //     return response()->json(['message' => 'Unauthorized'], 403);
            // }

            return $next($request);
        });

    }


    public function index(Request $request): JsonResponse
    {
        // if (!$request->user()->hasPermissionTo('view wiring persons')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
            ]);

            $query = WiringPerson::withoutTrashed()->orderBy('id', 'DESC');

            if (!empty($validated['search'])) {
                $search = $validated['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('wiring_person_name', 'like', "%{$search}%")
                        ->orWhere('wiring_person_no', 'like', "%{$search}%");
                });
            }

            $results = $query->paginate(10);

            return response()->json($results, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing/searching wiring persons',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function store(StoreRequest $request): JsonResponse
    {
        try {
            $person = $this->service->create($request->validated());
            return response()->json([
                'message' => 'Wiring person created successfully',
                'data' => new WiringPersonResource($person)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create wiring person',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($wiringPersonId): JsonResponse
    {
        try {
            $wiringPerson = WiringPerson::findOrFail($wiringPersonId);

            return response()->json([
                'success' => true,
                'data' => new WiringPersonResource($wiringPerson)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wiring person',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function update(UpdateRequest $request, $wiringPersonId)
    {
        try {
            // Load the model AFTER tenant is identified
            $wiringPerson = WiringPerson::findOrFail($wiringPersonId);

            // Update using the service
            $updated = $this->service->update($wiringPerson, $request->validated());

            return response()->json([
                'message' => 'Wiring person updated successfully',
                'data' => new WiringPersonResource($updated)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update wiring person',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function destroy($wiringPersonId): JsonResponse
    {
        try {
            $wiringPerson = WiringPerson::findOrFail($wiringPersonId);
            $this->service->delete($wiringPerson);

            return response()->json([
                'success' => true,
                'message' => 'Wiring person deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete wiring person !',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
