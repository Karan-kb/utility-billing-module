<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Imports\OpeningShareEntryImport;
use App\Models\MemberEntry;
use App\Models\ShareOpeningEntry;
use App\Models\FiscalYear;
use App\Models\ShareTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ImportValidationException;



class ShareOpeningEntryController extends Controller
{

    public function listShareOpeningEntries(Request $request)
    {
       

        try {
            $entries = ShareTransaction::withoutTrashed()
                ->where('transaction_type', 3)
                ->leftJoin('member_entries as m', 'share_transactions.member_entry_id', '=', 'm.id')
                ->select(
                    'share_transactions.*',
                    'm.customer_name_en',
                    'm.customer_name_np'
                )
                ->orderBy('member_entry_id', 'asc')
                ->paginate(10);

            return response()->json([
                'message' => 'Share opening entries fetched successfully !',
                'data' => $entries
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing share opening entries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function listCustomerDetails(Request $request, $member_no)
    {
        // if (!$request->user()->hasOrganizationPermission('view share opening entries')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $validator = validator(['member_no' => $member_no], [
                'member_no' => [
                    'required',
                    'string',
                    'max:10',
                    'exists:tenant.member_entries,member_no,deleted_at,NULL,is_active,1',
                ],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $customerDetails = MemberEntry::where('member_entries.member_no', $member_no)
                ->where('member_entries.is_active', 1)

                ->select(
                    'member_entries.customer_name_en',
                    'member_entries.customer_name_np',

                )
                ->get();

            if ($customerDetails->isEmpty()) {
                return response()->json([
                    'message' => 'No active, non-deleted records found for the provided member_no.',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'message' => 'Customer details retrieved successfully',
                'data' => $customerDetails,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving customer details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getById(Request $request, $id)
    {
        try {
            $entry = ShareTransaction::withoutTrashed()
                ->where('transaction_type', 3)
                ->findOrFail($id);

            $customer = MemberEntry::select('id', 'customer_name_en', 'customer_name_np')
                ->find($entry->member_entry_id);

            $data = [
                'id' => $entry->id,
                'date_in_bs' => $entry->date_in_bs,
                'date_in_ad' => $entry->date_in_ad,
                'member_no' => $entry->member_entry_id,
                'customer_name_en' => $customer->customer_name_en ?? null,
                'customer_name_np' => $customer->customer_name_np ?? null,
                'share_type' => $entry->share_type,
                'share_certificate_no' => $entry->share_certificate_no,
                'share_quantity' => $entry->share_quantity,
                'share_value' => $entry->share_value,
                'amount' => $entry->amount,
                'created_at' => $entry->created_at,
                'updated_at' => $entry->updated_at,
            ];

            return response()->json(['share_opening_entry' => $data], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Share Opening Entry not found or already deleted',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the Share Opening Entry',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }



    public function searchCustomerDetails(Request $request)
    {

        try {
            $searchTerm = $request->input('search');

            $customers = MemberEntry::where('is_active', 1)
                ->where(function ($query) use ($searchTerm) {
                    $query->where('member_no', 'like', "%{$searchTerm}%")
                        ->orWhere('customer_name_en', 'like', "%{$searchTerm}%")
                        ->orWhere('customer_name_np', 'like', "%{$searchTerm}%");
                })
                ->select(
                    'member_no',
                    'customer_name_en',
                    'customer_name_np'
                )
                ->get();

            if ($customers->isEmpty()) {
                return response()->json([
                    'message' => 'No active, non-deleted records found for the provided search term.',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'message' => 'Customer details retrieved successfully',
                'data' => $customers,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while searching customer details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function createShareOpeningEntry(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('create share opening entries')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $validated = $request->validate([
                'date_in_bs' => [
                    'required',
                    'string',
                    'max:10',
                    'regex:/^\d{4}-\d{2}-\d{2}$/',
                    function ($attribute, $value, $fail) {
                        try {
                            $adDate = \App\Helpers\NepaliCalendar::bsToAd($value);
                            $today = date('Y-m-d');
                            if ($adDate > $today) {
                                $fail('The ' . $attribute . ' cannot be a future date.');
                            }
                        } catch (\Exception $e) {
                            $fail('The ' . $attribute . ' is not a valid BS date.');
                        }
                    },
                ],
                'date_in_ad' => [
                    'required',
                    'string',
                    'max:10',
                    'regex:/^\d{4}-\d{2}-\d{2}$/',
                    function ($attribute, $value, $fail) {
                        $today = date('Y-m-d');
                        if ($value > $today) {
                            $fail('The ' . $attribute . ' cannot be a future date.');
                        }
                    },
                ],
                'member_entry_id' => [
                    'required',
                    'integer',
                    'exists:tenant.member_entries,id,deleted_at,NULL,is_active,1',
                    function ($attribute, $value, $fail) {
                        if (
                            ShareTransaction::withoutTrashed()
                                ->where('member_entry_id', $value)
                                ->where('transaction_type', 3)
                                ->exists()
                        ) {
                            $fail('The member_no is already in use by an active share opening entry record.');
                        }
                    },
                ],

                'share_type' => [
                    'required',
                    'string',
                    'in:electricity',
                ],
                'share_certificate_no' => [
                    'required',
                    'string',
                    'max:20',
                ],
                'share_quantity' => [
                    'required',
                    'integer',
                    'min:1',
                ],
                'share_value' => [
                    'required',
                    'numeric',
                    function ($attribute, $value, $fail) {
                        if ((float) $value !== 100.00) {
                            $fail('The share_value must be exactly 100.00.');
                        }
                    },
                ],
                'amount' => [
                    'required',
                    'numeric',
                    function ($attribute, $value, $fail) use ($request) {
                        $expectedTotal = $request->input('share_quantity') * 100.00;
                        if ((float) $value !== (float) $expectedTotal) {
                            $fail("The amount must be equal to share_quantity * 100.00 (expected: $expectedTotal).");
                        }
                    },
                ],

            ]);
            $fiscalYearID = FiscalYear::whereNull('deleted_at')->where('status', 1);
            $entry = ShareTransaction::create([
                'date_in_bs' => $validated['date_in_bs'],
                'date_in_ad' => $validated['date_in_ad'],
                'member_entry_id' => $validated['member_entry_id'],
                'fiscal_year_id' => $fiscalYearID,
                'share_type' => $validated['share_type'],
                'transaction_type' => 3,
                'share_certificate_no' => $validated['share_certificate_no'],
                'share_quantity' => $validated['share_quantity'],
                'share_value' => number_format($validated['share_value'], 4, '.', ''),
                'amount' => number_format($validated['amount'], 4, '.', ''),
            ]);
            return response()->json([
                'message' => 'Share opening entry created successfully',
                'data' => $entry->toArray()
            ], 201);
        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first(); // Get the first error message
            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'No active meter issue found for the provided member_no.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the share opening entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function editShareOpeningEntry(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('edit share opening entries')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $entry = ShareTransaction::withoutTrashed()
                ->where('transaction_type', 3)
                ->findOrFail($id);



            $validated = $request->validate([
                'date_in_bs' => [
                    'required',
                    'string',
                    'max:10',
                    'regex:/^\d{4}-\d{2}-\d{2}$/',
                    function ($attribute, $value, $fail) {
                        try {
                            $adDate = \App\Helpers\NepaliCalendar::bsToAd($value);
                            $today = date('Y-m-d');
                            if ($adDate > $today) {
                                $fail('The ' . $attribute . ' cannot be a future date.');
                            }
                        } catch (\Exception $e) {
                            $fail('The ' . $attribute . ' is not a valid BS date.');
                        }
                    },
                ],
                'date_in_ad' => [
                    'required',
                    'string',
                    'max:10',
                    'regex:/^\d{4}-\d{2}-\d{2}$/',
                    function ($attribute, $value, $fail) {
                        $today = date('Y-m-d');
                        if ($value > $today) {
                            $fail('The ' . $attribute . ' cannot be a future date.');
                        }
                    },
                ],
                'member_entry_id' => [
                    'required',
                    'integer',
                    'exists:tenant.member_entries,id,deleted_at,NULL,is_active,1',
                    function ($attribute, $value, $fail) use ($entry) {
                        if ($value !== $entry->member_entry_id) {
                            $fail('The member_no cannot be changed once assigned.');
                            return;
                        }
                        if (
                            $value !== $entry->member_no && ShareTransaction::withoutTrashed()
                                ->where('member_entry_id', $value)
                                ->where('transaction_type', 3)
                                ->where('id', '!=', $entry->id)
                                ->exists()
                        ) {
                            $fail('The member_no is already in use by another active share opening entry record.');
                        }
                    },
                ],
                'share_type' => ['required', 'string', 'in:electricity'],
                'share_certificate_no' => ['required', 'string', 'max:20'],
                'share_quantity' => ['required', 'integer', 'min:1'],
                'share_value' => [
                    'required',
                    'numeric',
                    function ($attribute, $value, $fail) {
                        if ((float) $value !== 100.00) {
                            $fail('The share_value must be exactly 100.00.');
                        }
                    },
                ],
                'amount' => [
                    'required',
                    'numeric',
                    function ($attribute, $value, $fail) use ($request) {
                        $expectedTotal = $request->input('share_quantity') * 100.00;
                        if ((float) $value !== (float) $expectedTotal) {
                            $fail("The amount must be equal to share_quantity * 100.00 (expected: $expectedTotal).");
                        }
                    },
                ],
            ]);

            $fiscalYearID = FiscalYear::whereNull('deleted_at')->where('status', 1);


            $updateData = [
                'date_in_bs' => $validated['date_in_bs'],
                'date_in_ad' => $validated['date_in_ad'],
                'member_entry_id' => $validated['member_entry_id'],
                'fiscal_year_id' => $fiscalYearID,
                'share_type' => $validated['share_type'],
                'share_certificate_no' => $validated['share_certificate_no'],
                'share_quantity' => $validated['share_quantity'],
                'share_value' => number_format($validated['share_value'], 4, '.', ''),
                'amount' => number_format($validated['amount'], 4, '.', ''),
            ];

            $entry->update($updateData);
            return response()->json([
                'message' => 'Share opening entry updated successfully',
                'data' => $entry->fresh()
            ], 201);
        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();
            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Share opening entry or member entry not found.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the share opening entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteShareOpeningEntry($id)
    {
       

        try {
            $entry = ShareTransaction::withoutTrashed()
                ->where('transaction_type', 3)
                ->findOrFail($id);

            $hasReturn = ShareTransaction::withoutTrashed()
                ->where('transaction_type', 2)
                ->where('is_cancel', 0)
                ->where('member_entry_id', $entry->member_entry_id)
                ->exists();

           


            if ($hasReturn) {
                return response()->json([
                    'message' => 'Cannot delete: Share Return exists for this customer and share certificate number.'
                ], 400);
            }


            $entry->delete();
            return response()->json([
                'message' => 'Share opening entry deleted successfully',
                'id' => $entry->id,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Share opening entry not found or already deleted',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the share opening entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function importExcel(Request $request)
{
    try {
        $request->validate([
            'file'       => 'required|file|mimes:xlsx,xls,csv',
            'date_in_bs' => 'nullable|string',
        ]);

        $import = new OpeningShareEntryImport($request->date_in_bs);

        Excel::import($import, $request->file('file'));

        return response()->json([
            'message'  => 'Excel imported successfully',
            'imported' => $import->importedCount,
            'failed'   => 0,
        ], 200);

    } catch (ImportValidationException $e) {
        return response()->json([
            'message'     => 'Import validation failed',
            'imported'    => 0,
            'failed'      => count($e->getFailures()),
            'failed_rows' => $e->getFailures(),
        ], 422);

    } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
        $failures = $e->failures();
        $firstError = $failures[0]->errors()[0] ?? 'Excel validation failed';
        return response()->json([
            'message' => $firstError,
            'errors'  => ['excel_validation' => $failures],
        ], 422);

    } catch (ValidationException $e) {
        $allErrors = $e->errors();
        $firstError = collect($allErrors)->flatten()->first();
        return response()->json([
            'message' => $firstError ?: 'Validation failed',
            'errors'  => $allErrors,
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error importing Excel',
            'errors'  => ['exception' => [$e->getMessage()]],
        ], 500);
    }
}


    public function getOpeningShareEntryImportFieldNames()
{
    try {
        $fields = [
            'member_no',
            'share_certificate_no',
            'share_quantity',
            'amount',
        ];

        return response()->json([
            'data' => $fields,
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'status' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}
}
