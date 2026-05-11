<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\DiscountAndFine;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class DiscountAndFineController extends Controller
{


    public function listDiscountsAndFines(Request $request)
    {
       


        try {
            $nonDeletedCount = DiscountAndFine::withoutTrashed()->count();

            $showDiscount = $nonDeletedCount >= 1 ? false : true;
            $entries = DiscountAndFine::withoutTrashed()
                ->orderBy('days_after')
                ->get();



            return response()->json([
                'data' => $entries,
                'showDiscount' => $showDiscount
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing discounts and fines',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function toggleActiveStatus(Request $request, $id)
    {

        try {
            $discountAndFine = DiscountAndFine::withoutTrashed()->findOrFail($id);

            $discountAndFine->is_active = !$discountAndFine->is_active;
            $discountAndFine->save();


            return response()->json([
                'message' => "Discounts and fines after {$discountAndFine->days_after} days active status updated to " . ($discountAndFine->is_active ? 'active' : 'inactive'),
                'is_active' => $discountAndFine->is_active
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Discounts and fines not found or already deleted'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while toggling the discounts and fines active status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function hasNonDeletedRecord()
    {
        $exists = DiscountAndFine::withoutTrashed()->exists();

        return response()->json([
            'exists' => $exists
        ]);
    }

    public function createDiscountAndFine(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('create discounts and fines')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $validated = $request->validate([
                'type' => 'required|integer|in:1,2,3',
                'amount_type' => 'required|integer|in:1,2',
                'amount' => [
                    'required',
                    'numeric',
                    'min:0.1',
                    function ($attribute, $value, $fail) use ($request) {
                        $type = $request->input('type');

                        if ($request->input('amount_type') == 1 && floor($value) != $value) {
                            $fail('Amount must be a whole number when amount_type is percent.');
                        }
                        if ($request->input('amount_type') == 1 && floor($value) != $value) {
                            $fail('Amount must be a whole number when amount_type is percent.');
                        }
                        $previousAmount = DiscountAndFine::withoutTrashed()
                            ->where('type', $type)
                            ->max('amount') ?? 0;

                        if ($value <= $previousAmount) {
                            $fail("The amount must be greater than the previous maximum ({$previousAmount}) for this type.");
                        }
                    }
                ],
                'days_after' => [
                    'required',
                    'integer',
                    'min:1',
                    Rule::unique(DiscountAndFine::class, 'days_after')->whereNull('deleted_at'),
                    function ($attribute, $value, $fail) {
                        $maxDaysAfter = DiscountAndFine::withoutTrashed()->max('days_after') ?? 0;
                        if ($value <= $maxDaysAfter) {
                            $fail("The {$attribute} must be greater than the current maximum ({$maxDaysAfter}).");
                        }
                    },
                ],
                'description' => 'nullable|string',
                'is_active' => 'sometimes|boolean',
            ], [
                'amount.numeric' => 'The amount must be a valid number (e.g., 10.00).',
            ]);

            if ($validated['type'] == 1) {
                $discountExists = DiscountAndFine::withoutTrashed()
                    ->where('type', 1)
                    ->exists();

                if ($discountExists) {
                    throw ValidationException::withMessages([
                        'type' => 'Discount already exists and cannot be created again.'
                    ]);
                }
            }

           


            $discountAndFine = DiscountAndFine::create($validated);


            return response()->json([
                'message' => "Discount/Fine for {$discountAndFine->days_after} days created successfully",
                'data' => $discountAndFine->toArray()
            ], 201);
        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();


            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the discount and fine !!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function editDiscountAndFine(Request $request, $id)
    {


        try {

            $currentRecord = DiscountAndFine::withoutTrashed()->findOrFail($id);

            $validated = $request->validate([
                'days_after' => [
                    'sometimes',
                    'integer',
                    'min:1',
                    function ($attribute, $value, $fail) use ($currentRecord) {
                        if (DiscountAndFine::withoutTrashed()->where('days_after', $value)->where('id', '!=', $currentRecord->id)->exists()) {
                            $fail('The days_after value must be unique.');
                        }

                        $maxDaysAfter = DiscountAndFine::withoutTrashed()
                            ->where('id', '!=', $currentRecord->id)
                            ->max('days_after') ?? 0;
                        if ($value <= $maxDaysAfter) {
                            $fail("The {$attribute} must be greater than the current maximum ({$maxDaysAfter}).");
                        }
                    },
                ],
                'type' => [
                    'sometimes',
                    'integer',
                    'in:1,2,3',
                    function ($attribute, $value, $fail) use ($currentRecord) {
                        if ($currentRecord->type == 1 && $value != 1) {
                            $fail('The type of a discount record cannot be changed.');
                        }
                        if ($value == 1) {
                            $existingDiscount = DiscountAndFine::withoutTrashed()
                                ->where('type', 1)
                                ->where('id', '!=', $currentRecord->id)
                                ->exists();
                            if ($existingDiscount) {
                                $fail('Discount can only be the first record and cannot be added again.');
                            }
                        } 
                        // else {
                        //     $discountExists = DiscountAndFine::withoutTrashed()->where('type', 1)->exists();
                        //     if (!$discountExists) {
                        //         $fail('A discount must be created first before adding fines or rebates.');
                        //     }
                        // }
                    },
                ],
                'amount_type' => 'sometimes|integer|in:1,2',
                'amount' => [
                    'sometimes',
                    'numeric',
                    'min:0.1',
                    function ($attribute, $value, $fail) use ($request, $currentRecord) {
                        $type = $request->input('amount_type', $currentRecord->amount_type);
                        if ($type == 1 && floor($value) != $value) {
                            $fail('Amount must be a whole number when amount_type is percent.');
                        }

                        $previousAmount = DiscountAndFine::withoutTrashed()
                            ->where('id', '!=', $currentRecord->id)
                            ->max('amount') ?? 0;

                        if ($value <= $previousAmount) {
                            $fail("The amount must be greater than the previous maximum ({$previousAmount}).");
                        }
                    },
                ],
                'description' => 'sometimes|nullable|string',
                'is_active' => 'sometimes|boolean',
            ], [
                'amount.numeric' => 'The amount must be a valid number (e.g., 10 or 10.00).',
            ]);

            $updateData = [];
            foreach (['days_after', 'type', 'amount_type', 'amount', 'description', 'is_active'] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $validated[$field];
                }
            }
            if (!$request->has('is_active')) {
                $updateData['is_active'] = true;
            }

            $currentRecord->fill($updateData);
            $currentRecord->save();


            return response()->json([
                'message' => "Discount/Fine for {$currentRecord->days_after} days updated successfully",
                'data' => $currentRecord->toArray()
            ], 201);

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();


            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred while updating the discount and fine',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the discount and fine',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getById(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('view discounts and fines')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }


        try {
            $discountfine = DiscountAndFine::withoutTrashed()->findOrFail($id);
            return response()->json(['discount_fine' => $discountfine], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Discount and fine not found or already deleted',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the discount and fine',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function deleteDiscountAndFine(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('delete discounts and fines')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {


            $discountAndFine = DiscountAndFine::withoutTrashed()->findOrFail($id);
            $discountAndFine->delete();

            return response()->json(['message' => "Discount/Fine for {$discountAndFine->days_after} days deleted successfully"]);
        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first(); // Get the first error message


            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the discount and fine',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
