<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\NepaliCalendar;
use App\Http\Controllers\Controller;
use App\Imports\ShareEntryImport;
use App\Models\FiscalYear;
use App\Services\Accounting\ShareAccountingService;
use App\Services\PaymentService;
use App\Services\ShareQuantityService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\ShareTransaction;
use App\Models\Payment;
use App\Models\MemberEntry;
use App\Models\MasterSetup;
use App\Models\MeterIssue;
use App\Models\ShareOpeningEntry;
use Carbon\Carbon;
use Maatwebsite\Excel\Excel;
use App\Services\VoucherEntryService;
use App\Helpers\Helper;
use App\Models\AccountHead;
use App\Services\VoucherBalanceService;

class ShareTransactionController extends Controller
{
    protected PaymentService $paymentService;
    protected ShareQuantityService $shareService;


    public function __construct(PaymentService $paymentService, ShareQuantityService $shareService, protected VoucherEntryService $voucherService)
    {
        $this->paymentService = $paymentService;
        $this->shareService = $shareService;

    }
    public function createShareEntry(Request $request)
    {
        return $this->createTransactionByType($request, 1, 7); // share entry
    }

    public function createShareReturn(Request $request)
    {
        return $this->createTransactionByType($request, 2, 8); // share return
    }

    private function createTransactionByType(Request $request, int $transactionType, int $paymentType)
    {
        
        try {
           return DB::connection('tenant')->transaction(function () use ($request, $transactionType, $paymentType) {

                $totalAvailableShares = 0;

                if ($transactionType === 2) {
                    $shares = $this->shareService->getExistingSharesforReturn($request->member_entry_id, $request->service_charge);
                    $totalAvailableShares = $shares['existing_share_quantity'];
                    $totalAvailableSharesAmount = $shares['existing_share_amount'];






                    $request->merge([
                        'existing_share_quantity' => $totalAvailableShares,
                        'existing_share_amount' => $totalAvailableSharesAmount,
                    ]);
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
                    ],
                    'share_type' => ['required', 'string', 'in:electricity'],
                    'share_certificate_no' => ['required', 'string', 'max:20'],
                    'voucher_no' => [
                        'required',
                        'string',
                        'max:20',
                        function ($attribute, $value, $fail) use ($request, $transactionType) {

                            try {
                                $prefixMap = [
                                    1 => 'SE',
                                    2 => 'SR',
                                    3 => 'OS',
                                ];

                                if (!isset($prefixMap[$transactionType])) {
                                    $fail("Invalid transaction type.");
                                    return;
                                }

                                $prefix = $prefixMap[$transactionType];
                                $adDate = Carbon::now()->format('Y-m-d');
                                $bsDate = NepaliCalendar::adToBs($adDate);
                                $bsParts = explode('-', $bsDate);
                                $currentBsYear = (int) $bsParts[0];
                                $currentBsMonth = (int) $bsParts[1];
                                $fiscalYear = $currentBsMonth >= 4 ? $currentBsYear : $currentBsYear - 1;
                                $fiscalYearCode = substr($fiscalYear, 2, 2) . substr($fiscalYear + 1, 2, 2);

                                $lastReceipt = ShareTransaction::withTrashed()
                                    ->where('voucher_no', 'like', "{$prefix}{$fiscalYearCode}%")
                                    ->orderBy('id', 'desc')
                                    ->first();

                                $lastNumber = $lastReceipt ? (int) substr($lastReceipt->voucher_no, 8) : 0;
                                $expectedVoucherNo = "{$prefix}{$fiscalYearCode}-" . str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);

                                if ($value !== $expectedVoucherNo) {
                                    $fail("Invalid {$attribute}. Expected: {$expectedVoucherNo}.");
                                }
                            } catch (\Exception $e) {
                                $fail("Error validating {$attribute}: " . $e->getMessage());
                            }
                        }
                    ],

                    'share_quantity' => in_array($transactionType, [1, 3])
                        ? ['required', 'integer', 'min:1']
                        : ['nullable'],

                    'existing_share_quantity' => $transactionType === 2
                        ? ['required', 'integer', 'min:1']
                        : ['nullable'],

                    'share_value' => in_array($transactionType, [1, 3])
                        ? ['required', 'numeric', 'in:100']
                        : [],
                    'amount' => $transactionType === 2
                        ? [
                            'required',
                            'numeric',
                            function ($attribute, $value, $fail) use ($totalAvailableSharesAmount) {                               
                                $expected = $totalAvailableSharesAmount;
                                if (bccomp((string) $value, (string) $expected, 2) !== 0) {
                                    $fail("Amount must be equal to existing share amount ({$expected}).");
                                }
                            },
                        ]
                        : [
                            'required',
                            'numeric',
                            function ($attribute, $value, $fail) use ($request) {
                                $expected = $request->share_quantity * 100;
                                if ($value !== $expected) {
                                    $fail("Amount must equal share_quantity × 100 (expected {$expected}).");
                                }
                            },
                        ],






                    'service_charge' => $transactionType === 2 ? ['required', 'numeric', 'min:0'] : [],
                    'payment_by_cash' => [
                        'required',
                        'boolean',
                        function ($attribute, $value, $fail) use ($request) {
                            if (!$value && !$request->input('payment_by_bank', false)) {
                                $fail('At least one of payment_by_cash or payment_by_bank must be true.');
                            }
                        },
                    ],
                    'payment_by_bank' => 'required|boolean',
                    'cash_amount' => [
                        'nullable',
                        'numeric',
                        'min:0',
                        'max:999999999999.99',
                        function ($attribute, $value, $fail) use ($request, $transactionType) {

                            if (in_array($transactionType, [1, 3])) {
                                $total = $request->input('share_quantity', 0) * 100;
                            } elseif ($transactionType === 2) {
                                $total = $request->input('amount', 0);
                            } else {
                                $total = $request->input('amount', 0);
                            }

                            $cashSelected = $request->input('payment_by_cash', false);
                            $bankSelected = $request->input('payment_by_bank', false);
                            $bankAmount = $request->input('bank_amount', 0);

                            if ($cashSelected) {
                                if ($bankSelected) {
                                    if (($value + $bankAmount) != $total) {
                                        $fail("The sum of cash_amount and bank_amount must equal the total amount ({$total}).");
                                    }
                                } else {
                                    if ($value != $total) {
                                        $fail("cash_amount must equal the total amount ({$total}).");
                                    }
                                }
                            } elseif ($value > 0) {
                                $fail("cash_amount cannot be greater than 0 if payment_by_cash is not selected.");
                            }

                            if (($value ?? 0) == 0 && ($bankAmount ?? 0) == 0) {
                                $fail("Both cash_amount and bank_amount cannot be zero.");
                            }
                        },
                    ],

                    'bank_amount' => [
                        'nullable',
                        'numeric',
                        'min:0',
                        'max:999999999999.99',
                        function ($attribute, $value, $fail) use ($request, $transactionType) {

                            if (in_array($transactionType, [1, 3])) { // share entry
                                $total = $request->input('share_quantity', 0) * 100;
                            } elseif ($transactionType === 2) {
                                $total = $request->input('amount', 0);
                            } else {
                                $total = $request->input('amount', 0);
                            }

                            $cashSelected = $request->input('payment_by_cash', false);
                            $bankSelected = $request->input('payment_by_bank', false);
                            $cashAmount = $request->input('cash_amount', 0);

                            if ($bankSelected) {
                                if ($cashSelected) {
                                    if (($value + $cashAmount) != $total) {
                                        $fail("The sum of cash_amount and bank_amount must equal the total amount ({$total}).");
                                    }
                                } else {
                                    if ($value != $total) {
                                        $fail("bank_amount must equal the total amount ({$total}).");
                                    }
                                }
                            } elseif ($value > 0) {
                                $fail("bank_amount cannot be greater than 0 if payment_by_bank is not selected.");
                            }

                            if (($value ?? 0) == 0 && ($cashAmount ?? 0) == 0) {
                                $fail("Both cash_amount and bank_amount cannot be zero.");
                            }
                        },
                    ],


                    'cheque_no' => [
                        'nullable',
                        'string',
                        'max:20',
                        'required_if:payment_by_bank,1',
                    ],
                   'bank_id' => [
                    'nullable',
                    'required_if:payment_by_bank,1',
                    'integer',
                    function ($attribute, $value, $fail) {
                        if (!AccountHead::on('tenant')
                            ->where('id', $value)
                            ->where('account_group_id', 10) // bank accounts
                            ->where('is_active', 1)
                            ->whereNull('deleted_at')
                            ->exists()) {
                            
                            $fail("The selected {$attribute} is invalid. Must be a bank in account head whose account group must be Bank Accounts.");
                        }
                    },
                ],
                ]);
                //dd('yes');

                $fields = ['total_amount', 'cash_amount', 'bank_amount', 'share_value', 'return_amount', 'service_charge', 'return_share_amount', 'existing_share_amount'];
                foreach ($fields as $f) {
                    $validated[$f] = isset($validated[$f]) ? (float) $validated[$f] : 0.00;

                }

                $finalShareQuantity = $transactionType === 2
                    ? (int) $validated['existing_share_quantity']
                    : (int) $validated['share_quantity'];

                $fiscalYearID = FiscalYear::whereNull('deleted_at')->where('status', 1);
              


                $transaction = ShareTransaction::create([
                    'date_in_bs' => $validated['date_in_bs'],
                    'date_in_ad' => $validated['date_in_ad'],
                    'member_entry_id' => $validated['member_entry_id'],
                    'fiscal_year_id' => $fiscalYearID,
                    'share_type' => $validated['share_type'],
                    'share_certificate_no' => $validated['share_certificate_no'],
                    'transaction_type' => $transactionType,
                    'share_quantity' => $finalShareQuantity,

                    'share_value' => in_array($transactionType, [1, 3]) ? $validated['share_value'] : 100,
                    'amount' => in_array($transactionType, [1, 3])
                        ? $validated['amount']
                        : $validated['amount'] ?? 0,

                    'service_charge' => $transactionType === 2 ? $validated['service_charge'] : 0,
                    'voucher_no' => $validated['voucher_no'],
                ]);

                if ($validated['payment_by_cash'] && $validated['cash_amount'] > 0) {
                    Payment::create([
                        'reference_id' => $transaction->id,
                        'type' => $paymentType,
                        'amount' => $validated['cash_amount'],
                        'payment_mode' => 1
                    ]);
                }

                if ($validated['payment_by_bank'] && $validated['bank_amount'] > 0) {
                    Payment::create([
                        'reference_id' => $transaction->id,
                        'type' => $paymentType,
                        'amount' => $validated['bank_amount'],
                        'payment_mode' => 2,
                        'cheque_no' => $validated['cheque_no'] ?? null,
                        'bank_id' => $validated['bank_id'] ?? null
                    ]);
                }

                if ($transactionType == 1) {
                    $lines[] = [
                        'account_head_id' => 7,
                        'particulars' => "Share Account ($transaction->voucher_no})",
                        'credit' => $validated['amount'],
                    ];


                    if ($validated['payment_by_cash'] && $validated['cash_amount'] > 0) {
                        $lines[] = [
                            'account_head_id' => 1,
                            'debit' => $validated['cash_amount'],
                            'particulars' => "Cash Received (Share Account {$transaction->voucher_no})",
                        ];
                    }


                    if ($validated['payment_by_bank'] && $validated['bank_amount'] > 0) {
                        $lines[] = [
                            'account_head_id' => $validated['bank_id'], 
                            'debit' => $validated['bank_amount'],
                            'particulars' => "Bank Received (Share Account {$transaction->voucher_no})",
                        ];
                    }
                    VoucherBalanceService::validate($lines);
                    $voucher = $this->voucherService->create([
                        'date' => $validated['date_in_ad'],
                        'fiscal_year_id' => Helper::getActiveFiscalYearId(),
                        'voucher_no' => $transaction->voucher_no,
                        'particulars' => "Share EntryAccount {$transaction->voucher_no}",
                        'status' => 2,
                        'reference_type' => 7,//share entry
                        'reference_id' => $transaction->id,
                        'member_entry_id' =>$validated['member_entry_id'],
                        'lines' => $lines,
                    ]);
                }
                    if($transactionType == 2){
                        $lines[] = [
                            'account_head_id' => 7,
                            'particulars' => "Share Return ($transaction->voucher_no})",
                            'debit' =>  bcadd((string) $validated['amount'],(string) $validated['service_charge'],2),
                        ];


                        if ($validated['payment_by_cash'] && $validated['cash_amount'] > 0) {
                            $lines[] = [
                                'account_head_id' => 1,
                                'credit' => $validated['cash_amount'],
                                'particulars' => "Cash Received (Share Return {$transaction->voucher_no})",
                            ];
                        }


                        if ($validated['payment_by_bank'] && $validated['bank_amount'] > 0) {
                            $lines[] = [
                               'account_head_id' => $validated['bank_id'], 
                                'credit' => $validated['bank_amount'],
                                'particulars' => "Bank Received (Share Return {$transaction->voucher_no})",
                            ];
                        }

                        if ($validated['service_charge'] > 0) {
                            $lines[] = [
                                    'account_head_id' => 17,
                                    'credit' => $validated['service_charge'],
                                    'particulars' => "Service Charge (Share Deposit Return)",
                                ];
                            }
                        VoucherBalanceService::validate($lines);
                        $voucher = $this->voucherService->create([
                            'date' => $validated['date_in_ad'],
                            'fiscal_year_id' => Helper::getActiveFiscalYearId(),
                            'voucher_no' => $transaction->voucher_no,
                            'particulars' => "Share Return {$transaction->voucher_no}",
                            'status' => 2,
                            'reference_type' => 8,//share entry
                            'reference_id' => $transaction->id,
                            'member_entry_id' =>$validated['member_entry_id'],
                            'lines' => $lines,
                        ]);
                    }
                   

                    // app(ShareAccountingService::class)
                    //     ->createVoucherEntries($transaction, $transactionType);
                // }
            

                return response()->json([
                    'message' => $transactionType === 1 ? 'Share entry created successfully' : 'Share return created successfully',
                    'data' => $transaction
                ], 201);

            }, 5);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            return response()->json(['message' => collect($errors)->flatten()->first(), 'errors' => $errors], 422);
        } catch (\Exception $e) {
             if (str_contains($e->getMessage(), 'Voucher entry not balanced')) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
            return response()->json(['message' => 'An error occurred while creating the share transaction', 'error' => $e->getMessage()], 500);
        }
    }








    public function indexShareEntries(Request $request)
    {
        return $this->getTransactionsByType($request, 1); // share entry
    }

    public function indexShareReturns(Request $request)
    {
        return $this->getTransactionsByType($request, 2); // share return
    }

    private function getTransactionsByType(Request $request, int $type)
    {
        //$permission = $type === 0 ? 'view share entries' : 'view share return';

        // if (!$request->user()->hasOrganizationPermission($permission)) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $search = $request->query('search');

            $query = ShareTransaction::with(['memberEntry', 'payments.bank'])
                ->where('transaction_type', $type);

            
            if (!empty($search)) {
                $query->whereHas('memberEntry', function ($q) use ($search) {
                    $q->where('member_no', 'like', "%{$search}%")
                        ->orWhere('customer_name_en', 'like', "%{$search}%")
                        ->orWhere('customer_name_np', 'like', "%{$search}%");
                });
            }

         
            $transactions = $query->orderBy('id', 'desc')->paginate(10);

            
            $transactions->getCollection()->transform(function ($transaction) use ($type) {
                $paymentData = $this->paymentService->transformPayments($transaction->payments ?? []);

                $data = [
                    'id' => $transaction->id,
                    'date_in_bs' => $transaction->date_in_bs,
                    'date_in_ad' => $transaction->date_in_ad,
                    'member_entry_id' => $transaction->member_entry_id,
                    'voucher_no' => $transaction->voucher_no,
                    'transaction_type' => $transaction->transaction_type,
                    'share_quantity' => $transaction->share_quantity,

                    'share_value' => $transaction->share_value,
                    'amount' => $transaction->amount,

                    'service_charge' => $transaction->service_charge,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at,
                    'deleted_at' => $transaction->deleted_at,
                    'member_no' => $transaction->memberEntry->member_no ?? null,
                    'member_name_en' => $transaction->memberEntry->customer_name_en ?? null,
                    'member_name_np' => $transaction->memberEntry->customer_name_np ?? null,
                ];

                if ($type === 0) {
                    unset(
                        $data['transaction_type'],
                        $data['return_share_quantity'],
                        $data['return_amount'],
                        $data['service_charge']
                    );
                }

                return array_merge($data, $paymentData);
            });

            return response()->json($transactions, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching share transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function showShareEntry($id)
    {
        return $this->getShareDetaiByMemberIdAndType($id, 1);
    }
    public function showShareReturn($id)
    {
        //show the share details onwn by member
        return $this->getShareDetaiByMemberIdAndType($id, 1);
    }
    private function getTransactionByType($id, int $type)
    {
        try {
            $transaction = ShareTransaction::with(['memberEntry', 'payments'])
                ->where('transaction_type', $type)
                ->findOrFail($id);          

            $paymentData = $this->paymentService->transformPayments($transaction->payments ?? []);

            $data = [
                'id' => $transaction->id,
                'date_in_bs' => $transaction->date_in_bs,
                'date_in_ad' => $transaction->date_in_ad,
                'member_entry_id' => $transaction->member_entry_id,
                'voucher_no' => $transaction->voucher_no,
                'transaction_type' => $transaction->transaction_type,
                'share_quantity' => $transaction->share_quantity,
                'share_value' => $transaction->share_value,
                'service_charge' => $transaction->service_charge,
                'total_amount' => $transaction->amount,
                'created_at' => $transaction->created_at,
                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at,
                'deleted_at' => $transaction->deleted_at,
                'member_name_en' => $transaction->memberEntry->customer_name_en ?? null,
                'member_name_np' => $transaction->memberEntry->customer_name_np ?? null,
            ];

            if ($type === 1) {
                unset(
                    $data['transaction_type'],                  
                    $data['service_charge']
                );
            }

            $data = array_merge($data, $paymentData);

            return response()->json([
                'message' => $type === 1 ? 'Share entry retrieved successfully' : 'Share return retrieved successfully',
                'data' => $data
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Share return Transaction not found or not of the requested type',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the share transaction !',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    private function getShareDetaiByMemberIdAndType($id, int $type)
    {
       
        try {
            $transaction = ShareTransaction::with(['memberEntry', 'payments'])
                ->where('transaction_type', $type)
                ->where('member_entry_id', $id)
                ->firstOrFail(); 
                
                
                $totals = ShareTransaction::where('member_entry_id', $id)
                ->whereIn('transaction_type', [1, 2, 3])
                ->selectRaw("
                    SUM(
                        CASE 
                            WHEN transaction_type IN (1, 3) THEN share_quantity
                            WHEN transaction_type = 2 THEN -share_quantity
                            ELSE 0
                        END
                    ) as total_share_quantity,
                    SUM(
                        CASE 
                            WHEN transaction_type IN (1, 3) THEN amount
                             WHEN transaction_type = 2 THEN -(amount + service_charge)
                            ELSE 0
                        END
                    ) as total_amount
                ")
                ->first();           

            $paymentData = $this->paymentService->transformPayments($transaction->payments ?? []);

            $data = [
                'id' => $transaction->id,
                'date_in_bs' => $transaction->date_in_bs,
                'date_in_ad' => $transaction->date_in_ad,
                'member_entry_id' => $transaction->member_entry_id,
                'voucher_no' => $transaction->voucher_no,
                'transaction_type' => $transaction->transaction_type,
                'share_quantity' => $totals->total_share_quantity,
                'share_value' => $transaction->share_value,
                'service_charge' => $transaction->service_charge,
                'total_amount' => $totals->total_amount,
                'share_certificate_no' => $transaction->share_certificate_no,
                'created_at' => $transaction->created_at,
                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at,
                'deleted_at' => $transaction->deleted_at,
                'member_name_en' => $transaction->memberEntry->customer_name_en ?? null,
                'member_name_np' => $transaction->memberEntry->customer_name_np ?? null,
            ];

            if ($type === 1) {
                unset(
                    $data['transaction_type'],                  
                    $data['service_charge']
                );
            }

            // $data = array_merge($data, $paymentData);

            return response()->json([
                'message' => $type === 1 ? 'Share entry retrieved successfully' : 'Share return retrieved successfully',
                'data' => $data
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Share return Transaction not found or not of the requested type',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the share transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    


    public function searchShareEntries()
    {
        return $this->searchCustomersByShareType(0);
    }

    public function searchShareReturns()
    {
        return $this->searchCustomersByShareType(1);
    }

    public function cancelShareEntry(Request $request, $id)
    {
        try {
            $transaction = ShareTransaction::whereNull('deleted_at')
                ->findOrFail($id);
            if ($transaction->is_cancel == 1) {
                return response()->json([
                    'error' => true,
                    'message' => 'Already Cancelled!.',
                ], 422);
            }

            $transaction->update(['is_cancel' => 1]);

            return response()->json([
                'message' => 'Transaction cancelled successfully',
                'data' => $transaction,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'An error occurred while cancelling the share transaction',
                'error' => $e->getMessage()
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while cancelling the share transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    public function cancelShareReturn(Request $request, $id)
    {
        try {
            $transaction = ShareTransaction::whereNull('deleted_at')
                ->findOrFail($id);
            if ($transaction->is_cancel == 1) {
                return response()->json([
                    'error' => true,
                    'message' => 'Already Cancelled!.',
                ], 422);
            }

            $transaction->update(['is_cancel' => 1]);

            return response()->json([
                'message' => 'Transaction cancelled successfully',
                'data' => $transaction,
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Transaction not found',
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'An error occurred while cancelling the share transaction',
                'error' => $e->getMessage()
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while cancelling the share transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function searchCustomersByShareType(int $type)
    {
        try {
            $searchTerm = request()->input('search');

            $matchedCustomers = MemberEntry::on('tenant')
               
                ->where('is_active', 1)
                ->where(function ($query) use ($searchTerm) {
                    $query->where('member_no', 'like', "%{$searchTerm}%")
                        ->orWhere('customer_name_en', 'like', "%{$searchTerm}%")
                        ->orWhere('customer_name_np', 'like', "%{$searchTerm}%");
                })
                ->select('id', 'member_no', 'customer_name_en', 'customer_name_np')
                ->get();

            if ($matchedCustomers->isEmpty()) {
                return response()->json([
                    'message' => 'No matching customers found.',
                    'data' => [],
                ], 200);
            }

            $customers = $matchedCustomers->map(function ($customer) use ($type) {

                // Use ShareQuantityService to get existing shares
                $shares = $this->shareService->getExistingShares($customer->id);

                $data = [
                    'member_entry_id' => $customer->id,
                    'member_no' => $customer->member_no,
                    'customer_name_en' => $customer->customer_name_en,
                    'customer_name_np' => $customer->customer_name_np,
                ];

                if ($type === 1) {
                    $data['existing_share_quantity'] = $shares['existing_share_quantity'];
                    $data['existing_share_amount'] = $shares['existing_share_amount'];
                }

                return $data;
            })
                ->filter(fn($c) => $type === 0 || ($c['existing_share_quantity'] ?? 0) > 0)
                ->values();

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

    public function importShareExcel(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('create share entries')) {
        //     return response()->json([
        //         'message' => 'Unauthorized',
        //         'errors' => ['permission' => ['User lacks create share entries permission']]
        //     ], 403);
        // }

        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
            ]);

            $import = new ShareEntryImport();

            DB::transaction(function () use ($import, $request) {
                Excel::import($import, $request->file('file'), null, \Maatwebsite\Excel\Excel::XLSX);
            });

            return response()->json([
                'message' => 'Excel imported successfully',
                'errors' => [],
                'imported' => $import->importedCount,
                'failed_rows' => $import->failedRows,
            ], 200);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $firstErrorMessage = count($failures) > 0 ? $failures[0]->errors()[0] ?? 'Excel validation failed' : 'Excel validation failed';

            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => ['excel_validation' => $failures],
            ], 422);

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();

            return response()->json([
                'message' => $firstErrorMessage ?: 'Validation failed',
                'errors' => $allErrors
            ], 422);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error importing Excel',
                'errors' => ['exception' => [$e->getMessage()]],
            ], 500);
        }
    }




}
