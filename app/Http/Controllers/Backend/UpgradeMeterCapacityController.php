<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\NepaliCalendar;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpgradeMeterCapacity\StoreRequest;

use App\Models\MemberEntry;
use App\Models\MeterIssue;
use App\Models\DepositEntry;
use App\Models\MasterSetup;
use App\Models\MeterDepositTransaction;
use App\Models\Payment;
use App\Models\UpgradeMeterCapacity;
use App\Models\VoucherSummary;
use App\Models\VoucherSummaryDetail;
use App\Services\Accounting\UpgradeMeterCapacityAccountingService;
use App\Services\MemberInfoFromMeterIssueService;
use App\Services\PaymentService;
use App\Services\PaymentValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;

class UpgradeMeterCapacityController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }


    public function createUpgradeMeterCapacity(StoreRequest $request, UpgradeMeterCapacityAccountingService $accounting)
    {
        $connection = (new UpgradeMeterCapacity())->getConnectionName() ?: config('database.default');

        try {
            return DB::connection($connection)->transaction(function () use ($request, $accounting) {

                $validated = $request->validated();
               

                $entry = UpgradeMeterCapacity::create([
                    'date_in_bs' => $validated['date_in_bs'],
                    'date_in_ad' => $validated['date_in_ad'],
                    'meter_issue_id' => $validated['meter_issue_id'],
                    'voucher_no' => $validated['voucher_no'],
                    'charge_amount' => ($validated['charge_amount'] ?? 0),
                    'existing_capacity_id' => $validated['existing_capacity_id'],
                    'upgraded_capacity_id' => $validated['upgraded_capacity_id'],
                    'deposit_amount' => ($validated['deposit_amount'] ?? 0),
                    'amount' => $validated['amount'],
                ]);


                $this->paymentService->createPayments($entry->id, [
                    'cash_amount' => $validated['cash_amount'] ?? 0,
                    'bank_amount' => $validated['bank_amount'] ?? 0,
                    'cheque_no' => $validated['cheque_no'] ?? null,
                    'bank_id' => $validated['bank_id'] ?? null,
                ], 2);

               
                $meterIssue = MeterIssue::where('id', $validated['meter_issue_id'])
                    ->orderByDesc('id')
                    ->first();

                if ($meterIssue) {
                    $meterIssue->update(['capacity_id' => $validated['upgraded_capacity_id']]);
                }

              
                if (!empty($validated['deposit_amount']) && $validated['deposit_amount'] > 0) {
                    $depositEntry = MeterDepositTransaction::withoutTrashed()
                        ->where('meter_issue_id', $validated['meter_issue_id'])
                        ->orderByDesc('id')
                        ->first();

                    $depositAmount = (float) $validated['deposit_amount'];

                    if ($depositEntry) {
                        MeterDepositTransaction::withoutEvents(function () use ($depositEntry, $depositAmount) {
                            $depositEntry->amount += $depositAmount;
                            $depositEntry->upgraded_deposit_amount += $depositAmount;
                            $depositEntry->save();


                            $cashPayment = \App\Models\Payment::withoutTrashed()
                                ->where('reference_id', $depositEntry->id)
                                ->where('type', 2)
                                ->where('payment_mode', 1)
                                ->first();

                            if ($cashPayment) {
                                $cashPayment->amount += $depositAmount;
                                $cashPayment->save();
                            }
                        });
                    } else {
                        $newDepositVoucherNo = $request->generateDepositVoucherNo();

                        $newDepositEntry = MeterDepositTransaction::create([
                            'meter_issue_id' => $validated['meter_issue_id'],
                            'voucher_no' => $newDepositVoucherNo,
                            'amount' => $depositAmount,
                            'upgraded_deposit_amount' => $depositAmount,
                            'date_in_bs' => $validated['date_in_bs'],
                            'date_in_ad' => $validated['date_in_ad'],
                        ]);

                        Payment::create([
                            'reference_id' => $newDepositEntry->id,
                            'type' => 1,
                            'amount' => $depositAmount,
                            'payment_mode' => 1,
                            'cheque_no' => null,
                            'bank_id' => null,
                            'collector_id' => null,
                        ]);


                    }
                }
                $accounting->createVoucher($entry);

                return response()->json([
                    'message' => 'Upgrade Meter Capacity entry created successfully',
                    'id' => $entry->id,
                ], 201);

            }, 5);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the Upgrade Meter Capacity entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function editUpgradeMeterCapacity(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('edit upgrade meter capacity')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $connection = (new UpgradeMeterCapacity())->getConnectionName() ?: config('database.default');

        try {
            $response = DB::connection($connection)->transaction(function () use ($request, $id) {
                $entry = UpgradeMeterCapacity::withoutTrashed()->find($id);

                if (!$entry) {
                    return response()->json([
                        'message' => "Upgrade meter capacity entry not found."
                    ], 404);
                }

                if ($entry->is_cancel == 1) {
                    return response()->json([
                        'message' => 'This Upgrade meter capacity entry has been cancelled. Updates are not allowed.'
                    ], 403);
                }

                $validated = $request->validate([
                    'date_in_bs' => [
                        'required',
                        'string',
                        'max:10',
                        'regex:/^\d{4}-\d{2}-\d{2}$/',
                        function ($attribute, $value, $fail) {
                            try {
                                $adDate = NepaliCalendar::bsToAd($value);
                                if ($adDate > date('Y-m-d')) {
                                    $fail("The {$attribute} cannot be a future date.");
                                }
                            } catch (\Exception $e) {
                                $fail("The {$attribute} is not a valid BS date.");
                            }
                        },
                    ],
                    'date_in_ad' => [
                        'required',
                        'string',
                        'max:10',
                        'regex:/^\d{4}-\d{2}-\d{2}$/',
                        function ($attribute, $value, $fail) {
                            if ($value > date('Y-m-d')) {
                                $fail("The {$attribute} cannot be a future date.");
                            }
                        },
                    ],
                    'meter_issue_id' => [
                        'required',
                        'integer',
                        'exists:tenant.meter_issues,id,deleted_at,NULL,is_active,1',
                        function ($attribute, $value, $fail) use ($entry) {
                            if ($value !== $entry->meter_issue_id) {
                                $fail('The meter_issue_id cannot be changed once assigned.');
                                return;
                            }
                        },
                    ],
                    'existing_capacity_id' => [
                        'required',
                        'integer',
                        'exists:tenant.master_setups,id',
                        function ($attribute, $value, $fail) use ($request) {
                            $meterIssue = MeterIssue::where('id', $request->input('meter_issue_id'))
                                ->orderByDesc('id')
                                ->first();
                            if (!$meterIssue) {
                                $fail('No active meter issue found for the provided customer.');
                                return;
                            }
                            if ($meterIssue->capacity_id != $value) {
                                $fail('The existing_meter_capacity does not match the customer’s latest MeterIssue demand_capacity.');
                            }
                        },
                    ],
                    'upgraded_capacity_id' => [
                        'required',
                        'exists:tenant.master_setups,id',
                        function ($attribute, $value, $fail) {
                            if (
                                !MasterSetup::where('id', $value)
                                    ->where('master_setup_type_id', 4)
                                    ->whereNull('deleted_at')
                                    ->exists()
                            ) {
                                $fail('The selected upgrated meter capacity must have type Capacity, be active, and not deleted.');
                            }
                        },
                    ],
                    'charge_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
                    'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'amount' => ['required', 'numeric', 'min:1', 'max:999999999999.99'],
                    'payment_by_cash' => [
                        'required',
                        'boolean',
                        function ($attr, $val, $fail) use ($request) {
                            PaymentValidationService::validatePaymentMethods($request, $attr, $val, $fail);
                        }
                    ],
                    'payment_by_bank' => ['required', 'boolean'],
                    'cash_amount' => [
                        'nullable',
                        'numeric',
                        'min:0',
                        'max:999999999999.99',
                        function ($attr, $val, $fail) use ($request) {
                            PaymentValidationService::validateCashAmount($request, $val, $fail);
                        }
                    ],
                    'bank_amount' => [
                        'nullable',
                        'numeric',
                        'min:0',
                        'max:999999999999.99',
                        function ($attr, $val, $fail) use ($request) {
                            PaymentValidationService::validateBankAmount($request, $val, $fail);
                        }
                    ],
                    'cheque_no' => ['nullable', 'string', 'max:20', 'required_if:payment_by_bank,1'],
                    'bank_id' => [
                        'nullable',
                        'integer',
                        'required_if:payment_by_bank,1',
                        function ($attr, $val, $fail) use ($request) {
                            PaymentValidationService::validateBank($request, $attr, $val, $fail);
                        }
                    ],
                ]);

                $entry->update([
                    'date_in_bs' => $validated['date_in_bs'],
                    'date_in_ad' => $validated['date_in_ad'],
                    'meter_issue_id' => $validated['meter_issue_id'],
                    'charge_amount' => number_format((float) ($validated['charge_amount'] ?? 0), 4, '.', ''),
                    'existing_capacity_id' => $validated['existing_capacity_id'],
                    'upgraded_capacity_id' => $validated['upgraded_capacity_id'],
                    'deposit_amount' => number_format((float) ($validated['deposit_amount'] ?? 0), 4, '.', ''),
                    'amount' => number_format((float) $validated['amount'], 4, '.', ''),
                ]);
                $this->paymentService->createPayments($entry->id, [
                    'cash_amount' => $validated['cash_amount'] ?? 0,
                    'bank_amount' => $validated['bank_amount'] ?? 0,
                    'cheque_no' => $validated['cheque_no'] ?? null,
                    'bank_id' => $validated['bank_id'] ?? null,
                ], 2);


                $meterIssue = MeterIssue::where('id', $validated['meter_issue_id'])
                    ->orderByDesc('id')
                    ->first();

                if ($meterIssue) {
                    $meterIssue->update([
                        'capacity_id' => $validated['upgraded_capacity_id']
                    ]);
                }

                if (!empty($validated['deposit_amount']) && $validated['deposit_amount'] > 0) {
                    $depositEntry = MeterDepositTransaction::withoutTrashed()
                        ->where('meter_issue_id', $validated['meter_issue_id'])
                        ->orderByDesc('id')
                        ->first();

                    $depositAmount = (float) $validated['deposit_amount'];

                    if ($depositEntry) {
                        MeterDepositTransaction::withoutEvents(function () use ($depositEntry, $depositAmount, $validated) {
                            $depositEntry->amount = $depositAmount;
                            $depositEntry->upgraded_deposit_amount = $depositAmount;
                            $depositEntry->date_in_bs = $validated['date_in_bs'];
                            $depositEntry->date_in_ad = $validated['date_in_ad'];
                            $depositEntry->save();
                        });
                    }
                }
                return response()->json([
                    'message' => 'Upgrade Meter Capacity entry updated successfully',
                    'data' => $entry
                ], 200);
            });

            return $response;

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstError = collect($allErrors)->flatten()->first();
            return response()->json([
                'message' => $firstError,
                'errors' => $allErrors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the Upgrade Meter Capacity entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function searchCustomerDetails(Request $request)
    {
        try {
            $searchTerm = $request->input('search');

            $customers = MeterIssue::where('meter_issues.is_active', 1)
                ->join('member_entries', 'meter_issues.member_entry_id', '=', 'member_entries.id')
                ->where(function ($query) use ($searchTerm) {
                    $query->where('member_entries.id', 'like', "%{$searchTerm}%")
                        ->orWhere('member_entries.customer_name_en', 'like', "%{$searchTerm}%")
                        ->orWhere('member_entries.customer_name_np', 'like', "%{$searchTerm}%");
                })
                ->leftJoin('master_setups as phase', 'meter_issues.phase_id', '=', 'phase.id')
                ->leftJoin('master_setups as capacity', 'meter_issues.capacity_id', '=', 'capacity.id')
                ->leftJoin('master_setups as transformer', 'meter_issues.transformer_id', '=', 'transformer.id')
                ->where('meter_issues.id', '=', function ($query) {
                    $query->select('id')
                        ->from('meter_issues as mi')
                        ->whereColumn('mi.member_entry_id', 'meter_issues.member_entry_id')
                        ->where('mi.is_active', 1)
                        ->whereNull('mi.deleted_at')
                        ->orderBy('mi.id', 'desc')
                        ->limit(1);
                })
                ->select(
                    'meter_issues.id as meter_issue_id',
                    'member_entries.member_no',
                    'member_entries.id as customer_id',
                    'member_entries.customer_name_en',
                    'member_entries.customer_name_np',
                    'meter_issues.meter_no',
                    'meter_issues.capacity_id as demand_capacity_id',
                    'capacity.name_en as demand_capacity_name_en',
                    'meter_issues.phase_id as demand_phase_id',
                    'phase.name_en as demand_phase_name_en',
                    'meter_issues.transformer_id as transformer_id',
                    'transformer.name_en as transformer_name_en',
                    'meter_issues.pole_no',
                    'meter_issues.pole_distance',
                    'meter_issues.issue_meter_capacity'
                )
                ->get();

            if ($customers->isEmpty()) {
                return response()->json([
                    'message' => 'No active customers found for the provided search term.',
                    'data' => []
                ], 200);
            }

            $formatted = $customers->map(function ($item) {
                return [
                    'meter_issue_id' => $item->meter_issue_id, // updated
                    'member_no' => $item->member_no,
                    'customer_name_en' => $item->customer_name_en,
                    'customer_name_np' => $item->customer_name_np,
                    'meter_no' => $item->meter_no,
                    'demand_capacity' => [
                        'id' => $item->demand_capacity_id,
                        'name_en' => $item->demand_capacity_name_en,
                    ],
                    'demand_phase' => [
                        'id' => $item->demand_phase_id,
                        'name_en' => $item->demand_phase_name_en,
                    ],
                    'transformer' => [
                        'id' => $item->transformer_id,
                        'name_en' => $item->transformer_name_en,
                    ],
                    'pole_no' => $item->pole_no,
                    'pole_distance' => $item->pole_distance,
                    'issue_meter_capacity' => $item->issue_meter_capacity,
                ];
            });

            return response()->json([
                'message' => 'Customers retrieved successfully',
                'data' => $formatted
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while searching customers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    public function listUpgradeMeterCapacities(Request $request, MemberInfoFromMeterIssueService $memberInfoService)
    {
        // if (!$request->user()->hasOrganizationPermission('view upgrade meter capacity')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $records = UpgradeMeterCapacity::withoutTrashed()
                ->where('is_cancel', 0)
                ->whereIn('upgrade_meters.id', function ($query) {
                    $query->selectRaw('MAX(um.id)')
                        ->from('upgrade_meters as um')
                        ->join('meter_issues as mi', 'mi.id', '=', 'um.meter_issue_id')
                        ->whereNull('um.deleted_at')
                        ->whereNull('mi.deleted_at')
                        ->where('um.is_cancel', 0)
                        ->groupBy('mi.member_entry_id');
                })
                ->with([
                    'meterIssue:id,meter_no,member_entry_id',
                    'meterIssue.memberEntry:id,customer_name_en,customer_name_np',
                    'existingMeter:id,name_en,name_np',
                    'upgradedMeter:id,name_en,name_np',
                    'payments.bank:id,name,name_np',
                ])
                ->orderBy('created_at', 'desc')
                ->paginate(10);


            $paymentService = app(\App\Services\PaymentService::class);

            $records->getCollection()->transform(function ($record) use ($memberInfoService, $paymentService) {

                $paymentData = $paymentService->transformPayments($record->payments);

                // Fill missing info from MeterIssue using service
                if (!$record->customer_name_en || !$record->meter_no || !$record->member_no) {
                    $memberInfo = $memberInfoService->getMemberInfo($record->meter_issue_id);
                    $record->customer_name_en = $record->memberEntry?->customer_name_en ?? $memberInfo['customer_name_en'];
                    $record->customer_name_np = $record->memberEntry?->customer_name_np ?? $memberInfo['customer_name_np'];
                    $record->meter_no = $record->meter_no ?? $memberInfo['meter_no'];
                    $record->member_no = $record->member_no ?? $memberInfo['member_no'];
                }

                return [
                    'id' => $record->id,
                    'voucher_no' => $record->voucher_no,
                    'date_in_bs' => $record->date_in_bs,
                    'date_in_ad' => $record->date_in_ad,
                    'charge_amount' => $record->charge_amount,
                    'deposit_amount' => $record->deposit_amount,
                    'amount' => $record->amount,
                    'is_cancel' => $record->is_cancel,

                    'payment_by_cash' => $paymentData['payment_by_cash'],
                    'cash_amount' => $paymentData['cash_amount'],
                    'payment_by_bank' => $paymentData['payment_by_bank'],
                    'bank_amount' => $paymentData['bank_amount'],
                    'cheque_no' => $paymentData['cheque_no'],
                    'bank_id' => $paymentData['bank_id'],
                    'bank_name_en' => $paymentData['bank_name_en'],
                    'bank_name_np' => $paymentData['bank_name_np'],

                    'existing_meter_name_en' => $record->existingMeter?->name_en,
                    'existing_meter_name_np' => $record->existingMeter?->name_np,
                    'upgraded_meter_name_en' => $record->upgradedMeter?->name_en,
                    'upgraded_meter_name_np' => $record->upgradedMeter?->name_np,

                    'member_no' => $record->member_no,
                    'customer_name_en' => $record->customer_name_en,
                    'customer_name_np' => $record->customer_name_np,
                    'meter_no' => $record->meter_no,
                ];
            });

            return response()->json(['upgrade_meter_capacities' => $records], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing upgrade meter capacities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function cancelUpgrade($id)
    {
        try {

            $transaction = UpgradeMeterCapacity::whereNull('deleted_at')
                ->findOrFail($id);

            $meterTransaction = MeterDepositTransaction::withoutTrashed()
                ->where('meter_issue_id', $transaction->meter_issue_id)
                ->latest('id')
                ->first();
            if (!$meterTransaction) {
                return response()->json([
                    'message' => 'No meter deposit transaction found for this upgrade meter capacity.',
                ], 404);
            }

            $transaction->update(['is_cancel' => 1]);
            $meterTransaction->update(['is_cancel' => 1]);

            return response()->json([
                'message' => 'Transaction cancelled successfully',
                'data' => $transaction,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Transaction not found.',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database query error occurred while cancelling the transaction',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while cancelling the transaction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





    public function getUpgradeMeterCapacityById(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('view upgrade meter capacity')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $record = UpgradeMeterCapacity::withoutTrashed()
                ->with([
                    'memberEntry:id,customer_name_en,customer_name_np',
                    'existingMeter:id,name_en,name_np',
                    'upgradedMeter:id,name_en,name_np',
                    'payments.bank:id,name,name_np',
                    'meterIssue:id,meter_no,member_entry_id,meter_start_no,construct_company,reading_seal_no,terminal_seal_no,meter_box_seal_no,pole_no,pole_distance'
                ])
                ->findOrFail($id);

            $paymentData = app(\App\Services\PaymentService::class)->transformPayments($record->payments);

            // Fill missing member/meter info using service if needed
            if (!$record->customer_name_en || !$record->meter_no || !$record->member_no) {
                $memberInfoService = app(\App\Services\MemberInfoFromMeterIssueService::class);
                $memberInfo = $memberInfoService->getMemberInfo($record->meter_issue_id);

                $record->customer_name_en = $record->memberEntry?->customer_name_en ?? $memberInfo['customer_name_en'];
                $record->customer_name_np = $record->memberEntry?->customer_name_np ?? $memberInfo['customer_name_np'];
                $record->meter_no = $record->meter_no ?? $memberInfo['meter_no'];
                $record->member_no = $record->member_no ?? $memberInfo['member_no'];
            }

            $response = [
                'upgrade_meter_capacity' => [
                    'id' => $record->id,
                    'voucher_no' => $record->voucher_no,
                    'date_in_bs' => $record->date_in_bs,
                    'date_in_ad' => $record->date_in_ad,
                    'charge_amount' => $record->charge_amount,
                    'deposit_amount' => $record->deposit_amount,
                    'amount' => $record->amount,
                    'is_cancel' => $record->is_cancel,

                    // From PaymentService
                    'payment_by_cash' => $paymentData['payment_by_cash'],
                    'cash_amount' => $paymentData['cash_amount'],
                    'payment_by_bank' => $paymentData['payment_by_bank'],
                    'bank_amount' => $paymentData['bank_amount'],
                    'cheque_no' => $paymentData['cheque_no'],
                    'bank_id' => $paymentData['bank_id'],
                    'bank_name_en' => $paymentData['bank_name_en'],
                    'bank_name_np' => $paymentData['bank_name_np'],

                    // Member & meter info
                    'member_no' => $record->member_no,
                    'meter_no' => $record->meter_no,
                    'customer_name_en' => $record->customer_name_en,
                    'customer_name_np' => $record->customer_name_np,

                    // Meter Issue additional fields
                    'meter_start_no' => $record->meterIssue?->meter_start_no,
                    'construct_company' => $record->meterIssue?->construct_company,
                    'reading_seal_no' => $record->meterIssue?->reading_seal_no,
                    'terminal_seal_no' => $record->meterIssue?->terminal_seal_no,
                    'meter_box_seal_no' => $record->meterIssue?->meter_box_seal_no,
                    'pole_no' => $record->meterIssue?->pole_no,
                    'pole_distance' => $record->meterIssue?->pole_distance,

                    'existing_meter_name_en' => $record->existingMeter?->name_en,
                    'existing_meter_name_np' => $record->existingMeter?->name_np,
                    'upgraded_meter_name_en' => $record->upgradedMeter?->name_en,
                    'upgraded_meter_name_np' => $record->upgradedMeter?->name_np,
                ]
            ];

            return response()->json($response, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Upgrade meter capacity record not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving the record',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // public function deleteUpgradeMeterCapacity(Request $request, $id)
    // {
    //     // if (!$request->user()->hasOrganizationPermission('delete upgrade meter capacity')) {
    //     //     return response()->json(['message' => 'Unauthorized'], 403);
    //     // }

    //     $connection = (new UpgradeMeterCapacity)->getConnectionName() ?: config('database.default');

    //     try {
    //         $response = DB::connection($connection)->transaction(function () use ($id, $connection) {

    //             $entry = UpgradeMeterCapacity::on($connection)
    //                 ->withoutTrashed()
    //                 ->where('cancel_status', 0)
    //                 ->findOrFail($id);

    //             $this->revertMeterCapacityOnUpgradeCancel($entry);

              

    //             $entry->delete();
    //             return response()->json([
    //                 'message' => 'Upgrade meter capacity entry deleted successfully with reversion applied',
    //                 'id' => $id,
    //             ], 200);
    //         });

    //         return $response;

    //     } catch (ModelNotFoundException $e) {
    //         return response()->json([
    //             'message' => 'Upgrade meter capacity entry not found',
    //         ], 404);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'An error occurred while deleting the upgrade meter capacity entry',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    // private function revertMeterCapacityOnUpgradeCancel($upgradeEntry)
    // {
    //     try {
    //         $latestUpgrade = UpgradeMeterCapacity::on('tenant')
    //             ->where('member_entry_id', $upgradeEntry->member_entry_id)
    //             ->whereNull('deleted_at')
    //             ->where('cancel_status', 0)
    //             ->latest('id')
    //             ->first();

    //         if (!$latestUpgrade || $latestUpgrade->id == $upgradeEntry->id) {

    //             $meterIssue = MeterIssue::on('tenant')
                    
    //                 ->where('member_entry_id', $upgradeEntry->customer_id)
    //                 ->orderByDesc('id')
    //                 ->first();

    //             if ($meterIssue) {
    //                 $meterIssue->update([
    //                     'demand_capacity' => $upgradeEntry->existing_meter_capacity,
    //                 ]);

    //             }

    //             $voucherSummary = VoucherSummary::on('tenant')
    //                 ->where('voucher_no', $upgradeEntry->voucher_no)
    //                 ->first();

    //             $debitAmount = VoucherSummaryDetail::on('tenant')
    //                 ->where('voucher_summary_id', optional($voucherSummary)->id)
    //                 ->sum('debit');

    //             if ($debitAmount > 0) {

    //                 $latestDeposit = DepositEntry::on('tenant')
    //                     ->where('member_entry_id', $upgradeEntry->customer_id)
    //                     ->whereNull('deleted_at')
    //                     ->where('cancel_status', 0)
    //                     ->latest('id')
    //                     ->first();

    //                 if ($latestDeposit) {

    //                     $newDepositAmount = max(0, $latestDeposit->meter_deposit_amount - $debitAmount);
    //                     $newUpgradedDeposit = max(0, $latestDeposit->upgraded_deposit_amount - $debitAmount);

    //                     // Recalculate cash/bank split
    //                     $newCash = $latestDeposit->cash_amount;
    //                     $newBank = $latestDeposit->bank_amount;

    //                     if ($latestDeposit->payment_by_cash && !$latestDeposit->payment_by_bank) {
    //                         $newCash = $newDepositAmount;
    //                         $newBank = 0;

    //                     } elseif ($latestDeposit->payment_by_bank && !$latestDeposit->payment_by_cash) {
    //                         $newBank = $newDepositAmount;
    //                         $newCash = 0;

    //                     } elseif ($latestDeposit->payment_by_bank && $latestDeposit->payment_by_cash) {
    //                         $ratioCash = $latestDeposit->cash_amount / max(1, $latestDeposit->meter_deposit_amount);
    //                         $ratioBank = $latestDeposit->bank_amount / max(1, $latestDeposit->meter_deposit_amount);

    //                         $newCash = round($newDepositAmount * $ratioCash, 4);
    //                         $newBank = round($newDepositAmount * $ratioBank, 4);
    //                     }

    //                     // Update deposit
    //                     $latestDeposit->update([
    //                         'meter_deposit_amount' => $newDepositAmount,
    //                         'upgraded_deposit_amount' => $newUpgradedDeposit,
    //                         'cash_amount' => $newCash,
    //                         'bank_amount' => $newBank,
    //                     ]);

    //                 }
    //             }

    //         } else {
    //             Log::info("Cancelled UpgradeMeterCapacity is not latest — no reversion applied.");
    //         }

    //     } catch (\Exception $e) {
    //         Log::error("Error reverting upgrade meter capacity: " . $e->getMessage());
    //     }
    // }

}
