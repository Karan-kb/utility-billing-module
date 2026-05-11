<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Imports\OpeningMeterDepositEntryImport;
use App\Models\DepositReturn;
use App\Models\MemberEntry;
use App\Models\FiscalYear;
use App\Models\MeterDepositTransaction;
use App\Models\MeterIssue;
use App\Models\OpeningMeterDepositEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Imports\ImportValidationException;


use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;


class OpeningMeterDepositEntryController extends Controller
{
    public function searchCustomerDetails(Request $request)
    {
        try {
            $searchTerm = $request->input('search');

            $customers = MeterIssue::where('is_active', 1)
                ->whereHas('memberEntry', function ($query) use ($searchTerm) {
                    $query->where(function ($q) use ($searchTerm) {
                        $q->where('customer_name_en', 'like', "%{$searchTerm}%")
                            ->orWhere('customer_name_np', 'like', "%{$searchTerm}%")
                            ->orWhere('member_no', 'like', "%{$searchTerm}%");
                    })->where('is_active', 1); 
                })
                ->with(['memberEntry:id,member_no,customer_name_en,customer_name_np'])
                ->select('id', 'member_entry_id', 'meter_no')
                ->get()
                ->map(function ($item) {
                    return [
                        'meter_issue_id' => $item->id,
                        'member_no' => $item->memberEntry->member_no ?? null,
                        'member_entry_id' => $item->memberEntry->id ?? null,
                        'customer_name_en' => $item->memberEntry->customer_name_en ?? null,
                        'customer_name_np' => $item->memberEntry->customer_name_np ?? null,
                        'meter_no' => $item->meter_no,

                    ];
                });

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



    public function getById(Request $request, $id)
    {
        try {
            $entry = MeterDepositTransaction::withoutTrashed()
                ->with([
                    'meterIssue:id,meter_no,member_entry_id',
                    'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np'
                ])
                ->findOrFail($id);

            $meter = $entry->meterIssue;
            $member = $meter?->memberEntry;

            $entry->member_no = $member?->member_no ?? null;
            $entry->customer_name_en = $entry->customer_name_en ?? ($member?->customer_name_en ?? null);
            $entry->customer_name_np = $entry->customer_name_np ?? ($member?->customer_name_np ?? null);
            $entry->meter_no = $meter?->meter_no ?? null;

            unset($entry->meterIssue);

            return response()->json(['open_meter_deposit' => $entry], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Opening Meter Deposit Entry not found or already deleted !!',
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'An error occurred while fetching the Opening Meter Deposit Entry !!',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }



    public function listOpeningMeterDepositEntries(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view opening meter deposit entries')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $query = MeterDepositTransaction::withoutTrashed()
                ->where('transaction_type', 4)
                ->where('is_cancel', 0)
                ->orderBy('created_at', 'desc')
                ->with([
                    'meterIssue:id,meter_no,member_entry_id',
                    'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np'
                ]);

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                        ->orWhere('customer_name_en', 'like', '%' . $search . '%')
                        ->orWhere('customer_name_np', 'like', '%' . $search . '%')
                        ->orWhereHas('memberEntry', function ($sub) use ($search) {
                            $sub->where('member_no', 'like', '%' . $search . '%')
                                ->orWhere('customer_name_en', 'like', '%' . $search . '%')
                                ->orWhere('customer_name_np', 'like', '%' . $search . '%');
                        });
                });
            }

            $entries = $query->paginate(10);

            $entries->getCollection()->transform(function ($entry) {
                $meter = $entry->meterIssue;
                $member = $meter?->memberEntry;

                $entry->member_no = $member?->member_no ?? null;
                $entry->customer_name_en = $entry->customer_name_en ?? ($member?->customer_name_en ?? null);
                $entry->customer_name_np = $entry->customer_name_np ?? ($member?->customer_name_np ?? null);
                $entry->meter_no = $meter?->meter_no ?? null;

                unset($entry->meterIssue);

                return $entry;
            });

            return response()->json(['opening_meter_deposit_entries' => $entries], 200);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'An error occurred while retrieving opening meter deposit entries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createOpeningMeterDepositEntry(Request $request)
    {


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
                    'date',
                    function ($attribute, $value, $fail) {
                        $today = date('Y-m-d');
                        if ($value > $today) {
                            $fail('The ' . $attribute . ' cannot be a future date.');
                        }
                    },
                ],

                'meter_issue_id' => [
                    'required',
                    'integer',
                    function ($attribute, $value, $fail) {
                        $exists = MeterIssue::on('tenant')
                            ->where('id', $value)
                            ->where('is_active', 1)
                          
                            ->exists();

                        if (!$exists) {
                            $fail('The selected meter issue id is invalid or inactive.');
                        }
                    },
                    function ($attribute, $value, $fail) {
                        if (
                            MeterDepositTransaction::on('tenant')->withoutTrashed()
                                ->where('meter_issue_id', $value)
                                ->where('transaction_type', 4)
                                ->exists()
                        ) {
                            $fail('An active opening meter deposit already exists for this meter.');
                        }
                    },
                ],
                'amount' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:999999999999.99',
                    function ($attribute, $value, $fail) {
                        if ($value < 0.00 || $value > 999999999999.99) {
                            $fail('The meter deposit amount must be between 0.00 and 999999999999.99.');
                        }
                    },
                ],

            ]);
            $fiscalYearID = FiscalYear::where('status', 1)
                ->value('id');


            $entry = MeterDepositTransaction::create([
                'date_in_bs' => $validated['date_in_bs'],
                'date_in_ad' => $validated['date_in_ad'],
                'meter_issue_id' => $validated['meter_issue_id'],
                'fiscal_year_id' => $fiscalYearID,

                'transaction_type' => 4,
                'amount' => $validated['amount'],
            ]);


            return response()->json([
                'message' => 'Opening meter deposit entry created successfully',
                'data' => $entry->toArray()
            ], 201);
        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first(); // Get the first error message
            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the opening meter deposit entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    public function editOpeningMeterDepositEntry(Request $request, $id)
    {
        

        try {

            $entry = MeterDepositTransaction::withoutTrashed()->findOrFail($id);

            if ($entry->transaction_type != 4) {
                return response()->json([
                    'message' => 'The specified entry is not an opening meter deposit entry and cannot be edited.',
                ], 400);
            }


            $hasDepositReturn = MeterDepositTransaction::withoutTrashed()
                ->where('meter_issue_id', $entry->meter_issue_id)
                ->where('transaction_type', 2)
                ->exists();

            if ($hasDepositReturn) {
                return response()->json([
                    'message' => 'Cannot edit this opening meter deposit entry because a deposit return exists for the same customer and meter.',
                ], status: 400);
            }


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
                    'date',
                    function ($attribute, $value, $fail) {
                        $today = date('Y-m-d');
                        if ($value > $today) {
                            $fail('The ' . $attribute . ' cannot be a future date.');
                        }
                    },
                ],
                'meter_issue_id' => [
                    'required',
                    'integer',
                    function ($attribute, $value, $fail) {
                        $exists = MeterIssue::on('tenant')
                            ->where('id', $value)
                            ->where('is_active', 1)
                           
                            ->exists();

                        if (!$exists) {
                            $fail('The selected meter is invalid or inactive.');
                        }
                    },
                    function ($attribute, $value, $fail) use ($entry) {
                        if (
                            $value !== $entry->member_entry_id &&
                            MeterDepositTransaction::on('tenant')->withoutTrashed()
                                ->where('meter_issue_id', $value)
                                ->where('id', '!=', $entry->id)
                                ->exists()
                        ) {
                            $fail('An active opening meter deposit already exists for this member.');
                        }
                    },
                ],
                'amount' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:999999999999.99',
                    function ($attribute, $value, $fail) {
                        if ($value < 0.0000 || $value > 999999999999.99) {
                            $fail('The meter deposit amount must be between 0.0000 and 999999999999.99.');
                        }
                    },
                ],

            ]);
            $fiscalYearID = FiscalYear::where('status', 1)
                ->value('id');

            $entry->update([
                'date_in_bs' => $validated['date_in_bs'],
                'date_in_ad' => $validated['date_in_ad'],
                'fiscal_year_id' => $fiscalYearID,
                'meter_issue_id' => $validated['meter_issue_id'],
                'amount' => $validated['amount'],
            ]);


            return response()->json([
                'message' => 'Opening meter deposit entry updated successfully',
                'data' => $entry->toArray(),
            ], 201);

        } catch (ValidationException $e) {
            $errors = $e->errors();
            $first = collect($errors)->flatten()->first();
            return response()->json(['message' => $first, 'errors' => $errors], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the opening meter deposit entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function deleteOpeningMeterDepositEntry(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('delete opening meter deposit entries')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $entry = MeterDepositTransaction::withoutTrashed()->findOrFail($id);

            $hasDepositReturn = MeterDepositTransaction::withoutTrashed()
                ->where('meter_issue_id', $entry->meter_issue_id)
                ->where('transaction_type', 2)
                ->exists();

            if ($hasDepositReturn) {
                return response()->json([
                    'message' => 'Cannot delete this opening meter deposit entry because a deposit return exists for the same customer and meter.',
                ], status: 400);
            }

            $entry->delete();

            return response()->json([
                'message' => 'Opening meter deposit entry deleted successfully',
                'id' => $entry->id,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Opening meter deposit entry not found or already deleted',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the opening meter deposit entry',
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

        $import = new OpeningMeterDepositEntryImport(
            app(\App\Services\MeterIssueResolverService::class),
            $request->date_in_bs
        );

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

    public function getOpeningMeterDepositEntryImportFieldNames()
{
    try {
        $fields = [
            'member_no',
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
