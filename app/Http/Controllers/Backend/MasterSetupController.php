<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\MasterSetupHelper;
use App\Helpers\TenantRuntimeHelper;
use App\Http\Controllers\Controller;
use App\Models\AccountHead;
use App\Models\AdvancePayment;
use App\Models\DepositEntry;
use App\Models\DepositReturn;
use App\Models\MahasulReceiptEntry;
use App\Models\MasterSetupType;
use App\Models\MemberEntry;
use App\Models\MeterIssue;
use App\Models\NEAPurchase;
use App\Models\RateAndCapacity;
use Illuminate\Http\Request;
use App\Models\MasterSetup;
use App\Models\MeterInsurance;
use App\Models\NEAPaymentEntry;
use App\Models\NonMemberPayment;
use App\Models\OtherIncomeReceipt;
use App\Models\ShareEntry;
use App\Models\ShareReturn;
use App\Models\UpgradeMeterCapacity;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class MasterSetupController extends Controller
{
    public function index()
    {
        try {
            $types = MasterSetupType::select('id', 'name')->get();

            return response()->json([
                'success' => true,
                'data' => $types
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching master setup types',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function toggleActiveStatus(Request $request, $id)
    {

        try {
            $masterSetup = MasterSetup::withoutTrashed()->findOrFail($id);

            if (
                $masterSetup->master_setup_type_id == 1 &&
                $masterSetup->name_en === 'Irrigation' &&
                $masterSetup->name_np === 'सिंचाई'
            ) {
                return response()->json([
                    'message' => 'The default Irrigation record cannot be deactivated.',
                    'is_active' => true
                ], 403);
            }

            $masterSetup->is_active = !$masterSetup->is_active;
            $masterSetup->save();

            return response()->json([
                'message' => "Master setup {$masterSetup->name_en} active status updated to " . ($masterSetup->is_active ? 'active' : 'inactive'),
                'is_active' => $masterSetup->is_active
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Master setup not found or already deleted'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while toggling the master setup active status',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getOrderNumbers(Request $request)
    {
        try {
            $validated = $request->validate([
                'master_setup_type_id' => 'required|integer|in:1,2,3,4,5,6,7,8',
            ]);

            $typeId = $validated['master_setup_type_id'];

            $maxOrderNo = MasterSetup::where('master_setup_type_id', $typeId)
                ->whereNull('deleted_at')
                ->max('order_no');
            $nextOrderNo = $maxOrderNo ? $maxOrderNo + 1 : 1;

            return response()->json(['order_no' => $nextOrderNo], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the next order number',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a master setup record by ID
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getById($id)
    {
        // if (!request()->user()->hasOrganizationPermission('view master setups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $masterSetup = MasterSetup::withoutTrashed()->findOrFail($id);
            return response()->json(['master_setup' => $masterSetup], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Master setup not found or already deleted',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the master setup',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    private function isProtectedMasterSetup(MasterSetup $entry): bool
    {
        $protected = config('mastersetup_protected');

        if (!isset($protected[$entry->master_setup_type_id])) {
            return false;
        }

        foreach ($protected[$entry->master_setup_type_id] as $item) {
            if (
                $item['name_en'] === $entry->name_en &&
                $item['name_np'] === $entry->name_np
            ) {
                return true;
            }
        }

        return false;
    }

    public function listMasterSetups(Request $request)
    {
        try {
            MasterSetupHelper::ensureDefaultMasterSetupsExist();

            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
                'master_setup_type_id' => [
                    'nullable',
                    'integer',
                    function ($attribute, $value, $fail) {
                        $exists = MasterSetup::on('tenant')
                            ->where('id', $value)
                            ->whereNull('deleted_at')
                            ->exists();

                        if (!$exists) {
                            $fail("The selected {$attribute} is invalid.");
                        }
                    }
                ],
            ]);

            $query = MasterSetup::withoutTrashed();
              if (TenantRuntimeHelper::isRuntimeKhanepani()) {
                    $query->whereNotIn('master_setup_type_id', [1, 3, 4]);
                }
            if (!empty($validated['master_setup_type_id'])) {
                $query->where('master_setup_type_id', $validated['master_setup_type_id']);
            }

            if (!empty($validated['search'])) {
                $search = $validated['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhere('name_np', 'like', "%{$search}%");
                });
            }

            $results = $query->paginate(10);

            return response()->json($results, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing/searching master setups',
                'error' => $e->getMessage(),
            ], 500);
        }
    }






    public function createMasterSetup(Request $request)
    {

        try {
            $validated = $request->validate([
                'master_setup_type_id' => 'required|integer|in:1,2,3,4,5,6,7,8',
                'name_en' => [
                    'required',
                    'string',
                    'max:50',
                    function ($attribute, $value, $fail) use ($request) {
                        if (
                            MasterSetup::withoutTrashed()
                                ->where('master_setup_type_id', $request->input('master_setup_type_id'))
                                ->where('name_en', $value)
                                ->exists()
                        ) {
                            $fail('The name_en must be unique for this type.');
                        }
                    },
                ],
                'name_np' => [
                    'required',
                    'string',
                    'max:50',
                    function ($attribute, $value, $fail) use ($request) {
                        if (
                            MasterSetup::withoutTrashed()
                                ->where('master_setup_type_id', $request->input('master_setup_type_id'))
                                ->where('name_np', $value)
                                ->exists()
                        ) {
                            $fail('The name_np must be unique for this type.');
                        }
                    },
                ],
                'is_active' => 'sometimes|boolean',
            ]);
                if (TenantRuntimeHelper::isRuntimeKhanepani()) {
                            if (in_array($validated['master_setup_type_id'], [1, 3, 4])) {
                                return response()->json([
                                    'message' => 'Cannot create this master setup type for Khanepani company.',
                                ], 422);
                            }
                        }

            $orderNo = MasterSetup::where('master_setup_type_id', $validated['master_setup_type_id'])
                ->whereNull('deleted_at')
                ->max('order_no');

            $orderNo = ($orderNo ?: 0) + 1;
            $masterSetup = MasterSetup::create([
                'master_setup_type_id' => $validated['master_setup_type_id'],
                'name_en' => $validated['name_en'],
                'name_np' => $validated['name_np'],
                'order_no' => $orderNo,
                'is_active' => $validated['is_active'] ?? 1,
            ]);
            return response()->json([
                'message' => "Master setup {$masterSetup->name_en} created successfully!!!",
                'data' => $masterSetup->toArray()
            ], 201);

        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstError = reset($errors)[0] ?? 'Validation failed';
            return response()->json([
                'message' => $firstError,
                'errors' => $errors
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the master setup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function editMasterSetup(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('edit master setups')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $masterSetup = MasterSetup::withoutTrashed()->findOrFail($id);
            if ($this->isProtectedMasterSetup($masterSetup)) {
                return response()->json([
                    'message' => 'This master setup record is a default system value and cannot be edited.',
                ], 403);
            }


            $validated = $request->validate([

                'name_en' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:50',
                    function ($attribute, $value, $fail) use ($request, $masterSetup) {
                        if (
                            MasterSetup::withoutTrashed()
                                ->where('master_setup_type_id', $request->input('master_setup_type_id', $masterSetup->master_setup_type_id))
                                ->where('name_en', $value)
                                ->where('id', '!=', $masterSetup->id)
                                ->exists()
                        ) {
                            $fail('The name_en must be unique for this type.');
                        }
                    },
                ],
                'name_np' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:50',
                    function ($attribute, $value, $fail) use ($request, $masterSetup) {
                        if (
                            MasterSetup::withoutTrashed()
                                ->where('master_setup_type_id', $request->input('master_setup_type_id', $masterSetup->master_setup_type_id))
                                ->where('name_np', $value)
                                ->where('id', '!=', $masterSetup->id)
                                ->exists()
                        ) {
                            $fail('The name_np must be unique for this type.');
                        }
                    },
                ],
                'is_active' => 'sometimes|boolean',
            ]);
            $updateData = [];
            if ($request->has('name_en')) {
                $updateData['name_en'] = $validated['name_en'];
            }
            if ($request->has('name_np')) {
                $updateData['name_np'] = $validated['name_np'];
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = (bool) $validated['is_active'];
            }
            $masterSetup->fill($updateData);
            $saved = $masterSetup->save();


            return response()->json([
                'message' => "Master setup {$masterSetup->name_en} updated successfully",
                'data' => $masterSetup->toArray()
            ], 201);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstError = reset($errors)[0] ?? 'Validation failed';
            return response()->json([
                'message' => $firstError,
                'errors' => $errors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the master setup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


  public function getBank(Request $request)
{
    try {
        $banks = AccountHead::on('tenant')
            ->where('account_group_id', 10)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'name_np'])
            ->map(function ($bank) {
                return [
                    'id' => $bank->id,
                    'name_en' => $bank->name,
                    'name_np' => $bank->name_np,
                ];
            });

        return response()->json([
            'message' => 'Active banks fetched successfully',
            'data' => $banks
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while fetching banks',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    public function deleteMasterSetup(Request $request, $id)
    {

        try {
            $entry = MasterSetup::withoutTrashed()->findOrFail($id);
            if ($this->isProtectedMasterSetup($entry)) {
                return response()->json([
                    'message' => 'This master setup record is a default system value and cannot be deleted.',
                ], 403);
            }

            $typeId = $entry->master_setup_type_id;

            $referencedTables = [];


            if ($typeId == 6) {
                if (MemberEntry::on('tenant')->where('area_id', $id)->exists()) {
                    $referencedTables[] = 'MemberEntry';
                }
                if (MeterIssue::on('tenant')->where('area_id', $id)->exists()) {
                    $referencedTables[] = 'MeterIssue';
                }
            }


            if ($typeId == 4) {
                if (MeterIssue::on('tenant')->where('capacity_id', $id)->exists()) {
                    $referencedTables[] = 'MeterIssue';
                }
                if (RateAndCapacity::on('tenant')->where('capacity_id', $id)->whereNull('deleted_at')->exists()) {
                    $referencedTables[] = 'RateAndCapacity';
                }
            }


            if ($typeId == 1) {
                if (MeterIssue::on('tenant')->where('purpose_id', $id)->exists()) {
                    $referencedTables[] = 'MeterIssue';
                }
                if (RateAndCapacity::on('tenant')->where('purpose_id', $id)->whereNull('deleted_at')->exists()) {
                    $referencedTables[] = 'RateAndCapacity';
                }
            }


            if ($typeId == 3) {
                if (MeterIssue::on('tenant')->where('phase_id', $id)->exists()) {
                    $referencedTables[] = 'MeterIssue';
                }
                if (RateAndCapacity::on('tenant')->where('phase_id', $id)->whereNull('deleted_at')->exists()) {
                    $referencedTables[] = 'RateAndCapacity';
                }
            }


            if ($typeId == 5) {
                if (MeterIssue::on('tenant')->where('transformer_id', $id)->exists()) {
                    $referencedTables[] = 'MeterIssue';
                }
                if (NEAPurchase::on('tenant')->where('transformer_id', $id)->whereNull('deleted_at')->exists()) {
                    $referencedTables[] = 'NEAPurchase';
                }
                if (NEAPaymentEntry::on('tenant')->where('transformer_id', $id)->whereNull('deleted_at')->where('is_cancel', 0)->exists()) {
                    $referencedTables[] = 'NEAPaymentEntry';
                }
            }


            if ($typeId == 7) {
                if (MemberEntry::on('tenant')->where('occupation_id', $id)->exists()) {
                    $referencedTables[] = 'MemberEntry';
                }
            }

            if (!empty($referencedTables)) {
                return response()->json([
                    'message' => "Cannot delete MasterSetup record because it is referenced in active records: " . implode(', ', $referencedTables),
                ], 422);
            }

            $entry->delete();
            return response()->json([
                'message' => 'Master setup deleted successfully',
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Master setup not found or already deleted'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the master setup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
