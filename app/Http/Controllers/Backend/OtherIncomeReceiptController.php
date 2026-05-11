<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\NepaliCalendar;
use App\Http\Controllers\Controller;
use App\Http\Requests\OtherIncomeReceipt\StoreRequest;
use App\Models\OtherIncomeReceipt;
use App\Models\AccountHead;
use App\Models\MeterIssue;
use App\Models\OtherIncomeReceiptContent;

use App\Services\Accounting\OtherIncomeReceiptAccountingService;
use App\Services\MemberInfoFromMeterIssueService;
use App\Services\PaymentService;
use App\Services\VoucherEntryService;
use Doctrine\DBAL\Query\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\MemberEntry;

use Illuminate\Support\Facades\Auth;
use App\Helpers\Helper;
use App\Services\VoucherBalanceService;
use Illuminate\Support\Facades\Log;

class OtherIncomeReceiptController extends Controller
{
    // protected MemberInfoFromMeterIssueService $memberInfoService;

    // protected PaymentService $paymentService;

    public function __construct(protected PaymentService $paymentService, protected MemberInfoFromMeterIssueService $memberInfoService,protected VoucherEntryService $voucherService)
    {
        $this->paymentService = $paymentService;
        $this->memberInfoService = $memberInfoService;
    }



    public function store(StoreRequest $request)
    {
        


        $connection = (new OtherIncomeReceipt())->getConnectionName() ?: config('database.default');

        try {
            $response = DB::connection($connection)->transaction(function () use ($request) {
                $validated = $request->validated();
                $receipt = OtherIncomeReceipt::create([
                    'date_in_bs' => $validated['date_in_bs'],
                    'date_in_ad' => $validated['date_in_ad'],
                    'voucher_no' => $validated['voucher_no'],
                    'meter_issue_id' => $validated['meter_issue_id'],
                    'amount' => number_format($validated['amount'], 4, '.', ''),
                ]);
                $lines = [];
                foreach ($validated['income_heads'] as $incomeHead) {

                    $other_income_receipt = OtherIncomeReceiptContent::create([
                        'other_income_receipt_id' => $receipt->id,
                        'account_head_id' => $incomeHead['id'],

                        'amount' => number_format((float) $incomeHead['amount'], 4, '.', ''),
                    ]);
                    $lines[] = [
                        'account_head_id' => $incomeHead['id'],
                        'particulars'     => "Other Income {$receipt->voucher_no})",
                        'credit'          => $other_income_receipt->amount,
                    ];


                }
                if ($validated['cash_amount'] > 0) {
                    $lines[] = [
                        'account_head_id' => 1, 
                        'debit'           => $validated['cash_amount'],
                        'particulars'     => "Cash Received (Other Income {$receipt->voucher_no})",
                    ];
                }

                if ($validated['bank_amount'] > 0) {
                    $lines[] = [
                        'account_head_id' => $validated['bank_id'], 
                        'debit'           => $validated['bank_amount'],
                        'particulars'     => "Bank Received (Other Income {$receipt->voucher_no})",
                    ];
                }      
                          
                VoucherBalanceService::validate($lines);
                $voucher = $this->voucherService->create([
                    'date'           => $validated['date_in_ad'],
                    'fiscal_year_id' => Helper::getActiveFiscalYearId(),
                    'voucher_no' => $receipt->voucher_no,
                    'particulars'    => "Other Income {$receipt->voucher_no}",
                    'status'         => 2,
                    'reference_type' => 6,//other income
                    'reference_id'   => $receipt->id,
                    'member_entry_id' => MeterIssue::getMemberByMeterIssueId($validated['meter_issue_id'])->id ?? null,
                    'lines'          => $lines,
                ]);                
                // app(OtherIncomeReceiptAccountingService::class)
                //     ->createVoucher($receipt);

                $this->paymentService->createPayments($receipt->id, [
                    'cash_amount' => $validated['cash_amount'] ?? 0,
                    'bank_amount' => $validated['bank_amount'] ?? 0,
                    'cheque_no' => $validated['cheque_no'] ?? null,
                    'bank_id' => $validated['bank_id'] ?? null,
                ], 6);



                return [
                    'message' => 'Other income receipt created successfully',
                    'data' => $receipt->toArray()
                ];
            });

            return response()->json($response, 201);

        } catch (\Exception $e) {
             if (str_contains($e->getMessage(), 'Voucher entry not balanced')) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }

            return response()->json([
                'message' => 'An error occurred while creating the other income receipt',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function listOtherIncomeSetups(Request $request)
    {
        // if (!$request->user()->hasOrganizationPermission('view other income receipts')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $charges = AccountHead::withoutTrashed()

                ->get()
                ->map(function ($charge) {
                    return [
                        'id' => $charge->id,
                        'name' => $charge->name,
                        'name_np' => $charge->name_np,
                        'amount' => $charge->amount,
                        'is_active' => $charge->is_active,
                    ];
                });


            return response()->json([
                'message' => 'Other charge setups retrieved successfully !',
                'data' => $charges,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving other charge setups',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function listOtherIncomeReceiptContent(Request $request, $meter_issue_id)
    {
        // if (!$request->user()->hasOrganizationPermission('view other income receipts')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $validator = validator(['meter_issue_id' => $meter_issue_id], [
                'meter_issue_id' => [
                    'required',
                    'integer',
                    'exists:tenant.other_income_receipts,meter_issue_id,deleted_at,NULL',
                ],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $incomeHeadsContent = OtherIncomeReceiptContent::withoutTrashed()
                ->join('other_income_receipts', function ($join) {
                    $join->on('other_income_receipt_contents.other_income_receipt_id', '=', 'other_income_receipts.id')
                        ->whereNull('other_income_receipts.deleted_at');
                })
                ->leftJoin('account_heads', 'other_income_receipt_contents.account_head_id', '=', 'account_heads.id')
                ->where('other_income_receipts.meter_issue_id', $meter_issue_id)
                ->select(
                    'other_income_receipt_contents.other_income_receipt_id',
                    'other_income_receipt_contents.account_head_id',
                    'account_heads.name',
                    'account_heads.name_np',

                    'other_income_receipt_contents.amount'
                )
                ->get();

            return response()->json([
                'message' => $incomeHeadsContent->isEmpty() ? 'No active, non-deleted income heads content found.' : 'Income heads content retrieved successfully',
                'data' => $incomeHeadsContent,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving income heads content',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function searchCustomerDetails(Request $request, MemberInfoFromMeterIssueService $memberInfoService)
    {
        try {
            $searchTerm = $request->input('search');          

            $customers = MemberEntry::has('meterIssues')
                ->with(['meterIssues'])              
                ->where('is_active', 1)
                ->where(function ($query) use ($searchTerm) {
                    $query->where('member_no','like',"%{$searchTerm}%")
                          ->orWhere('customer_name_en', 'like', "%{$searchTerm}%");
                })
                ->get()
                ->map(function ($customer) {
                    return [
                        'id'   => $customer->id,
                        'customer_name_en' => $customer->customer_name_en,
                        'member_no' => $customer->member_no, 
                        'pan_no' => $customer->pan_no, 
                        'meter_no' => $customer->meterIssues->first()->meter_no ?? '', 
                        'meter_issue_id' => $customer->meterIssues->first()->id ?? '', 
                    ];
                });
                
            return response()->json([
                'message' => 'Customer Lists !',
                'data' => $customers,
            ], 200);

        }catch (ModelNotFoundException $e) {    
            return response()->json([
                'message' => 'No active member found matching your search criteria.',
                'error' => 'Member Not Found'
            ], 404);
        
        } 
        catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while searching customer details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function getById(Request $request, $id)
    {
        

        try {
            // Load only necessary relations
            $entry = OtherIncomeReceipt::withoutTrashed()
                ->with([
                    'incomeHeadsContent.accountHead:id,name,name_np',
                    'bank:id,name_en,name_np',
                    'payments'
                ])
                ->findOrFail($id);

            // Fetch meter member info safely
            $meterMemberInfo = $this->memberInfoService->getMemberInfo($entry->meter_issue_id);

            // If $meterMemberInfo is numeric array, flatten it
            if (is_array($meterMemberInfo) && array_key_exists(0, $meterMemberInfo)) {
                $meterMemberInfo = $meterMemberInfo[0];
            }

            // Null-safe defaults
            $meterMemberInfo = $meterMemberInfo ?? [];           
            $paymentData = $this->paymentService->transformPayments($entry->payments ?? []);
          

            // Format income heads safely
            $incomeHeads = $entry->incomeHeadsContent->map(function ($head) {
                $accountHead = $head->accountHead;

                return [
                    'id' => $head->id,
                    'other_income_receipt_id' => $head->other_income_receipt_id,
                    'account_head_id' => $head->account_head_id,
                    'name' => $accountHead?->name ?? null,
                    'name_np' => $accountHead?->name_np ?? null,
                    'amount' => $head->amount,
                    'created_at' => $head->created_at?->toDateTimeString(),
                    'updated_at' => $head->updated_at?->toDateTimeString(),
                    'deleted_at' => $head->deleted_at?->toDateTimeString(),
                ];
            })->toArray();

            // Merge main entry + payments safely using "+" operator
            $data = [
                'id' => $entry->id,
                'date_in_bs' => $entry->date_in_bs,
                'date_in_ad' => $entry->date_in_ad,
                'voucher_no' => $entry->voucher_no,

                'meter_issue_id' => $entry->meter_issue_id,
                'meter_no' => $meterMemberInfo['meter_no'] ?? null,
                'member_no' => $meterMemberInfo['member_no'] ?? null,
                'customer_name_en' => $meterMemberInfo['customer_name_en'] ?? null,
                'customer_name_np' => $meterMemberInfo['customer_name_np'] ?? null,
                'pan_no' => $meterMemberInfo['pan_no'] ?? null,

                'payment_by_cash' => $entry->payment_by_cash,
                'payment_by_bank' => $entry->payment_by_bank,
                'cheque_no' => $entry->cheque_no,

                'bank_id' => $entry->bank_id,
                'bank_name_en' => optional($entry->bank)->name_en,
                'bank_name_np' => optional($entry->bank)->name_np,

                'amount' => $entry->amount,
                'cash_amount' => $entry->cash_amount,
                'bank_amount' => $entry->bank_amount,
                'is_cancel' => $entry->is_cancel,

                'created_at' => $entry->created_at?->toDateTimeString(),
                'updated_at' => $entry->updated_at?->toDateTimeString(),

                'income_heads_content' => $incomeHeads,
            ] + $paymentData; // "+" preserves string keys safely

            return response()->json([
                'message' => 'Other Income Receipt retrieved successfully',
                'other_income' => $data
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Other Income Receipt entry not found or already deleted'
            ], 404);

        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database query error occurred while fetching the entry',
                'error' => $e->getMessage()
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }







    public function listOtherIncomeReceipts(
        Request $request,
        MemberInfoFromMeterIssueService $memberInfoService
    ) {
        // if (!$request->user()->hasOrganizationPermission('view other income receipts')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        try {
            $query = OtherIncomeReceipt::withoutTrashed()
                ->with([
                    'incomeHeadsContent.accountHead:id,name,name_np',
                    'bank:id,name_en,name_np',
                    'payments'
                ]);

            if ($request->filled('search')) {
                $search = $request->input('search');

                $query->where(function ($q) use ($search) {

                    // Search in voucher_no
                    $q->where('voucher_no', 'like', "%{$search}%")

                        ->orWhereHas('memberEntry', function ($sub) use ($search) {
                            $sub->where('member_no', 'like', "%{$search}%")
                                ->orWhere('customer_name_en', 'like', "%{$search}%")
                                ->orWhere('customer_name_np', 'like', "%{$search}%");
                        });
                });
            }


            $entries = $query->paginate(10);

            $entries->getCollection()->transform(function ($entry) use ($memberInfoService) {

                $member = $memberInfoService->getMemberInfo($entry->meter_issue_id);

                $paymentData = $this->paymentService->transformPayments($entry->payments ?? []);

                return array_merge([
                    'id' => $entry->id,
                    'date_in_bs' => $entry->date_in_bs,
                    'date_in_ad' => $entry->date_in_ad,
                    'voucher_no' => $entry->voucher_no,
                    'meter_issue_id' => $entry->meter_issue_id,
                    'member_no' => $member['member_no'],
                    'customer_name_en' => $member['customer_name_en'],
                    'customer_name_np' => $member['customer_name_np'],
                    'pan_no' => $member['pan_no'],
                    'meter_no' => $member['meter_no'],
                    'payment_by_cash' => $entry->payment_by_cash,
                    'payment_by_bank' => $entry->payment_by_bank,
                    'cheque_no' => $entry->cheque_no,
                    'bank_id' => $entry->bank_id,
                    'bank_name_en' => optional($entry->bank)->name_en,
                    'bank_name_np' => optional($entry->bank)->name_np,

                    'amount' => $entry->amount,
                    'cash_amount' => $entry->cash_amount,
                    'bank_amount' => $entry->bank_amount,
                    'is_cancel' => $entry->is_cancel,
                    'created_at' => $entry->created_at,
                    'updated_at' => $entry->updated_at,

                    // Income head content list
                    'income_heads_content' => $entry->incomeHeadsContent->map(function ($head) {
                        return [
                            'id' => $head->id,
                            'other_income_receipt_id' => $head->other_income_receipt_id,
                            'other_income_setup_id' => $head->other_income_setup_id,
                            'income_head_en' => optional($head->otherIncomeSetup)->income_head_en,
                            'income_head_np' => optional($head->otherIncomeSetup)->income_head_np,
                            'charge_amount' => $head->charge_amount,
                            'amount' => $head->amount,
                            'created_at' => $head->created_at,
                            'updated_at' => $head->updated_at,
                            'deleted_at' => $head->deleted_at,
                        ];
                    }),

                ], $paymentData);
            });

            return response()->json($entries);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving other income receipts !',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function deleteOtherIncomeReceipt(Request $request, $id)
    {
        // if (!$request->user()->hasOrganizationPermission('delete other income receipts')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        $connection = (new OtherIncomeReceipt)->getConnectionName() ?: config('database.default');

        try {
            $response = DB::connection($connection)->transaction(function () use ($id) {

                $receipt = OtherIncomeReceipt::withoutTrashed()
                    ->findOrFail($id);
                $receipt->payments()->delete();
                $receipt->delete();

                OtherIncomeReceiptContent::where('other_income_receipt_id', $receipt->id)->delete();

             
                return response()->json([
                    'message' => 'Other income receipts entry deleted successfully',
                    'id' => $id,
                ], 200);
            });

            return $response;
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Other income receipts entry not found',
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'An error occurred while deleting the other income receipts entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function cancelReceipt(Request $request, $id)
    {
        try {
            $isCancel = $request->input('is_cancel');

            if(!is_bool($isCancel)) {
                throw ValidationException::withMessages([
                    'is_cancel' => 'The is_cancel field must be a boolean.'
                ]);
            }

            if($isCancel === true){
                $receipt = OtherIncomeReceipt::withoutTrashed()
                    ->findOrFail($id);

                $receipt->is_cancel = true;
                $receipt->update();

               
                return response()->json([
                    'message' => 'Other income receipt cancelled successfully',
                    'id' => $id,
                ], 200);
            } else {
                return response()->json([
                    'message' => 'is_cancel must be true to cancel the receipt',
                ], 400);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Other income receipt entry not found',
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database query error occurred while cancelling the entry',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while cancelling the entry',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
