<?php

namespace App\Http\Controllers\Backend;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Imports\DepositEntryImport;
use App\Models\FiscalYear;
use App\Models\MeterDepositTransaction;
use App\Models\MemberEntry;
use App\Models\MasterSetup;
use App\Models\UpgradeMeterCapacity;
use App\Helpers\NepaliCalendar;
use App\Models\MeterIssue;
use App\Models\OpeningMeterDepositEntry;
use App\Models\Payment;
use App\Services\Accounting\MeterDepositAccountingService;
use App\Services\MeterDepositTransactionValidationService;
use App\Services\PaymentService;
use App\Services\PaymentValidationService;
use Doctrine\DBAL\Query\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;
use App\Services\MemberInfoFromMeterIssueService;
use App\Services\VoucherEntryService;
use App\Services\KnowMeterStartService;
use App\Services\VoucherBalanceService;

class MeterDepositTransactionController extends Controller
{
    protected $meterDepositValidationService;
    protected PaymentService $paymentService;
    protected MemberInfoFromMeterIssueService $memberInfoService;
    

    public function __construct(
        PaymentService $paymentService,
        MeterDepositTransactionValidationService
        $meterDepositValidationService,
        MemberInfoFromMeterIssueService $memberInfoService,
        protected VoucherEntryService $voucherService
    ) {
        $this->paymentService = $paymentService;
        $this->meterDepositValidationService = $meterDepositValidationService;
        $this->memberInfoService = $memberInfoService;


    }


    public function createDeposit(Request $request)
    {
        return $this->createTransactionByType($request, 1, 1);
    }
    public function createReturn(Request $request)
    {
        return $this->createTransactionByType($request, 2, 3);
    }

    public function createUpgrade(Request $request)
    {
        return $this->createTransactionByType($request, 3, 2);
    }


    private function createTransactionByType(Request $request, int $transactionType, int $paymentType)
    {



        try {
         return DB::connection('tenant')->transaction(function () use ($request, $transactionType, $paymentType) {
                $meterIssueId = $request->input('meter_issue_id');

                 $lines = []; 

            $meterIssueId = $request->input('meter_issue_id');

            if ($meterIssueId && $transactionType !== 3 && $this->meterDepositValidationService->hasExistingTransaction($meterIssueId, $transactionType)) {
                throw new \Exception("A transaction of this type already exists for this member.");
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
                        'date',
                        function ($attribute, $value, $fail) {
                            if ($value > date('Y-m-d')) {
                                $fail("The {$attribute} cannot be a future date.");
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
                                $fail('The selected meter issue does not exist or is inactive.');
                                return;
                            }
                        },

                    ],
                    'voucher_no' => [
                        'required',
                        'string',
                        'max:20',
                        function ($attribute, $value, $fail) use ($transactionType) {
                            $error = $this->meterDepositValidationService->validateVoucherNo($value, $transactionType);
                            if ($error)
                                $fail($error);
                        },
                    ],
                    "existing_capacity_id" => [
                        'required_if:transaction_type,3',
                        'integer',
                    ],

                    "upgraded_capacity_id" => [
                        'required_if:transaction_type,3',
                        'integer',
                    ],

                    'amount' => [
                        'required',
                        'numeric',
                        'min:1',
                        'max:999999999999.99',
                        function ($attribute, $value, $fail) use ($transactionType, $request) {

                            if ($transactionType === 2) { // returns
                                $meterIssueId = $request->input('meter_issue_id');
                                $existingAmount = $this->meterDepositValidationService->getExistingDepositAmountforReturn($meterIssueId, $request->service_charge);

                                if ($existingAmount <= 0) {
                                    $fail("Cannot create return: no deposits found for this member.");
                                }

                                if ($value != $existingAmount) {
                                    $fail("Return amount ({$value}) must match existing deposit amount ({$existingAmount}).");
                                }
                            }
                        },
                    ],

                    'service_charge' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'charge_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
                    'payment_by_cash' => [
                        'required',
                        'boolean',
                        function ($attribute, $value, $fail) use ($request) {

                            PaymentValidationService::validatePaymentMethods($request, $attribute, $value, $fail);

                        },
                    ],

                    'payment_by_bank' => ['required', 'boolean'],

                    'cash_amount' => [
                        'nullable',
                        'numeric',
                        'min:0',
                        'max:999999999999.99',
                        function ($attribute, $value, $fail) use ($transactionType, $request) {
                            if ($transactionType == 3) {
                                PaymentValidationService::validateCashAmountForUpgrade($request, $value, $fail);
                            } elseif ($transactionType == 2) {
                                PaymentValidationService::validateCashAmountWithServiceChargeforReturn($request, $value, $fail);
                            } else {
                                PaymentValidationService::validateCashAmountWithServiceCharge($request, $value, $fail);
                            }
                        },
                    ],

                    'bank_amount' => [
                        'nullable',
                        'numeric',
                        'min:0',
                        'max:999999999999.99',
                        function ($attribute, $value, $fail) use ($transactionType, $request) {
                            if ($transactionType == 3) {
                                PaymentValidationService::validateBankAmountForUpgrade($request, $value, $fail);
                            } elseif ($transactionType == 2) {
                                PaymentValidationService::validateBankAmountWithServiceChargeforReturn($request, $value, $fail);

                            } else {
                                PaymentValidationService::validateBankAmountWithServiceCharge($request, $value, $fail);
                            }
                        },
                    ],

                    'cheque_no' => ['nullable', 'string', 'max:20', 'required_if:payment_by_bank,1'],

                    'bank_id' => [
                        'nullable',
                        'required_if:payment_by_bank,1',
                        'integer',
                        function ($attribute, $value, $fail) use ($request) {
                            PaymentValidationService::validateBank($request, $attribute, $value, $fail);
                        }
                    ],
                ]);
                $fiscalYearID = FiscalYear::where('status', 1)
                    ->value('id');
                if ($transactionType === 2) {
                    $meterIssueId = $validated['meter_issue_id'];

                    $serviceCharge = $request->service_charge;

                    $totalDeposits = MeterDepositTransaction::where('meter_issue_id', $meterIssueId)
                        ->where('transaction_type', 1)
                        ->where('is_cancel', 0)
                        ->whereNull('deleted_at')
                        ->sum('amount');

                    $totalReturns = MeterDepositTransaction::where('meter_issue_id', $meterIssueId)
                        ->where('transaction_type', 2)
                        ->where('is_cancel', 0)
                        ->whereNull('deleted_at')
                        ->sum('amount');

                    $openingDeposit = MeterDepositTransaction::where('meter_issue_id', $meterIssueId)
                        ->where('transaction_type', 4)
                        ->where('is_cancel', 0)
                        ->whereNull('deleted_at')
                        ->sum('amount');

                    $upgradedDeposit = MeterDepositTransaction::where('meter_issue_id', $meterIssueId)
                        ->where('transaction_type', 3)
                        ->where('is_cancel', 0)
                        ->whereNull('deleted_at')
                        ->sum('amount');

                    $existingAmount = ($totalDeposits + $openingDeposit + $upgradedDeposit) - $totalReturns - $serviceCharge;

                    $returnAmount = $validated['amount'];
                    if ($existingAmount <= 0) {
                    throw new \Exception('Cannot create return: no deposits found for this member.');
                }

                if ($returnAmount != $existingAmount) {
                    throw new \Exception("Return amount ({$returnAmount}) must match existing deposit amount ({$existingAmount}).");
                }

                }
                if ($transactionType === 3) {
                    $newUpgradeVoucherNo = Helper::generateUpgradeVoucherNo();
                    UpgradeMeterCapacity::create([
                        'date_in_bs' => $validated['date_in_bs'],
                        'date_in_ad' => $validated['date_in_ad'],
                        'meter_issue_id' => $validated['meter_issue_id'],
                        'voucher_no' => $newUpgradeVoucherNo,

                        'existing_capacity_id' => $validated['existing_capacity_id'],
                        'upgraded_capacity_id' => $validated['upgraded_capacity_id'],


                    ]);

                    $meterIssue = MeterIssue::where('id', $validated['meter_issue_id'])
                        ->orderByDesc('id')
                        ->first();

                    if ($meterIssue) {
                        $meterIssue->update(['capacity_id' => $validated['upgraded_capacity_id']]);
                    }
                }
                $transactionData = [
                    'date_in_bs' => $validated['date_in_bs'],
                    'date_in_ad' => $validated['date_in_ad'],
                    'meter_issue_id' => $validated['meter_issue_id'],
                    'fiscal_year_id' => $fiscalYearID,
                    'voucher_no' => $validated['voucher_no'],
                    'transaction_type' => $transactionType,
                    'amount' => $validated['amount'],
                    'service_charge' => $transactionType === 3 ? ($validated['charge_amount'] ?? 0.00) : ($validated['service_charge'] ?? 0.00),
                ];



                $transaction = MeterDepositTransaction::create($transactionData);
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
                        'account_head_id' => 8,
                        'particulars' => "Meter Deposit {$transaction->voucher_no}",
                        'credit' => $validated['amount'],
                    ];


                    if ($validated['payment_by_cash'] && $validated['cash_amount'] > 0) {
                        $lines[] = [
                            'account_head_id' => 1,
                            'debit' => $validated['cash_amount'],
                            'particulars' => "Cash Received (Meter Deposit {$transaction->voucher_no} )",
                        ];
                    }


                    if ($validated['payment_by_bank'] && $validated['bank_amount'] > 0) {
                        $lines[] = [
                           'account_head_id' => $validated['bank_id'], 
                            'debit' => $validated['bank_amount'],
                            'particulars' => "Bank Received (Meter Deposit {$transaction->voucher_no})",
                        ];
                    }
                   VoucherBalanceService::validate($lines);

                  $this->voucherService->create([
                        'date' => $validated['date_in_ad'],
                        'voucher_no' => $transaction->voucher_no,
                        'fiscal_year_id' => Helper::getActiveFiscalYearId(),
                        'particulars' => "Meter Deposit {$transaction->voucher_no}",
                        'status' => 2,
                        'reference_type' => 1,//meter deposit
                        'reference_id' => $transaction->id,
                        'member_entry_id' => MeterIssue::getMemberByMeterIssueId($validated['meter_issue_id'])->id ?? null,
                        'lines' => $lines,
                    ]);
                }

                if ($transactionType == 2) {

                    $lines[] = [
                        'account_head_id' => 8,
                        'particulars' => "Meter Deposit Return {$transaction->voucher_no}",
                        'debit' => bcadd((string) $validated['amount'], (string) $validated['service_charge'], 2),
                    ];


                    if ($validated['payment_by_cash'] && $validated['cash_amount'] > 0) {
                        $lines[] = [
                            'account_head_id' => 1,
                            'credit' => $validated['cash_amount'],
                            'particulars' => "Cash Received (Meter Deposit Return {$transaction->voucher_no})",
                        ];
                    }


                    if ($validated['payment_by_bank'] && $validated['bank_amount'] > 0) {
                        $lines[] = [
                            'account_head_id' => $validated['bank_id'], 
                            'credit' => $validated['bank_amount'],
                            'particulars' => "Bank Received (Meter Deposit Return {$transaction->voucher_no})",
                        ];
                    }
                    if ($validated['service_charge'] > 0) {
                        $lines[] = [
                            'account_head_id' => 17,
                            'credit' => $validated['service_charge'],
                            'particulars' => "Service Charge (Deposit Return {$transaction->voucher_no})",
                        ];
                    }
                     VoucherBalanceService::validate($lines);

                    $this->voucherService->create([
                        'date' => $validated['date_in_ad'],
                        'fiscal_year_id' => Helper::getActiveFiscalYearId(),
                        'voucher_no' => $transaction->voucher_no,
                        'particulars' => "Meter Deposit Return {$transaction->voucher_no} ",
                        'status' => 2,
                        'reference_type' => 3,//meter deposit return
                        'reference_id' => $transaction->id,
                        'member_entry_id' => MeterIssue::getMemberByMeterIssueId($validated['meter_issue_id'])->id ?? null,
                        'lines' => $lines,
                    ]);
                }


                if ($transactionType == 3) {


                    if ($validated['charge_amount'] > 0) {
                        $lines[] = [
                            'account_head_id' => 17, // Upgrade Meter Capacity account
                            'particulars' => "Service Charge( {$transaction->voucher_no})",
                            'credit' => $validated['charge_amount'],
                        ];
                    }

                    if ($validated['deposit_amount'] > 0) {
                        $lines[] = [
                            'account_head_id' => 8, // Upgrade Meter Capacity account
                            'particulars' => "Upgrade Meter Capacity Charge( {$transaction->voucher_no})",
                            'credit' => $validated['deposit_amount'],
                        ];
                    }


                    if ($validated['payment_by_cash'] && $validated['cash_amount'] > 0) {
                        $lines[] = [
                            'account_head_id' => 1,
                            'debit' => $validated['cash_amount'],
                            'particulars' => "Cash Received (Upgrade Meter {$transaction->voucher_no} )",
                        ];
                    }


                    if ($validated['payment_by_bank'] && $validated['bank_amount'] > 0) {
                        $lines[] = [
                            'account_head_id' => $validated['bank_id'], 
                            'debit' => $validated['bank_amount'],
                            'particulars' => "Bank Received (Upgrade Meter {$transaction->voucher_no})",
                        ];
                    }
                   VoucherBalanceService::validate($lines);
                  $this->voucherService->create([
                        'date' => $validated['date_in_ad'],
                        'fiscal_year_id' => Helper::getActiveFiscalYearId(),
                        'voucher_no' => $transaction->voucher_no,
                        'particulars' => "Upgrade Meter {$transaction->voucher_no}",
                        'status' => 2,
                        'reference_type' => 2,//upgrade meter
                        'reference_id' => $transaction->id,
                        'member_entry_id' => MeterIssue::getMemberByMeterIssueId($validated['meter_issue_id'])->id ?? null,
                        'lines' => $lines,
                    ]);
                }


                // app(MeterDepositAccountingService::class)
                //     ->createVoucher($transaction);


                return response()->json([
                    'message' => 'Transaction created successfully',
                    'data' => $transaction
                ], 201);

            }, 5);

        } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Meter issue not found.'], 404);

    } catch (ValidationException $e) {
        $errors = $e->errors();
        return response()->json([
            'message' => collect($errors)->flatten()->first(),
            'errors' => $errors
        ], 422);

    } catch (\Exception $e) {

        if (str_contains($e->getMessage(), 'Voucher entry not balanced')) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'An error occurred while creating the meter deposit transaction',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function searchDeposits(Request $request)
    {
        return $this->searchCustomerByTransactionType($request, 1);
    }

    public function searchReturns(Request $request)
    {
        return $this->searchCustomerByTransactionType($request, 2);
    }

    public function searchUpgrade(Request $request)
    {

        return $this->searchCustomerByTransactionType($request, 3);
    }

    private function searchCustomerByTransactionType(Request $request, int $transactionType)
    {
        try {
            $searchTerm = $request->input('search');


            $meterIssues = MeterIssue::with('memberEntry')

                ->where('is_active', 1)
                ->where(function ($q) use ($searchTerm) {
                    $q->where('meter_no', 'like', "%{$searchTerm}%")
                        ->orWhereHas('memberEntry', function ($mq) use ($searchTerm) {
                            $mq->where('member_no', 'like', "%{$searchTerm}%")
                                ->orWhere('customer_name_en', 'like', "%{$searchTerm}%");
                        });
                })
                ->get();
            //dd($meterIssues);

            if ($meterIssues->isEmpty()) {
                return response()->json([
                    'message' => 'No meter records found.',
                    'data' => [],
                ], 200);
            }

            /** ---------------------------------
             * FILTER BASED ON TRANSACTION TYPE
             * --------------------------------- */
            if ($transactionType != 3) {
                $meterIssues = $meterIssues->filter(function ($meterIssue) use ($transactionType) {
                    return !$this->meterDepositValidationService
                        ->hasExistingTransaction($meterIssue->id, $transactionType);
                });

                if ($meterIssues->isEmpty()) {
                    return response()->json([
                        'message' => 'No meters available for this transaction type.',
                        'data' => [],
                    ], 200);
                }
            }

            /** ---------------------------------
             * BUILD RESPONSE
             * --------------------------------- */
            $customers = $meterIssues->map(function ($meterIssue) use ($transactionType) {

                $member = $meterIssue->memberEntry;

                $latestDeposit = MeterDepositTransaction::withoutTrashed()
                    ->where('meter_issue_id', $meterIssue->id)
                    ->whereIn('transaction_type', [1, 3, 4])
                    ->sum('amount');

                $result = [
                    'meter_issue_id' => $meterIssue->id,
                    'meter_no' => $meterIssue->meter_no,

                    'id' => $member?->id,
                    'member_no' => $member?->member_no,
                    'customer_name_en' => $member?->customer_name_en,
                    'customer_name_np' => $member?->customer_name_np,

                    'latest_deposit_amount' => $latestDeposit
                        ? round((float) $latestDeposit, 2)
                        : 0,
                ];

                if ($transactionType === 1) {
                    $result['existing_meter_deposit_amount'] = round(
                        $this->meterDepositValidationService
                            ->getExistingDepositAmount($meterIssue->id),
                        2
                    );
                }

                return $result;
            });

            return response()->json([
                'message' => 'Customer details retrieved successfully',
                'data' => $customers->values(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while searching customer details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





    public function indexDeposits(Request $request)
    {
        return $this->getTransactionsByType($request, 1);
    }

    public function indexReturns(Request $request)
    {
        return $this->getTransactionsByType($request, 2);
    }

    private function getTransactionsByType(Request $request, int $type)
    {
        $search = $request->query('search');

        $query = MeterDepositTransaction::where('transaction_type', $type)
            ->with([
                'meterIssue.memberEntry:id,member_no,customer_name_en,customer_name_np',
                'meterIssue:id,meter_no',
                'payments.bank:id,name,name_np'
            ]);


        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('meterIssue', function ($mq) use ($search) {
                    $mq->where('meter_no', 'like', "%{$search}%");
                })
                    ->orWhereHas('meterIssue.memberEntry', function ($mq) use ($search) {
                        $mq->where('member_no', 'like', "%{$search}%")
                            ->orWhere('customer_name_en', 'like', "%{$search}%")
                            ->orWhere('customer_name_np', 'like', "%{$search}%");
                    });
            });
        }


        $transactions = $query->paginate(10);


        $transactions->getCollection()->transform(function ($transaction) use ($type) {

            $memberInfo = $this->memberInfoService
                ->getMemberInfo($transaction->meter_issue_id);

            $paymentData = $this->paymentService
                ->transformPayments($transaction->payments);


            $data = [
                'id' => $transaction->id,
                'meter_issue_id' => $transaction->meter_issue_id,
                'voucher_no' => $transaction->voucher_no,
                'transaction_type' => $transaction->transaction_type,
                'date_in_bs' => $transaction->date_in_bs,
                'date_in_ad' => $transaction->date_in_ad,
                'deposit_amount' => $transaction->amount + ($transaction->service_charge ?? 0),
                'amount' => $transaction->amount,
                'service_charge' => $transaction->service_charge ?? '',

                'meter_no' => $memberInfo['meter_no'],
                'member_no' => $memberInfo['member_no'],
                'customer_name_en' => $memberInfo['customer_name_en'],
                'customer_name_np' => $memberInfo['customer_name_np'],
                // 'pan_no' => $memberInfo['pan_no'],
                // 'area_id' => $memberInfo['area_id'],

                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at,
                'deleted_at' => $transaction->deleted_at,
            ];

            if ($type === 1) {
                $data['service_charge'] = $transaction->service_charge;
            }

            return array_merge($data, $paymentData);
        });


        return response()->json($transactions);
    }

    public function showDeposit($id)
    {
        return $this->showTransactionByType($id, 1);
    }
    public function showReturn($id)
    {
        return $this->showTransactionByType($id, 2);
    }

    public function cancelReturn($id)
    {
        try {

            $transaction = MeterDepositTransaction::whereNull('deleted_at')
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

    public function cancelUpgrade($id)
    {
        try {

            $transaction = MeterDepositTransaction::whereNull('deleted_at')
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

    public function cancelDeposit($id)
    {
        try {

            $transaction = MeterDepositTransaction::whereNull('deleted_at')
                ->findOrFail($id);

            $transaction->update(['is_cancel' => 1]);

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


    public function showMeterUpgrade($id)
    {
        try {

            //$id = MemberEntry::findOrFail($id);
            //    $member = MemberEntry::where('id',$id)->first();
            $member = MemberEntry::with([
                'meterIssues' => function ($q) {
                    $q->where('is_active', '1')->limit(1);
                },
                'meterIssues.meterReadingEntries' => function ($q) {
                    $q->latest()->limit(1);
                }
            ])->where('id', $id)->first();
            $meterIssue = $member->meterIssues->first();
            $lastReading = $meterIssue?->meterReadingEntries->first();
            $capacityName = MasterSetup::find($meterIssue->capacity_id);
            $meterStartService = new KnowMeterStartService();
            $meterStartNo = $meterStartService->getMeterStartNo($meterIssue->id);


            return [
                'id' => $member->id,
                'member_no' => $member->member_no,
                'customer_name_en' => $member->customer_name_en,
                'capacity_name' => $capacityName->name_en ?? '',
                'capacity_id' => $meterIssue->capacity_id ?? '',
                'meter_no' => $meterIssue->meter_no ?? '',
                'meter_issue_id' => $meterIssue->id ?? '',
                'meter_start_no' => $meterStartNo, 
                'construct_company' => $meterIssue->construct_company ?? '',
                'reading_seal_no' => $meterIssue->reading_seal_no ?? '',
                'terminal_seal_no' => $meterIssue->terminal_seal_no ?? '',
                'meter_box_seal_no' => $meterIssue->meter_box_seal_no ?? '',
                'pole_no' => $meterIssue->pole_no ?? '',
                'pole_distance' => $meterIssue->pole_distance ?? '',
                'meter_no' => $meterIssue->meter_no ?? '',
            ];


            return response()->json([
                'message' => 'Meter deposit',
                'data' => '',
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


    public function getMeterDepositDetail($id)
    {
        try {


            //$id = MemberEntry::findOrFail($id);
            $member = MemberEntry::where('id', $id)->first();
            $meterIssue = MeterIssue::where('member_entry_id', $id)->first();
            $result = MeterDepositTransaction::where('meter_issue_id', $meterIssue->id)
                ->where('is_cancel', 0)
                ->selectRaw("
                SUM(
                CASE
                WHEN transaction_type IN (1, 3, 4) THEN amount
                WHEN transaction_type = 2 THEN -(amount + service_charge)
                ELSE 0
                END
                ) AS final_amount
                ")
                ->first();
            if ($result->final_amount < 0) {
                return response()->json([
                    'message' => 'No deposit Amount',
                    'data' => '',
                ], 200);
            }
            return [
                'id' => $member->id,
                'member_no' => $member->member_no,
                'amount' => $result->final_amount,
                'meter_issue_id' => $meterIssue->id ?? '',
                'meter_no' => $meterIssue->meter_no ?? '',
            ];


            return response()->json([
                'message' => 'Meter deposit',
                'data' => '',
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





    private function showTransactionByType($id, int $type)
    {
        try {
            $transaction = MeterDepositTransaction::with([
                'payments.bank'
            ])
                ->where('transaction_type', $type)
                ->findOrFail($id);


            $memberInfo = $this->memberInfoService
                ->getMemberInfo($transaction->meter_issue_id);

            $paymentData = $this->paymentService
                ->transformPayments($transaction->payments);

            $data = [
                'id' => $transaction->id,
                'meter_issue_id' => $transaction->meter_issue_id,
                'voucher_no' => $transaction->voucher_no,
                'transaction_type' => $transaction->transaction_type,

                'date_in_bs' => $transaction->date_in_bs,
                'date_in_ad' => $transaction->date_in_ad,
                'amount' => (float) $transaction->amount,

                'meter_no' => $memberInfo['meter_no'],
                'member_no' => $memberInfo['member_no'],
                'customer_name_en' => $memberInfo['customer_name_en'],
                'customer_name_np' => $memberInfo['customer_name_np'],

                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at,
                'deleted_at' => $transaction->deleted_at,
            ];

            if ($type === 1) {
                $data['service_charge'] = (float) $transaction->service_charge;
            }

            return response()->json([
                'message' => 'Meter deposit transaction retrieved successfully',
                'data' => array_merge($data, $paymentData),
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Transaction not found or not of the requested type',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the transaction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function importExcel(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('create deposit entries')) {
        //     return response()->json([
        //         'message' => 'Unauthorized',
        //         'errors' => ['permission' => ['User lacks create deposit entries permission']]
        //     ], 403);
        // }

        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
            ]);

            $import = new DepositEntryImport();

            DB::transaction(function () use ($import, $request) {
                Excel::import($import, $request->file('file'), null, Excel::XLSX);
            });

            $response = [
                'message' => 'Excel imported successfully',
                'errors' => [],
                'imported' => $import->importedCount,
                'failed_rows' => $import->failedRows,
            ];

            return response()->json($response, 200);

        } catch (ExcelValidationException $e) {
            $failures = $e->failures();
            $firstErrorMessage = count($failures) > 0 ? ($failures[0]->errors()[0] ?? 'Excel validation failed') : 'Excel validation failed';

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
