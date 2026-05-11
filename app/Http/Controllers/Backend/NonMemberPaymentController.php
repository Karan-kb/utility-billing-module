<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\NepaliCalendar;
use App\Http\Controllers\Controller;
use App\Http\Requests\NonMemberPayment\StoreRequest;
use App\Http\Requests\EditValidationRequest;
use App\Models\MasterSetup;
use App\Models\MeterIssue;
use App\Models\NonMemberPayment;
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
use App\Services\Accounting\NonMemberPaymentAccountingService;
use App\Services\VoucherEntryService;
use App\Helpers\Helper;
use App\Services\VoucherBalanceService;

class NonMemberPaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService,protected VoucherEntryService $voucherService)
    {
        $this->paymentService = $paymentService;
    }



    public function create(StoreRequest $request)
    {
        //$request->setModelClass(NonMemberPayment::class, VoucherValidationService::validate('NM', NonMemberPayment::class));

        // if (!$request->user()->hasOrganizationPermission('create non member payment')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $connection = (new NonMemberPayment())->getConnectionName() ?: config('database.default');

        try {
            return DB::connection($connection)->transaction(function () use ($request) {
                $validated = $request->validated();
                // $modelClass = $request->getModelClass();

                $entry = NonMemberPayment::create([
                    'date_in_bs' => $validated['date_in_bs'],
                    'date_in_ad' => $validated['date_in_ad'],
                    'customer_name_en' => $validated['customer_name_en'],
                    'customer_name_np' => $validated['customer_name_np'],
                    'address' => $validated['address'],
                    'mobile_no' => $validated['mobile_no'],
                    'demand_charge' => $validated['demand_charge'],
                    'consumption_unit' => $validated['consumption_unit'],
                    'voucher_no' => $validated['voucher_no'],
                    'amount' => round((float) $validated['amount'], 2),
                    'payment_by_cash' => $validated['payment_by_cash'],
                    'payment_by_bank' => $validated['payment_by_bank'],
                    'cash_amount' => round((float) ($validated['cash_amount'] ?? 0), 2),
                    'bank_amount' => round((float) ($validated['bank_amount'] ?? 0), 2),
                    'cheque_no' => $validated['cheque_no'] ?? null,
                    'bank_id' => $validated['bank_id'] ?? null,
                ]);

                $this->paymentService->createPayments($entry->id, [
                    'cash_amount' => $validated['cash_amount'] ?? 0,
                    'bank_amount' => $validated['bank_amount'] ?? 0,
                    'cheque_no' => $validated['cheque_no'] ?? null,
                    'bank_id' => $validated['bank_id'] ?? null,
                ], 9); // type = 9 for non_member_payment
                // app(NonMemberPaymentAccountingService::class)
                // ->createVoucher($entry);
                $amount = bcsub( $validated['amount'], $validated['demand_charge'], 2);

                $lines[] = [
                    'account_head_id' => 12,
                    'particulars' => "Non Member Payment ({$entry->voucher_no})",
                    'credit' => $amount,
                ];
                if ($validated['demand_charge'] > 0) {
                    $lines[] = [
                        'account_head_id' => 14,
                        'particulars' => "Demand Charge ({$entry->voucher_no})",
                        'credit' => $validated['demand_charge'],
                    ];
                }
                
    
                if ($validated['payment_by_cash'] && $validated['cash_amount'] > 0) {
                    $lines[] = [
                        'account_head_id' => 1,
                        'debit' => $validated['cash_amount'],
                        'particulars' => "Cash Received ({$entry->voucher_no})",
                    ];
                }
                
    
                if ($validated['payment_by_bank'] && $validated['bank_amount'] > 0) {
                    $lines[] = [
                       'account_head_id' => $validated['bank_id'], 
                        'debit' => $validated['bank_amount'],
                        'particulars' => "Bank Received ({$entry->voucher_no})",
                    ];
                }

                
                VoucherBalanceService::validate($lines);
                $voucher = $this->voucherService->create([
                    'date' => $entry->date_in_ad,
                    'fiscal_year_id' => Helper::getActiveFiscalYearId(),
                    'voucher_no' => $entry->voucher_no,
                    'particulars' => "Non Member Payment {$entry->voucher_no}",
                    'status' => 2,
                    'reference_type' => 9,//non member payment
                    'reference_id' => $entry->id,
                    'member_entry_id' => null,
                    'lines' => $lines,
                ]);


               

                return response()->json([
                    'message' => 'Entry created successfully',
                    'id' => $entry->id,
                ], 201);
            }, 5);
        } catch (\Exception $e) {
             if (str_contains($e->getMessage(), 'Voucher entry not balanced')) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }

            return response()->json([
                'message' => 'An error occurred while creating the entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }







    public function searchCustomerDetails(Request $request)
    {

        try {
            $searchTerm = $request->input('search');

            $customers = MeterIssue::where('is_active', 1)
                ->where(function ($query) use ($searchTerm) {
                    $query->where('member_entry_id', 'like', "%{$searchTerm}%")
                        ->orWhere('customer_name_en', 'like', "%{$searchTerm}%")
                        ->orWhere('customer_name_np', 'like', "%{$searchTerm}%");
                })
                ->select(
                    'member_entry_id',
                    'customer_name_en',
                    'customer_name_np',
                    'meter_no',
                    'demand_capacity'
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

    public function getById(Request $request, $id)
    {
        try {
            $entry = NonMemberPayment::withoutTrashed()->findOrFail($id);
            $payments = $entry->payments()->where('type', 9)->get();
            $paymentData = $this->paymentService->transformPayments($payments);

            $response = [
                'non_member_payment_entry' => array_merge($entry->toArray(), $paymentData)
            ];

            return response()->json($response, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Non Member Payment entry not found or already deleted',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the Non Member Payment entry',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function list(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view non member payment')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $records = NonMemberPayment::withoutTrashed()
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            $records->getCollection()->transform(function ($record) {
                $payments = $record->payments()->where('type', 9)->get();
                $paymentData = $this->paymentService->transformPayments($payments);

                return array_merge($record->toArray(), $paymentData);
            });

            return response()->json(['non_member_payments' => $records], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing Non Member Payment entries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }






}
