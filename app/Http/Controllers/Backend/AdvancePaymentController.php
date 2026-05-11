<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdvancePayment\StoreRequest;
use App\Models\AdvancePayment;
use App\Models\CustomerTransaction;
use App\Helpers\Helper;
use App\Models\FiscalYear;
use App\Models\MeterIssue;
use App\Models\MeterReadingEntry;
use App\Services\PaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Requests\AdvancePayment\UpdateRequest;
use App\Services\Accounting\AdvancePaymentAccountingService;
use App\Services\VoucherEntryService;
use App\Services\CustomerDueService;
use App\Services\VoucherBalanceService;

class AdvancePaymentController extends Controller
{
    protected PaymentService $paymentService;
protected $dueService;

    public function __construct(PaymentService $paymentService, protected VoucherEntryService $voucherService, CustomerDueService $dueService)
    {
        $this->paymentService = $paymentService;
        $this->dueService = $dueService;
    }

    public function create(StoreRequest $request)
    {
        $validated = $request->validated();
        $connection = (new AdvancePayment())->getConnectionName() ?: config('database.default');

        $meterIssueID = $validated['meter_issue_id'];

        $meterIssueId = MeterIssue::findOrFail($meterIssueID);

        $memberEntryID = $meterIssueId->member_entry_id;


       $previousDue = $this->dueService->getPreviousTotalDue(
            $memberEntryID,
            $meterIssueID
        );

        if ($previousDue > 0) {
            return response()->json([
                'message' => 'Cannot create Advance Payment. This member has previous due amount of Rs. ' . number_format($previousDue, 2),
                'previous_due' => $previousDue
            ], 400);
        }

        try {
            return DB::connection($connection)->transaction(function () use ($validated, $meterIssueID, $memberEntryID) {


                $entry = AdvancePayment::create([
                    'date_in_bs' => $validated['date_in_bs'],
                    'date_in_ad' => $validated['date_in_ad'],
                    'meter_issue_id' => $meterIssueID,

                    'voucher_no' => $validated['voucher_no'],
                    'amount' => round($validated['amount'] ?? 0, 2),
                    'payment_by_cash' => $validated['payment_by_cash'],
                    'payment_by_bank' => $validated['payment_by_bank'],
                    'cash_amount' => round($validated['cash_amount'] ?? 0, 2),
                    'bank_amount' => round($validated['bank_amount'] ?? 0, 2),
                    'cheque_no' => $validated['cheque_no'] ?? null,
                    'bank_id' => $validated['bank_id'] ?? null,
                ]);


                $advanceTransactions = CustomerTransaction::create([
                    'transaction_date' => $validated['date_in_ad'],
                    'member_entry_id' => $memberEntryID,
                    'transaction_type' => 6,
                    'charge_type' => 9,
                    'due' => NULL,
                    'amount' => round($validated['amount'] ?? 0, 2),
                    'direction' => 'CR'
                ]);

                $meterIssueId = MeterIssue::where('member_entry_id', $memberEntryID)->first();

                $this->paymentService->createPayments(
                    $entry->id,
                    [
                        'cash_amount' => $validated['cash_amount'] ?? 0,
                        'bank_amount' => $validated['bank_amount'] ?? 0,
                        'cheque_no' => $validated['cheque_no'] ?? null,
                        'bank_id' => $validated['bank_id'] ?? null,
                    ],
                    4
                );


                $lines[] = [
                    'account_head_id' => 9,
                    'particulars' => "Advance Payment ({$entry->voucher_no})",
                    'credit' => $validated['amount'],
                ];


                if ($validated['payment_by_cash'] && $validated['cash_amount'] > 0) {
                    $lines[] = [
                        'account_head_id' => 1,
                        'debit' => $validated['cash_amount'],
                        'particulars' => "Cash Received (Advance Payment {$entry->voucher_no})",
                    ];
                }


                if ($validated['payment_by_bank'] && $validated['bank_amount'] > 0) {
                    $lines[] = [
                      'account_head_id' => $validated['bank_id'], 
                        'debit' => $validated['bank_amount'],
                        'particulars' => "Bank Received (Advance Payment {$entry->voucher_no})",
                    ];
                }

                $fiscalYearId = Helper::getActiveFiscalYearId();
               VoucherBalanceService::validate($lines);

                $voucher = $this->voucherService->create([
                    'date' => $entry->date_in_ad,
                    'particulars' => "Advance Payment {$entry->voucher_no})",
                    'fiscal_year_id' => $fiscalYearId,
                    'voucher_no' => $entry->voucher_no,
                    'status' => 2,
                    'reference_type' => 4,
                    'reference_id' => $entry->id,
                    'member_entry_id' => MeterIssue::getMemberByMeterIssueId($validated['meter_issue_id'])->id ?? null,
                    'lines' => $lines,
                ]);




                return response()->json([
                    'message' => 'Advance Payment entry created successfully',
                    'id' => 1,
                ], 201);

            });

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Related model not found',
                'error' => $e->getMessage(),
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database query error occurred while creating the Advance Payment entry',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
             if (str_contains($e->getMessage(), 'Voucher entry not balanced')) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
            return response()->json([
                'message' => 'An error occurred while creating the Advance Payment entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





    public function searchCustomerDetails(Request $request)
    {
        try {
            $searchTerm = $request->input('search');

            $customers = MeterIssue::join('member_entries', 'member_entries.id', '=', 'meter_issues.member_entry_id')
                ->where('meter_issues.is_active', 1)
                ->where(function ($query) use ($searchTerm) {
                    $query->where('member_entries.member_no', 'like', "%{$searchTerm}%")
                        ->orWhere('member_entries.customer_name_en', 'like', "%{$searchTerm}%")
                        ->orWhere('member_entries.customer_name_np', 'like', "%{$searchTerm}%");
                })
                ->select(
                    'meter_issues.id as meter_issue_id',
                    'member_entries.member_no',
                    'member_entries.customer_name_en',
                    'member_entries.customer_name_np',
                    'meter_issues.meter_no'                )
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


    public function getById(Request $request, $id)
    {
        try {

            $entry = AdvancePayment::withoutTrashed()
                ->where('type', 0)
                ->with([
                    'meterIssue:id,meter_no,member_entry_id',
                    'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np',
                    'payments.bank:id,name,name_np',
                ])
                ->findOrFail($id);

            $paymentData = app(PaymentService::class)->transformPayments($entry->payments);

            $response = [
                'advance_payment_entry' => [
                    'id' => $entry->id,
                    'voucher_no' => $entry->voucher_no,
                    'date_in_bs' => $entry->date_in_bs,
                    'date_in_ad' => $entry->date_in_ad,
                    'amount' => $entry->amount,
                    'is_cancel' => $entry['is_cancel'],


                    'payment_by_cash' => $paymentData['payment_by_cash'],
                    'cash_amount' => $paymentData['cash_amount'],
                    'payment_by_bank' => $paymentData['payment_by_bank'],
                    'bank_amount' => $paymentData['bank_amount'],
                    'cheque_no' => $paymentData['cheque_no'],
                    'bank_id' => $paymentData['bank_id'],
                    'bank_name_en' => $paymentData['bank_name_en'],
                    'bank_name_np' => $paymentData['bank_name_np'],

                    'member_no' => $entry->meterissue?->memberEntry?->member_no,
                    'meter_no' => $entry->meterIssue?->meter_no,
                    'customer_name_en' => $entry->meterIssue?->memberEntry?->customer_name_en,
                    'customer_name_np' => $entry->meterIssue?->memberEntry?->customer_name_np,
                ]
            ];

            return response()->json($response, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Advance payment entry not found or already deleted',
            ], 404);

        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database query error occurred while fetching the advance payment entry',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the advance payment entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function list(Request $request)
    {


        try {

            $records = AdvancePayment::withoutTrashed()
               ->where('type', 0)
                ->orderBy('created_at', 'desc')
                ->with([
                    'meterIssue:id,meter_no,member_entry_id',
                    'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np',
                    'payments.bank:id,name,name_np',
                ])
                ->paginate(10);

            $paymentService = app(PaymentService::class);

            $records->getCollection()->transform(function ($record) use ($paymentService) {

                $paymentData = $paymentService->transformPayments($record->payments);

                return [
                    'id' => $record->id,
                    'voucher_no' => $record->voucher_no,
                    'date_in_bs' => $record->date_in_bs,
                    'date_in_ad' => $record->date_in_ad,
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

                    // Member & meter info
                    'member_no' => $record->meterIssue?->memberEntry?->member_no,
                    'customer_name_en' => $record->meterIssue?->memberEntry?->customer_name_en,
                    'customer_name_np' => $record->meterIssue?->memberEntry?->customer_name_np,
                    'meter_no' => $record->meterIssue?->meter_no,
                ];
            });

            return response()->json(['advance_payments' => $records], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing advance payment entries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




}
