<?php


namespace App\Http\Controllers\Backend;

use App\Helpers\Helper;
use App\Helpers\NepaliCalendar;
use App\Http\Controllers\Controller;

use App\Models\MasterSetup;
use App\Models\NEAPaymentEntry;
use App\Models\NEAPurchase;
use App\Models\Payment;
use App\Services\Accounting\NEAPaymentAccountingService;
use App\Services\PaymentService;
use App\Services\PaymentValidationService;
use App\Services\VoucherValidationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use App\Http\Requests\NEAPaymentEntry\StoreRequest;
use App\Http\Requests\NEAPaymentEntry\UpdateRequest;
use App\Models\AccountHead;
use App\Models\MeterIssue;
use App\Services\VoucherBalanceService;
use App\Services\VoucherEntryService;

class NEAPaymentEntryController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService,protected VoucherEntryService $voucherService)
    {
        $this->paymentService = $paymentService;
    }

    public function store(StoreRequest $request)
    {
        $connection = (new NEAPaymentEntry)->getConnectionName() ?: config('database.default');

        try {
            return DB::connection($connection)->transaction(function () use ($request) {
                $validated = $request->validated();


//                 $purchase = NEAPurchase::on('tenant')
//                     ->where('transformer_id', $validated['transformer_id'])
//                     ->where('month', $validated['month'])
//                     ->whereNull('deleted_at')
//                     ->first();

//                 if (!$purchase) {
//                     return response()->json(['message' => 'No matching NEA purchase found for this transformer and month.'], 404);
//                 }

//                 $earliestUnpaid = NEAPurchase::on('tenant')
//                     ->where('transformer_id', $validated['transformer_id'])
//                     ->whereNull('deleted_at')
//                     ->whereDoesntHave('payments')
//                     ->orderBy('month', 'asc')
//                     ->first();

//                 if ($earliestUnpaid && $validated['month'] > $earliestUnpaid->month) {
//                     return response()->json([
//                         'message' => "You must first pay for month {$earliestUnpaid->month} before paying for month {$validated['month']}."
//                     ], 422);
//                 }

//                 $totalPaid = NEAPaymentEntry::on('tenant')
//                     ->whereNull('deleted_at')
//                     ->where('is_cancel', 0)
//                     ->where('transformer_id', $validated['transformer_id'])
//                     ->where('month', $validated['month'])
//                     ->sum('paid_amount');

//                 $remainingDue = $purchase->amount - $totalPaid;

//                 if ($remainingDue <= 0) {
//                     return response()->json([
//                         'message' => 'Payment not allowed. This month’s due amount is already cleared.'
//                     ], 400);
//                 }
// $actualPayment = $validated['paid_amount'] - $validated['fine_amount'];
//                 if ($actualPayment > $remainingDue) {
//                     return response()->json([
//                         'message' => "Payment exceeds remaining due. Remaining due is {$remainingDue}."
//                     ], 400);
//                 }

                $entry = NEAPaymentEntry::on('tenant')->create([
                    'date_in_bs' => $validated['date_in_bs'],
                    'date_in_ad' => $validated['date_in_ad'],
                    'transformer_id' => $validated['transformer_id'],
                    'voucher_no' => $validated['voucher_no'],
                    'month' => $validated['month'],
                    'due_amount' => $validated['due_amount'],
                    'paid_amount' => $validated['paid_amount'],
                    'fine_amount' => $validated['fine_amount'],
                    'rebate_amount' => $validated['rebate_amount'],
                    'total_amount' => $validated['total_amount'],
                ]);
                $this->paymentService->createPayments($entry->id, [
                    'cash_amount' => $validated['cash_amount'] ?? 0,
                    'bank_amount' => $validated['bank_amount'] ?? 0,
                    'cheque_no' => $validated['cheque_no'] ?? null,
                    'bank_id' => $validated['bank_id'] ?? null,
                ], 10);
                // app(NEAPaymentAccountingService::class)
                //     ->createVoucher($entry);
                 $neapayment = $validated['paid_amount'] - $validated['fine_amount'] + $validated['rebate_amount'];
                $lines[] = [
                    'account_head_id' => 21,
                    'particulars' => "NEA Payment {$entry->voucher_no})",
                    'credit' => $neapayment,
                ];
                
    
                if ($validated['payment_by_cash'] && $validated['cash_amount'] > 0) {
                    $lines[] = [
                        'account_head_id' => 1,
                        'debit' => $validated['cash_amount'],
                        'particulars' => "Cash NEA Payment ({$entry->voucher_no})",
                    ];
                }
                
    
                if ($validated['payment_by_bank'] && $validated['bank_amount'] > 0) {
                    $lines[] = [
                        'account_head_id' => 21,
                        'debit' => $validated['bank_amount'],
                        'particulars' => "Bank NEA Payment ({$entry->voucher_no})",
                    ];
                }
               if ($validated['rebate_amount'] > 0) {
    $lines[] = [
        'account_head_id' => 23,
        'debit' => $validated['rebate_amount'], // change from credit to debit
        'particulars' => "Rebate Discount (NEA Payment {$entry->voucher_no})",
    ];       
}

                if ($validated['fine_amount'] > 0) {
                    $lines[] = [
                        'account_head_id' => 13,
                        'particulars' => "Fine Expenses (NEA Payment {$entry->voucher_no})",
                        'credit' => $validated['fine_amount'],
                    ];
                }
                VoucherBalanceService::validate($lines);
    
                $voucher = $this->voucherService->create([
                    'date' => $entry->date_in_ad,
                    'fiscal_year_id' => Helper::getActiveFiscalYearId(),
                    'voucher_no' => $entry->voucher_no,
                    'particulars' => "Nea Payment {$entry->voucher_no}",
                    'status' => 2,
                    'reference_type' => 10,// Nea Payment
                    'reference_id' => $entry->id,
                    'member_entry_id' => null,
                    'lines' => $lines,
                ]);

                return response()->json([
                    'message' => 'NEA Payment successfully created.',
                    'data' => $entry
                ], 201);

            }, 5);
        } catch (\Exception $e) {
             if (str_contains($e->getMessage(), 'Voucher entry not balanced')) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }

            return response()->json([
                'message' => $e->getMessage() ?: 'An error occurred while creating payment',
                'errors' => ['exception' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function index(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view nea payment')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $search = $request->query('search');
            $fromDate = $request->query('from_date');
            $toDate = $request->query('to_date');
            $fromDateBs = $request->query('from_date_bs');
            $toDateBs = $request->query('to_date_bs');

            $recordsQuery = NEAPaymentEntry::withoutTrashed()
                ->orderBy('created_at', 'desc')
                ->with([
                    'payments.bank:id,name,name_np',
                    'transformer:id,name_en,name_np'
                ]);

            /* 🔍 Search */
            if (!empty($search)) {
                $recordsQuery->where(function ($q) use ($search) {
                    $q->where('month', $search)
                        ->orWhere('voucher_no', 'like', "%{$search}%")
                        ->orWhereHas('transformer', function ($q) use ($search) {
                            $q->where('name_en', 'like', "%{$search}%")
                                ->orWhere('name_np', 'like', "%{$search}%");
                        });
                });
            }

            /*  AD date filter */
            if (!empty($fromDate) && !empty($toDate)) {
                $recordsQuery->whereBetween('date_in_ad', [$fromDate, $toDate]);
            } elseif (!empty($fromDate)) {
                $recordsQuery->whereDate('date_in_ad', '>=', $fromDate);
            } elseif (!empty($toDate)) {
                $recordsQuery->whereDate('date_in_ad', '<=', $toDate);
            }

            /*  BS date filter */
            if (!empty($fromDateBs) && !empty($toDateBs)) {
                $recordsQuery->whereBetween('date_in_bs', [$fromDateBs, $toDateBs]);
            } elseif (!empty($fromDateBs)) {
                $recordsQuery->whereDate('date_in_bs', '>=', $fromDateBs);
            } elseif (!empty($toDateBs)) {
                $recordsQuery->whereDate('date_in_bs', '<=', $toDateBs);
            }

            $records = $recordsQuery->paginate(10);

            /*  Transform like Meter Insurance */
            $records->getCollection()->transform(function ($record) {

                // Transformer info
                $record->transformer_name_en = $record->transformer->name_en ?? null;
                $record->transformer_name_np = $record->transformer->name_np ?? null;

                // Cash & Bank amounts
                $record->cash_amount = round(
                    $record->payments->whereNull('bank_id')->sum('amount'),
                    2
                );

                $record->bank_amount = round(
                    $record->payments->whereNotNull('bank_id')->sum('amount'),
                    2
                );

                // Bank names
                $bankPayments = $record->payments
                    ->whereNotNull('bank_id')
                    ->pluck('bank')
                    ->filter();

                $record->bank_name_en = $bankPayments->pluck('name_en')->implode(', ');
                $record->bank_name_np = $bankPayments->pluck('name_np')->implode(', ');

                unset($record->payments, $record->transformer);

                return $record;
            });

            return response()->json([
                'nea_payments' => $records
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch NEA payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    public function getDueAmount(Request $request)
    {
        try {
            $transformer_id = (int) $request->query('transformer', 0);
            $month = $request->query('month');

            if ($transformer_id <= 0) {
                return response()->json(['message' => 'Invalid transformer_id.'], 422);
            }

            if ($month !== null) {
                $month = (int) $month;
                if ($month < 1 || $month > 12) {
                    return response()->json(['message' => 'Month must be between 1 and 12.'], 422);
                }
            }

            if ($month !== null) {
                $paymentForMonth = NEAPaymentEntry::on('tenant')
                     ->where('is_cancel', 0)
                    ->where('transformer_id', $transformer_id)
                    ->where('month', $month)
                    ->whereNull('deleted_at')
                    ->first();

                if ($paymentForMonth) {
                    return response()->json([
                        'message' => 'No due amount (payment already made for this month).',
                        'data' => null
                    ], 200);
                }
            }

            $latestPayment = NEAPaymentEntry::on('tenant')
                ->whereNull('deleted_at')
                ->where('is_cancel', 0)
                ->where('transformer_id', $transformer_id)
                ->orderByDesc('created_at')
                ->first();

            $dueAmount = $latestPayment ? max(0, (float) $latestPayment->due_amount - (float) $latestPayment->paid_amount) : 0;
            $previousDueAmount = $latestPayment ? (float) $latestPayment->due_amount : 0;

            $purchaseAmount = null;
            $totalUnits = null;

            if ($month !== null) {
                $purchase = NEAPurchase::on('tenant')
                    ->where('transformer_id', $transformer_id)
                    ->where('month', $month)
                    ->whereNull('deleted_at')
                    ->first();

                if ($purchase) {
                    $purchaseAmount = (float) $purchase->amount;
                    $totalUnits = $purchase->total_units;
                }
            }

            return response()->json([
                'message' => 'Due amount retrieved successfully.',
                'data' => [
                    'transformer_id' => $transformer_id,
                    'month' => $month,
                    'purchase_amount' => $purchaseAmount !== null ? number_format($purchaseAmount, 4, '.', '') : null,
                    'total_units' => $totalUnits,
                    'previous_due_amount' => number_format($previousDueAmount, 4, '.', ''),
                    'due_amount' => number_format($dueAmount, 4, '.', '')
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Server error'], 500);
        }
    }



   
public function getById($id)
{
    try {
        $entry = NEAPaymentEntry::withoutTrashed()->findOrFail($id);

        $entry->bank_name_en = null;
        $entry->bank_name_np = null;

        $payments = Payment::on('tenant')
            ->where('reference_id', $entry->id)
            ->where('type', 10)
            ->where('is_cancel', 0)
            ->get(['bank_id']);

        $bankIds = $payments
            ->whereNotNull('bank_id')
            ->pluck('bank_id')
            ->unique()
            ->values();

        if ($bankIds->isNotEmpty()) {
            $banks = AccountHead::on('tenant')
                ->whereIn('id', $bankIds)
                ->where('account_group_id', 10) // bank group
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->get(['name', 'name_np']);

            $entry->bank_name_en = $banks->pluck('name')->implode(', ');
            $entry->bank_name_np = $banks->pluck('name_np')->implode(', ');
        }

        return response()->json([
            'message' => 'Payment entry fetched successfully',
            'data' => $entry
        ], 200);

    } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Payment entry not found'], 404);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
    }
}

}
