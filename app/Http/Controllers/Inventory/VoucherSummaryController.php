<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\MemberEntry;
use App\Models\MeterReadingEntry;
use App\Models\VoucherSummary;
use App\Models\VoucherSummaryDetail;
use App\Services\AdvanceUsedWithDueMahasulFindService;
use App\Services\MemberInfoFromMeterIssueService;
use App\Services\MemberResolverService;
use App\Services\MeterReadingFindService;
use App\Services\PaymentService;
use App\Services\VoucherPreviewService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Helpers\NepaliCalendar;
use Carbon\Carbon;
use App\Models\CustomerTransaction;
use Illuminate\Support\Facades\DB;
use App\Models\MeterDepositTransaction;
use App\Models\ShareTransaction;
use App\Models\AdvancePayment;
use App\Models\Fine;
use App\Models\MahasulReceiptEntry;
use App\Models\MeterInsurance;
use App\Models\MeterIssue;
use App\Models\NEAPaymentEntry;
use App\Models\NonMemberPayment;
use App\Models\OtherIncomeReceipt;
use App\Models\UpgradeMeterCapacity;
use App\Services\DateConverterService;

class VoucherSummaryController extends Controller
{


    public function index(Request $request): JsonResponse
    {
        try {

            $voucherType = [
                '1' => 'DEPOSIT_ENTRY',
                '2' => 'UPGRADE_METER',
                '3' => 'DEPOSIT_RETURN',
                '4' => 'ADVANCE_PAYMENT',
                '5' => 'METER_INSURANCE',
                '6' => 'OTHER_INCOME_RECEIPT',
                '7' => 'SHARE_ENTRY',
                '8' => 'SHARE_RETURN',
                '9' => 'NON_MEMBER_PAYMENT',
                '10' => 'NEA_PAYMENT',
                '11' => 'MAHASUL_RECEIPT',
                'ALL' => 'ALL',
            ];


            $type = $request->query('type', 'ALL');
            $from_date = $request->query('from_date');
            $to_date = $request->query('to_date');
            $voucherNumber = $request->query('voucher_number');
            $voucherTypeMap = array_flip($voucherType);
            $type = $voucherTypeMap[$type] ?? null;
            $perPage = $request->query('per_page', 50);

            $query = VoucherSummary::with([
                'voucherSummaryDetail.accountHead'

            ]);


            // date filter
            if ($from_date && $to_date) {
                $query->whereBetween('date', [$from_date, $to_date]);
            }

            // type filter
            if ($type !== 'ALL') {
                $query->where('reference_type', $type);
            }

            // voucher_number filter in related details
            if ($voucherNumber) {
                $query->where('voucher_no', $voucherNumber);
            }

            $voucherSummaries = $query
                ->orderBy('date', 'desc')
                ->paginate($perPage)
                ->through(function ($voucher) {
                    $adDate = $voucher->date->format('Y-m-d');
                    $bsDate = NepaliCalendar::adToBs($adDate);
                    return [
                        'id' => $voucher->id,
                        'voucher_no' => $voucher->voucher_no,
                        'date' => $voucher->date->format('Y-m-d'),
                        'date_np' => $bsDate,
                        'total_debit' => $voucher->voucherSummaryDetail->sum('debit'),
                        'total_credit' => $voucher->voucherSummaryDetail->sum('credit'),
                        'is_cancel' => $voucher->status == 3 ? 'Canceled' : '',
                        'details' => $voucher->voucherSummaryDetail->map(function ($detail) {

                            $debit = (float) $detail->debit;
                            $credit = (float) $detail->credit;

                            // If cancelled detail, make amounts negative
                            if ($detail->is_cancelled) {
                                $debit = $debit > 0 ? -$debit : $debit;
                                $credit = $credit > 0 ? -$credit : $credit;
                            }

                            return [
                                'account_head_id' => $detail->accountHead->name ?? null,
                                'particulars' => $detail->particulars,
                                'debit' => number_format($debit, 2, '.', ''),
                                'credit' => number_format($credit, 2, '.', ''),
                            ];
                        }),
                    ];
                });
            $totalQuery = VoucherSummaryDetail::join(
                'voucher_summaries',
                'voucher_summaries.id',
                '=',
                'voucher_summary_details.voucher_summary_id'
            );

            if ($from_date && $to_date) {
                $totalQuery->whereBetween('voucher_summaries.date', [$from_date, $to_date]);
            }

            if ($type !== 'ALL') {
                $totalQuery->where('voucher_summaries.reference_type', $type);
            }

            if ($voucherNumber) {
                $totalQuery->where('voucher_summaries.voucher_no', $voucherNumber);
            }

            $grandTotal = $totalQuery
                ->selectRaw('
                    SUM(voucher_summary_details.debit) as total_debit,
                    SUM(voucher_summary_details.credit) as total_credit
                ')
                ->first();
            return response()->json([
                'total' => [
                    'debit' => (float) $grandTotal->total_debit,
                    'credit' => (float) $grandTotal->total_credit,
                ],
                'vouchers' => $voucherSummaries,
            ]);

            // return response()->json($voucherSummaries, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Voucher Summary not found',
                'message' => $e->getMessage(),
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Database query failed',
                'message' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while fetching voucher summaries',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function ledgerList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|string|regex:/^\d{4}-\d{2}-\d{2}$/',
            'to_date' => 'required|string|regex:/^\d{4}-\d{2}-\d{2}$/',
            'account_head_id' => 'nullable|numeric',
            'account_group_id' => 'nullable|numeric',
            'payment_type' => 'nullable|string|in:cash,bank,DEPOSIT_RETURN,MAHASUL_RECEIPT,SHARE_ENTRY,SHARE_RETURN',
            'voucher_no' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $vouchers = VoucherSummary::selectRaw('
                    voucher_summaries.date_bs,
                    voucher_summaries.id,
                    voucher_summaries.date,
                    voucher_summaries.voucher_no,
                    voucher_summaries.particulars,
                    voucher_summaries.debit,
                    voucher_summaries.type,
                    voucher_summaries.payment_type,
                    voucher_summaries.credit
        ')
            ->leftJoin('voucher_summary_details as vsd', 'voucher_summaries.id', '=', 'vsd.voucher_summary_id')
            ->when($request->has('account_head_id'), function ($rr) use ($request) {
                $rr->where('vsd.account_head_id', $request->account_head_id);
            })
            ->when($request->has('account_group_id'), function ($rr) use ($request) {
                $rr->where('vsd.account_group_id', $request->account_group_id);
            })
            ->when($request->has('payment_type'), function ($rr) use ($request) {
                $rr->where('vsd.payment_type', strtoupper($request->payment_type));
            })
            ->when($request->has('voucher_no'), function ($rr) use ($request) {
                $rr->where('voucher_summaries.voucher_no', $request->voucher_no);
            })
            ->orderBy('voucher_summaries.date', 'desc')
            ->paginate(250);

        return response()->json($vouchers);
    }


    public function show($id): JsonResponse
    {
        try {
            $item = VoucherSummary::with([
                'accountHead:id,name',
                'accountGroup:id,name',
                'voucherSummaryDetail',
                'voucherSummaryInnerDetail'
            ])->findOrFail($id);
            return response()->json($item);
        } catch (ModelNotFoundException $e) {
            Log::error($e);
            return response()->json(['error' => 'Item not found'], 404);
        } catch (QueryException $e) {
            Log::error($e);
            return response()->json(['error' => 'An unexpected error occurred'], 500);
        }
    }
    public function getAllVoucherNumbers(Request $request): JsonResponse
    {
        try {
            $memberNo = $request->query('member_no');

            $query = VoucherSummary::query()
                ->select('id', 'voucher_no', 'date')
                ->whereNull('deleted_at'); // because SoftDeletes

            // Filter by member_no
            if ($memberNo) {
                $query->whereHas('voucherSummaryDetail.memberEntry', function ($q) use ($memberNo) {
                    $q->where('member_no', 'LIKE', '%' . $memberNo . '%');
                });
            }

            $vouchers = $query
                ->orderBy('date', 'desc')
                ->get()
                ->map(function ($voucher) {
                    return [
                        'id' => $voucher->id,
                        'voucher_no' => $voucher->voucher_no,
                    ];
                });

            return response()->json([
                'data' => $vouchers
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch voucher numbers',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getVoucherList(Request $request): JsonResponse
    {
        try {

            $voucherType = [
                '1' => 'DEPOSIT_ENTRY',
                '2' => 'UPGRADE_METER',
                '3' => 'DEPOSIT_RETURN',
                '4' => 'ADVANCE_PAYMENT',
                '5' => 'METER_INSURANCE',
                '6' => 'OTHER_INCOME_RECEIPT',
                '7' => 'SHARE_ENTRY',
                '8' => 'SHARE_RETURN',
                '9' => 'NON_MEMBER_PAYMENT',
                '10' => 'NEA_PAYMENT',
                '11' => 'MAHASUL_RECEIPT',
                'ALL' => 'ALL',
            ];


            $type = $request->query('type', 'ALL');
            $from_date = $request->query('from_date');
            $to_date = $request->query('to_date');
            $voucherNumber = $request->query('voucher_number');
            $voucherTypeMap = array_flip($voucherType);
            $type = $voucherTypeMap[$type] ?? null;
            $memberNo = $request->query('member_no');
            $perPage = $request->query('per_page', 50);

            $query = VoucherSummary::with([
                'voucherSummaryDetail.memberEntry'

            ]);

            if ($from_date && $to_date) {
                $query->whereBetween('date', [$from_date, $to_date]);
            }
            if ($type != 'ALL') {
                $query->where('reference_type', $type);
            }

            // voucher_number filter in related details
            if ($voucherNumber) {
                $query->where('voucher_no', $voucherNumber);
            }
            if ($memberNo) {
                $query->whereHas('voucherSummaryDetail.memberEntry', function ($q) use ($memberNo) {
                    $q->where('member_no', $memberNo);
                });
            }

            $voucherSummaries = $query
                ->orderBy('date', 'desc')
                ->paginate($perPage)
                ->through(function ($voucher) use ($voucherType) {
                    $adDate = $voucher->date->format('Y-m-d');
                    $bsDate = NepaliCalendar::adToBs($adDate);

                    $filteredDetails = $voucher->voucherSummaryDetail->whereIn('account_head_id', [1, 2]);

                    // Sum both debit and credit into a single amount
                    $amount = $filteredDetails->sum(function ($detail) {
                        return $detail->debit + $detail->credit;
                    });



                    $isCancelled = $voucher->status === 3 ? 1 : 0;

                    if ($isCancelled) {
                        $amount = $amount;
                    }
                    return [
                        'id' => $voucher->id,
                        'voucher_no' => $voucher->voucher_no,
                        'date' => $voucher->date->format('Y-m-d'),
                        'date_np' => $bsDate,
                        'member_no' => $voucher->voucherSummaryDetail
                            ->first()?->memberEntry?->member_no ?? '',
                        'customer_name' => $voucher->voucherSummaryDetail
                            ->first()?->memberEntry?->customer_name_en ?? '',
                        'amount' => $amount,
                        'voucher_type' => $voucherType[(string) $voucher->reference_type] ?? '',
                        'is_cancel' => $isCancelled,
                    ];
                });
            $totalQuery = VoucherSummaryDetail::join(
                'voucher_summaries',
                'voucher_summaries.id',
                '=',
                'voucher_summary_details.voucher_summary_id'
            );

            if ($from_date && $to_date) {
                $totalQuery->whereBetween('voucher_summaries.date', [$from_date, $to_date]);
            }

            if ($type !== 'ALL') {
                $totalQuery->where('voucher_summaries.reference_type', $type);
            }

            if ($voucherNumber) {
                $totalQuery->where('voucher_summaries.voucher_no', $voucherNumber);
            }

            return response()->json([
                'data' => $voucherSummaries,
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Voucher Summary not found',
                'message' => $e->getMessage(),
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'error' => 'Database query failed',
                'message' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred while fetching voucher summaries',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function getPreviewFromVoucher(
        $voucherId,
        VoucherPreviewService $service,
        MemberInfoFromMeterIssueService $memberInfoService,
        PaymentService $paymentService,
        DateConverterService $dateService
    ) {
        try {

            $data = $service->getPreviewFromVoucher(
                $voucherId,
                $memberInfoService,
                $paymentService,
                $dateService
            );

            return response()->json($data, 200);

        } catch (\Exception $e) {

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function geListWithAccountHead(Request $request)
    {
        $search = $request->query('search');
        $from_date = $request->query('from_date');
        $to_date = $request->query('to_date');
        $lang = $request->query('lang', 'eng');
        $perPage = $request->query('per_page', 50);

        $query = VoucherSummaryDetail::query()
            ->select(
                'account_head_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit')
            )
            ->join('voucher_summaries as vs', 'voucher_summary_details.voucher_summary_id', '=', 'vs.id')
            ->with('accountHead:id,name,name_np')
            ->where('vs.status', '!=', 3);

        // Date filter
        if ($from_date && $to_date) {
            $query->whereBetween('vs.date', [$from_date, $to_date]);
        }

        // Search filter
        if ($search) {
            $query->whereHas('accountHead', function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%');
            });
        }

        $results = $query
            ->groupBy('account_head_id')
            ->orderBy('account_head_id')
            ->paginate($perPage);

        $results->getCollection()->transform(function ($row) use ($lang) {

            $accountId = $row->account_head_id;
            $totalDebit = (float) $row->total_debit;
            $totalCredit = (float) $row->total_credit;

            $finalDebit = 0.0;
            $finalCredit = 0.0;

            /*
            |--------------------------------------------------------------------------
            | SPECIAL RULE → account_head_id = 1,2,22,23
            |--------------------------------------------------------------------------
            */
            if (in_array($accountId, [1, 2, 22, 23])) {

                if ($totalDebit > $totalCredit) {
                    $finalDebit = $totalDebit - $totalCredit;
                    $finalCredit = 0;
                } elseif ($totalCredit > $totalDebit) {
                    $finalCredit = $totalCredit - $totalDebit;
                    $finalDebit = 0;
                }

            }
            /*
            |--------------------------------------------------------------------------
            | OTHER ACCOUNT HEADS
            |--------------------------------------------------------------------------
            */ else {

                if ($totalCredit > $totalDebit) {
                    $finalCredit = $totalCredit - $totalDebit;
                    $finalDebit = 0;
                } elseif ($totalDebit > $totalCredit) {
                    $finalDebit = $totalDebit - $totalCredit;
                    $finalCredit = 0;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Formatting Rules
            |--------------------------------------------------------------------------
            */

            // For account_head_id 1 & 2 → wrap CREDIT in ()
            if (in_array($accountId, [1, 2]) && $finalCredit > 0) {
                $formattedCredit = '(' . $finalCredit . ')';
            } else {
                $formattedCredit = (string) $finalCredit;
            }

            // For all other accounts → wrap DEBIT in ()
            if (!in_array($accountId, [1, 2, 22, 23]) && $finalDebit > 0) {
                $formattedDebit = '(' . $finalDebit . ')';
            } else {
                $formattedDebit = (string) $finalDebit;
            }

            return [
                'account_head_id' => $accountId,
                'account_head' => $lang === 'nep'
                    ? $row->accountHead?->name_np
                    : $row->accountHead?->name,
                'total_debit' => $formattedDebit,
                'total_credit' => $formattedCredit,
            ];
        });

        return response()->json([
            'data' => $results
        ], 200);
    }

    public function getListWithMemberAndAccountFilter(Request $request)
    {
        $memberNo = $request->query('member_no');
        $accountHeadName = $request->query('account_head'); // filtering by name
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $lang = $request->query('lang', 'eng');
        $perPage = $request->query('per_page', 50);

        $query = VoucherSummaryDetail::query()
            ->select(
                'account_head_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit')
            )
            ->join('voucher_summaries as vs', 'voucher_summary_details.voucher_summary_id', '=', 'vs.id')
            ->with('accountHead:id,name,name_np')
            ->where('vs.status', '!=', 3)
            ->whereNotIn('vs.reference_type', [10, 12, 13, 14, 15]);

        if ($fromDate && $toDate) {
            $query->whereBetween('vs.date', [$fromDate, $toDate]);
        }
 
        if ($accountHeadName) {
            $query->whereHas('accountHead', function ($q) use ($accountHeadName) {
                $q->where(function ($sub) use ($accountHeadName) {
                    $sub->where('name', 'LIKE', '%' . $accountHeadName . '%')
                        ->orWhere('name_np', 'LIKE', '%' . $accountHeadName . '%');
                });
            });
        }

        if ($memberNo) {
            $query->whereHas('memberEntry', function ($q) use ($memberNo) {
                $q->where('member_no', 'LIKE', '%' . $memberNo . '%');
            });
        }

        $results = $query
            ->groupBy('account_head_id')
            ->orderBy('account_head_id')
            ->paginate($perPage);

        $results->getCollection()->transform(function ($row) use ($lang) {
            $accountId = $row->account_head_id;
            $totalDebit = (float) $row->total_debit;
            $totalCredit = (float) $row->total_credit;

            $finalDebit = 0.0;
            $finalCredit = 0.0;

            // SPECIAL RULE → account_head_id = 1,2,22,23
            if (in_array($accountId, [1, 2, 22, 23])) {
                if ($totalDebit > $totalCredit) {
                    $finalDebit = $totalDebit - $totalCredit;
                } elseif ($totalCredit > $totalDebit) {
                    $finalCredit = $totalCredit - $totalDebit;
                }
            }
            // OTHER ACCOUNT HEADS
            else {
                if ($totalCredit > $totalDebit) {
                    $finalCredit = $totalCredit - $totalDebit;
                } elseif ($totalDebit > $totalCredit) {
                    $finalDebit = $totalDebit - $totalCredit;
                }
            }

            if ($finalDebit == 0 && $finalCredit == 0) {
                return null;
            }

            // Formatting Rules
            if (in_array($accountId, [1, 2]) && $finalCredit > 0) {
                $formattedCredit = '(' . $finalCredit . ')';
            } else {
                $formattedCredit = (string) $finalCredit;
            }

            if (!in_array($accountId, [1, 2, 22, 23]) && $finalDebit > 0) {
                $formattedDebit = '(' . $finalDebit . ')';
            } else {
                $formattedDebit = (string) $finalDebit;
            }

            $amount = $finalDebit > 0 ? $formattedDebit : $formattedCredit;

            $accountName = $lang === 'nep' ? $row->accountHead?->name_np : $row->accountHead?->name;

            // For account_head_id 22 and 23 → keep as negative string
            if (in_array($accountId, [22, 23])) {
                $amount = '-' . (string) $amount;
            }

            return [
                'account_head_id' => $accountId,
                'account_head' => $accountName,
                'amount' => $amount,
            ];
        });

        // Remove null rows properly
        $results->setCollection(
            $results->getCollection()->reject(fn($row) => $row === null)->values()
        );

        return response()->json([
            'data' => $results
        ], 200);
    }


public function voucherCancel(Request $request, int $id)
{
    $request->validate([
        'reason' => 'required|string',
    ]);

    try {

        DB::connection('tenant')->transaction(function () use ($request, $id) {

            $original = VoucherSummary::with('voucherSummaryDetail')->findOrFail($id);

            if ($original->status === 3) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                'voucher' => ['This voucher is already cancelled.']
            ]);
            }

            /*
             STEP 1: VALIDATE FIRST (NO DB CHANGES YET)
            */

            if ($original->reference_type == 1 || $original->reference_type == 2 || $original->reference_type == 3) {

                $meterDeposit = MeterDepositTransaction::where('id', $original->reference_id)
                    ->where('is_cancel', 0)
                    ->first();

                if ($meterDeposit) {

                    $latestTransaction = MeterDepositTransaction::where('meter_issue_id', $meterDeposit->meter_issue_id)
                        ->where('is_cancel', 0)
                        ->whereNull('deleted_at')
                        ->orderByDesc('id')
                        ->first();

                    if (!$latestTransaction || $latestTransaction->id !== $meterDeposit->id) {
                       throw \Illuminate\Validation\ValidationException::withMessages([
                            'meter_issue_id' => ['You can only cancel the latest meter deposit transaction for this meter.']
                        ]);
                    }
                }
            }

            if ($original->reference_type == 7 || $original->reference_type == 8) {

                $shareTransaction = ShareTransaction::where('id', $original->reference_id)
                    ->where('is_cancel', 0)
                    ->first();

                if ($shareTransaction) {

                    $latestShareTransaction = ShareTransaction::where('member_entry_id', $shareTransaction->member_entry_id)
                        ->where('is_cancel', 0)
                        ->whereNull('deleted_at')
                        ->orderByDesc('id')
                        ->first();

                    if (!$latestShareTransaction || $latestShareTransaction->id !== $shareTransaction->id) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'member_entry_id' => ['You can only cancel the latest share transaction for this share.']
                        ]);
                    }
                }
            }
             if ($original->reference_type == 11) {
                $mahasulReceipt = MahasulReceiptEntry::find($original->reference_id);

                if ($mahasulReceipt) {
                    $latestReceipt = MahasulReceiptEntry::where('meter_issue_id', $mahasulReceipt->meter_issue_id)
                        ->where('is_cancel', 0)
                        ->whereNull('deleted_at')
                        ->orderByDesc('id')
                        ->first();

                    if (!$latestReceipt || $latestReceipt->id !== $mahasulReceipt->id) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'mahasul_receipt_id' => ['You can only cancel the latest Mahasul receipt for this meter.']
                        ]);
                    }
                }
            }

            // Validation for AdvancePayment: check if any Mahasul receipts exist after creation
            if ($original->reference_type == 4) {
                $advancePayment = AdvancePayment::where('type', 0)
                    ->find($original->reference_id);

                if ($advancePayment) {
                    $existingMahasul = MahasulReceiptEntry::where('meter_issue_id', $advancePayment->meter_issue_id)
                        ->where('created_at', '>', $advancePayment->created_at)
                        ->where('is_cancel', 0)
                        ->exists();

                    if ($existingMahasul) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'advance_payment' => ['Cannot cancel advance payment until all Mahasul receipts created after it are cancelled.']
                        ]);
                    }
                }
            }
                if ($original->reference_type == 10) {

                    $neaPayment = NEAPaymentEntry::where('id', $original->reference_id)
                        ->where('is_cancel', 0)
                        ->first();

                    if ($neaPayment) {

                        $latestNeaPayment = NEAPaymentEntry::where('transformer_id', $neaPayment->transformer_id)
                            ->where('is_cancel', 0)
                            ->whereNull('deleted_at')
                            ->orderByDesc('id')
                            ->first();

                        if (!$latestNeaPayment || $latestNeaPayment->id !== $neaPayment->id) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'transformer_id' => ['You can only cancel the latest NEA payment for this transformer.']
                            ]);
                        }
                    }
                }

            /*
             STEP 2: NOW EXECUTE (SAFE AFTER VALIDATION)
            */

            $original->update([
                'status' => 3,
                'reason' => $request->input('reason'),
            ]);

            foreach ($original->voucherSummaryDetail as $detail) {
                VoucherSummaryDetail::create([
                    'voucher_summary_id' => $original->id,
                    'particulars' => $detail->particulars,
                    'debit' => $detail->credit,
                    'credit' => $detail->debit,
                    'account_head_id' => $detail->account_head_id,
                    'member_entry_id' => $detail->member_entry_id,
                    'is_cancelled' => true,
                ]);
            }

            /*
             STEP 3: HANDLE REFERENCES
            */

            if ($original->reference_type == 1 || $original->reference_type == 2 || $original->reference_type == 3) {

                $meterDeposit = MeterDepositTransaction::where('id', $original->reference_id)
                    ->where('is_cancel', 0)
                    ->first();

                if ($meterDeposit) {

                    if ($meterDeposit->transaction_type == 3) {

                        $upgrade = UpgradeMeterCapacity::where('meter_issue_id', $meterDeposit->meter_issue_id)
                            ->where('voucher_no', $meterDeposit->voucher_no)
                            ->where('is_cancel', 0)
                            ->first();

                        if ($upgrade) {

                            $upgrade->update([
                                'is_cancel' => 1
                            ]);

                            MeterIssue::where('id', $meterDeposit->meter_issue_id)
                                ->update([
                                    'capacity_id' => $upgrade->existing_capacity_id
                                ]);
                        }
                    }

                    $meterDeposit->update([
                        'is_cancel' => 1,
                    ]);
                }
            }

            if ($original->reference_type == 4) {

                $advancePayment = AdvancePayment::where('type', 0)
                    ->find($original->reference_id);

                if ($advancePayment) {

                    $advancePayment->update([
                        'is_cancel' => 1,
                    ]);

                    CustomerTransaction::where('transaction_type', 6)
                        ->where('charge_type', 9)
                        ->where(
                            'member_entry_id',
                            MeterIssue::getMemberByMeterIssueId($advancePayment->meter_issue_id)->id ?? null
                        )
                        ->where('amount', $advancePayment->amount)
                        ->latest()
                        ->delete();
                }
            }

            if ($original->reference_type == 5) {
                MeterInsurance::where('id', $original->reference_id)
                    ->update(['is_cancel' => 1]);
            }

            if ($original->reference_type == 6) {
                OtherIncomeReceipt::where('id', $original->reference_id)
                    ->update(['is_cancel' => 1]);
            }

            if ($original->reference_type == 7 || $original->reference_type == 8) {
                ShareTransaction::where('id', $original->reference_id)
                    ->update(['is_cancel' => 1]);
            }

            if ($original->reference_type == 9) {
                NonMemberPayment::where('id', $original->reference_id)
                    ->update(['is_cancel' => 1]);
            }

            if ($original->reference_type == 10) {
                NEAPaymentEntry::where('id', $original->reference_id)
                    ->update(['is_cancel' => 1]);
            }

            if ($original->reference_type == 11) {

                CustomerTransaction::where('receipt_id', $original->reference_id)->delete();

                $mahasulReceipt = MahasulReceiptEntry::find($original->reference_id);

                if ($mahasulReceipt) {

                    $mahasulReceipt->update([
                        'is_cancel' => 1,
                    ]);

                    app(AdvanceUsedWithDueMahasulFindService::class)
                        ->cancelPreviousIfAdvanceStatusTwo(
                            $mahasulReceipt,
                            'Cancelled due to cancellation of subsequent Mahasul voucher'
                        );
                        if ($mahasulReceipt->total_due_amount > 0) {

                            Fine::where('meter_issue_id', $mahasulReceipt->meter_issue_id)
                                ->where('status', '!=', 2)
                                ->where('created_at', '>=', $mahasulReceipt->created_at)
                                ->get()
                                ->each(function ($fine) {
                                    $fine->update(['status' => 2]);
                                    $fine->delete();
                                });
                        }

                    
                       $memberEntryId = optional(
                            MeterIssue::getMemberByMeterIssueId($mahasulReceipt->meter_issue_id)
                        )->id;

                        if ($memberEntryId) {

                            CustomerTransaction::where('member_entry_id', $memberEntryId)
                                ->where('charge_type', 3)
                                ->where('reference_id', $mahasulReceipt->meter_reading_entry_id)
                                ->where('created_at', '>=', $mahasulReceipt->created_at)
                                ->delete();
                        }

                    $blacklistDetails = $original->voucherSummaryDetail
                        ->where('account_head_id', 20);

                    foreach ($blacklistDetails as $detail) {
                        if (!is_null($detail->member_entry_id)) {
                            MemberEntry::where('id', $detail->member_entry_id)
                                ->update(['is_blacklisted' => 1]);
                        }
                    }

                    $meterReadingService = new MeterReadingFindService();

                    $meterReadingIds = $meterReadingService
                        ->getMeterReadingIdsBetweenReceipts(
                            $mahasulReceipt->meter_issue_id,
                            $mahasulReceipt->id
                        );

                    MeterReadingEntry::whereIn('id', $meterReadingIds)
                        ->where('status', 2)
                        ->update(['status' => 1]);
                }
            }

        });

        return response()->json([
            'message' => 'Voucher Has Been Successfully Canceled',
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
    throw $e;
}

catch (\Exception $e) {
    return response()->json([
        'message' => 'Something went wrong while cancelling voucher.',
        'error' => $e->getMessage(), 
    ], 500);
}
}

public function getVoucherTypes(): JsonResponse
{
    try {

        $voucherTypes = [
            [
                'id' => 1,
                'name' => 'DEPOSIT_ENTRY',
            ],
            [
                'id' => 2,
                'name' => 'UPGRADE_METER',
            ],
            [
                'id' => 3,
                'name' => 'DEPOSIT_RETURN',
            ],
            [
                'id' => 4,
                'name' => 'ADVANCE_PAYMENT',
            ],
            [
                'id' => 5,
                'name' => 'METER_INSURANCE',
            ],
            [
                'id' => 6,
                'name' => 'OTHER_INCOME_RECEIPT',
            ],
            [
                'id' => 7,
                'name' => 'SHARE_ENTRY',
            ],
            [
                'id' => 8,
                'name' => 'SHARE_RETURN',
            ],
            [
                'id' => 9,
                'name' => 'NON_MEMBER_PAYMENT',
            ],
            [
                'id' => 10,
                'name' => 'NEA_PAYMENT',
            ],
            [
                'id' => 11,
                'name' => 'MAHASUL_RECEIPT',
            ],
        ];

        return response()->json([
            'data' => $voucherTypes
        ], 200);

    } catch (\Exception $e) {

        return response()->json([
            'error' => 'An error occurred while fetching voucher types',
            'message' => $e->getMessage(),
        ], 500);
    }
}

}