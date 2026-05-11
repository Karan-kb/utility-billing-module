<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\Helper;
use App\Helpers\NepaliCalendar;
use App\Http\Controllers\Controller;
use App\Http\Requests\MeterInsurance\StoreRequest;
use App\Http\Requests\MeterInsurance\UpdateRequest;

use App\Models\MasterSetup;
use App\Models\MemberEntry;
use App\Models\MeterInsurance;
use App\Models\MeterIssue;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\PaymentValidationService;
use App\Services\VoucherValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\Accounting\MeterInsuranceAccountingService;
use App\Services\VoucherBalanceService;
use App\Services\VoucherEntryService;

class MeterInsuranceController extends Controller
{

    public function __construct(protected PaymentService $paymentService,protected VoucherEntryService $voucherService)
    {
        $this->paymentService = $paymentService;
    }


    public function store(StoreRequest $request)
    {
        $validated = $request->validated();

        try {
            $entry = DB::connection('tenant')->transaction(function () use ($validated) {

                $entry = MeterInsurance::create([
                    'date_in_bs' => $validated['date_in_bs'],
                    'date_in_ad' => $validated['date_in_ad'],
                    'meter_issue_id' => $validated['meter_issue_id'],
                    'voucher_no' => $validated['voucher_no'],
                    'amount' => round((float) $validated['amount'], 2),
                    'payment_by_cash' => $validated['payment_by_cash'],
                    'payment_by_bank' => $validated['payment_by_bank'],
                    'cash_amount' => round((float) ($validated['cash_amount'] ?? 0), 2),
                    'bank_amount' => round((float) ($validated['bank_amount'] ?? 0), 2),
                    'cheque_no' => $validated['cheque_no'] ?? null,
                    'bank_id' => $validated['bank_id'] ?? null,
                    'is_active' => 1,
                ]);

                $this->paymentService->createPayments(
                    $entry->id,
                    [
                        'cash_amount' => $validated['cash_amount'] ?? 0,
                        'bank_amount' => $validated['bank_amount'] ?? 0,
                        'cheque_no' => $validated['cheque_no'] ?? null,
                        'bank_id' => $validated['bank_id'] ?? null,
                    ],
                    5
                );

                return $entry;
            });          
            

            if ($validated['payment_by_cash'] && $validated['cash_amount'] > 0) {
                $lines[] = [
                    'account_head_id' => 1,
                    'debit' => $validated['cash_amount'],
                    'particulars' => "Cash Received (Meter Insurance {$entry->voucher_no})",
                ];
            }
            

            if ($validated['payment_by_bank'] && $validated['bank_amount'] > 0) {
                $lines[] = [
                    'account_head_id' => $validated['bank_id'], 
                    'debit' => $validated['bank_amount'],
                    'particulars' => "Bank Received (Meter Insurance {$entry->voucher_no})",
                ];
            }
            $lines[] = [
                'account_head_id' => 19,
                'particulars' => "Meter Insurance ({$entry->voucher_no})",
                'credit' => $entry->amount,
            ];
            VoucherBalanceService::validate($lines);

            $voucher = $this->voucherService->create([
                'date' => $entry->date_in_ad,
                'fiscal_year_id' => Helper::getActiveFiscalYearId(),
                'voucher_no' => $entry->voucher_no,
                'particulars' => "Meter Insurance {$entry->voucher_no}",
                'status' => 2,
                'reference_type' => 5,//meter insurance
                'reference_id' => $entry->id,
                'member_entry_id' => MeterIssue::getMemberByMeterIssueId($validated['meter_issue_id'])->id ?? null,
                'lines' => $lines,
            ]);

            return response()->json([
                'message' => 'Meter insurance entry created successfully',
                'data' => $entry->toArray()
            ], 201);

        } catch (\Throwable $e) {
             if (str_contains($e->getMessage(), 'Voucher entry not balanced')) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }

            return response()->json([
                'message' => 'An error occurred while creating the meter insurance entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function searchCustomerDetails(Request $request)
    {
        try {
            $searchTerm = $request->input('search');
            $fiscalYear = $request->input('fiscal_year');

            $customers = MeterIssue::with('memberEntry')
                ->where('is_active', 1)
                ->when($searchTerm, function ($query, $searchTerm) {
                    $query->where('member_entry_id', 'like', "%{$searchTerm}%")
                        ->orWhereHas('memberEntry', function ($q) use ($searchTerm) {
                            $q->where('customer_name_en', 'like', "%{$searchTerm}%")
                                ->orWhere('customer_name_np', 'like', "%{$searchTerm}%");
                        });
                })
                ->whereNotIn('member_entry_id', function ($subQuery) use ($fiscalYear) {
                    $subQuery->select('member_entry_id')
                        ->from((new MeterInsurance)->getTable())
                        ->whereNull('deleted_at')
                        ->where('is_active', 1);


                })
                ->get();

            if ($customers->isEmpty()) {
                return response()->json([
                    'message' => 'No active, non-deleted records found for the provided search term.',
                    'data' => [],
                ], 200);
            }

            $result = $customers->map(function ($customer) {
                $member = $customer->memberEntry;

                return [
                    'meter_issue_id' => $customer->id,  // changed from member_entry_id
                    'customer_name_en' => $member->customer_name_en ?? null,
                    'customer_name_np' => $member->customer_name_np ?? null,
                    'member_no' => $member->member_no ?? null,
                    'meter_no' => $customer->meter_no,
                    'demand_capacity' => $customer->demandCapacity?->name_en ?? null,
                ];
            });

            return response()->json([
                'message' => 'Customer details retrieved successfully',
                'data' => $result,
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

            $entry = MeterInsurance::withoutTrashed()
                ->with([
                    'meterIssue:id,meter_no,member_entry_id,capacity_id',
                    'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np',
                    'meterIssue.demandCapacity:id,name_en',
                    'payments.bank:id,name,name_np',
                ])
                ->findOrFail($id);

            $paymentData = app(PaymentService::class)->transformPayments($entry->payments);

            $response = [
                'meter_insurance_entry' => [
                    'id' => $entry->id,
                    'voucher_no' => $entry->voucher_no,
                    'date_in_bs' => $entry->date_in_bs,
                    'date_in_ad' => $entry->date_in_ad,
                    'member_no' => $entry->meterIssue?->memberEntry?->member_no,
                    'meter_no' => $entry->meterIssue?->meter_no,
                    'amount' => $entry->amount,
                    'is_cancel' => $entry->is_cancel,
                    'deleted_at' => $entry->deleted_at,
                    'created_at' => $entry->created_at,
                    'updated_at' => $entry->updated_at,

                    // ✅ From PaymentService
                    'payment_by_cash' => $paymentData['payment_by_cash'],
                    'cash_amount' => $paymentData['cash_amount'],
                    'payment_by_bank' => $paymentData['payment_by_bank'],
                    'bank_amount' => $paymentData['bank_amount'],
                    'cheque_no' => $paymentData['cheque_no'],
                    'bank_id' => $paymentData['bank_id'],
                    'bank_name_en' => $paymentData['bank_name_en'],
                    'bank_name_np' => $paymentData['bank_name_np'],

                    // Member & meter info
                    'customer_name_en' => $entry->meterIssue?->memberEntry?->customer_name_en,
                    'customer_name_np' => $entry->meterIssue?->memberEntry?->customer_name_np,
                    'demand_capacity' => $entry->meterIssue?->demandCapacity?->name_en,
                ]
            ];

            return response()->json($response, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Meter Insurance entry not found or already deleted',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the Meter Insurance entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function index(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view meter insurance')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $search = $request->query('search');
            $fromDate = $request->query('from_date');
            $toDate = $request->query('to_date');
            $fromDateBs = $request->query('from_date_bs');
            $toDateBs = $request->query('to_date_bs');

            $recordsQuery = MeterInsurance::withoutTrashed()
                ->orderBy('created_at', 'desc')
                ->with([
                    'meterIssue:id,member_entry_id,meter_no,capacity_id',
                    'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np',
                    'payments.bank:id,name,name_np'
                ]);

            if (!empty($search)) {
                $recordsQuery->whereHas('meterIssue.memberEntry', function ($query) use ($search) {
                    $query->where('member_no', 'like', "%{$search}%")
                        ->orWhere('customer_name_en', 'like', "%{$search}%")
                        ->orWhere('customer_name_np', 'like', "%{$search}%");
                });
            }

            // Filter by AD dates
            if (!empty($fromDate) && !empty($toDate)) {
                $recordsQuery->whereBetween('date_in_ad', [$fromDate, $toDate]);
            } elseif (!empty($fromDate)) {
                $recordsQuery->whereDate('date_in_ad', '>=', $fromDate);
            } elseif (!empty($toDate)) {
                $recordsQuery->whereDate('date_in_ad', '<=', $toDate);
            }

            // Filter by BS dates
            if (!empty($fromDateBs) && !empty($toDateBs)) {
                $recordsQuery->whereBetween('date_in_bs', [$fromDateBs, $toDateBs]);
            } elseif (!empty($fromDateBs)) {
                $recordsQuery->whereDate('date_in_bs', '>=', $fromDateBs);
            } elseif (!empty($toDateBs)) {
                $recordsQuery->whereDate('date_in_bs', '<=', $toDateBs);
            }

            $records = $recordsQuery->paginate(10);

            $paymentService = app(PaymentService::class);

            $records->getCollection()->transform(function ($record) use ($paymentService) {
                $paymentData = $paymentService->transformPayments($record->payments);

                return [
                    'id' => $record->id,
                    'is_cancel' => $record->is_cancel,
                    'fiscal_year_id' => $record->fiscal_year_id,
                    'date_in_bs' => $record->date_in_bs,
                    'date_in_ad' => $record->date_in_ad,
                    'meter_issue_id' => $record->meter_issue_id,
                    'voucher_no' => $record->voucher_no,
                    'amount' => $record->amount,
                    'created_at' => $record->created_at,
                    'updated_at' => $record->updated_at,
                    'member_no' => $record->meterIssue->memberEntry->member_no ?? null,
                    'customer_name_en' => $record->meterIssue->memberEntry->customer_name_en ?? null,
                    'customer_name_np' => $record->meterIssue->memberEntry->customer_name_np ?? null,
                    'meter_no' => $record->meterIssue->meter_no ?? null,
                    'demand_capacity' => $record->meterIssue->demandCapacity?->name_en ?? null,

                    // Payment info via PaymentService
                    'payment_by_cash' => $paymentData['payment_by_cash'],
                    'cash_amount' => $paymentData['cash_amount'],
                    'payment_by_bank' => $paymentData['payment_by_bank'],
                    'bank_amount' => $paymentData['bank_amount'],
                    'cheque_no' => $paymentData['cheque_no'],
                    'bank_id' => $paymentData['bank_id'],
                    'bank_name_en' => $paymentData['bank_name_en'],
                    'bank_name_np' => $paymentData['bank_name_np'],
                ];
            });

            return response()->json(['meter_insurances' => $records], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing meter insurance entries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




}
