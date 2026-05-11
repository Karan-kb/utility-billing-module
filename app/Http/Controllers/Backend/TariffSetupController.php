<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\RateAndCapacity;
use App\Models\TariffSetup;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TariffSetupController extends Controller
{
    public function listTariffSetups(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view tariff setup')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }


        try {
            $entries = TariffSetup::query()
                ->select('id', 'rule_name', 'is_active', 'created_at', 'updated_at')
                ->orderBy('created_at', 'desc');
            return response()->json($entries->paginate(10));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving tariff setup records',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function toggleActiveStatus(Request $request, $id)
    {


        try {
            $tariffSetup = TariffSetup::findOrFail($id);

            // If setting to active (is_active = 1), deactivate all other records
            if (!$tariffSetup->is_active) {
                TariffSetup::where('id', '!=', $tariffSetup->id)
                    ->update(['is_active' => 0]);
                // Toggle is_active status
                $tariffSetup->is_active = 1;
                $tariffSetup->save();

                RateAndCapacity::query()->update([
                    'tariff_setup_id' => $tariffSetup->id
                ]);
            } else {
                // If turning OFF active rule → NOT allowed
                return response()->json([
                    'message' => 'At least one tariff setup must remain active.'
                ], 422);
            }

            return response()->json([
                'message' => "Tariff Setup {$tariffSetup->rule_name} active status updated to " . ($tariffSetup->is_active ? 'active' : 'inactive'),
                'is_active' => $tariffSetup->is_active
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Tariff Setup not found or already deleted'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while toggling the tariff setup active status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getActiveTariffSetup()
{
    try {
        $activeTariff = TariffSetup::where('is_active', 1)->firstOrFail();

        return response()->json([
            'message' => 'Active tariff setup retrieved successfully',
            'data' => [
                'id' => $activeTariff->id,
                'rule_name' => $activeTariff->rule_name,
            ]
        ], 200);

    } catch (ModelNotFoundException $e) {
        return response()->json([
            'message' => 'No active tariff setup found'
        ], 404);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error retrieving active tariff setup',
            'error' => $e->getMessage()
        ], 500);
    }
}

}
