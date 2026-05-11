<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\MemberEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\VoucherSummary;
use App\Models\Payment;
use App\Models\OtherIncomeReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IncomeHeadReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $from_date = $request->query('from_date');
            $to_date = $request->query('to_date');



            $query = DB::connection('tenant')
                ->table('voucher_summary_details as vsd')
                ->join('account_heads as ah', 'vsd.account_head_id', '=', 'ah.id')
                ->select(
                    'ah.id as account_head_id',
                    'ah.name as account_head_name',
                    'ah.name_np as account_head_name_np',
                    DB::raw('COALESCE(SUM(vsd.debit), 0) as total_debit'),
                    DB::raw('COALESCE(SUM(vsd.credit), 0) as total_credit'),
                    DB::raw('(COALESCE(SUM(vsd.debit), 0) + COALESCE(SUM(vsd.credit), 0)) as total_amount')
                )
                ->whereNull('vsd.deleted_at')
                ->groupBy('ah.id', 'ah.name', 'ah.name_np');

            // Date filter
            if ($from_date && $to_date) {
                $query->whereBetween('vsd.date', [$from_date, $to_date]);
            }

            $results = $query->get();

            return response()->json([
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Failed to fetch income head report',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function get(Request $request): JsonResponse
    {
        try {
            $query = DB::connection('tenant')
                ->table('voucher_summary_details as vsd')
                ->join('account_heads as ah', 'vsd.account_head_id', '=', 'ah.id')
                ->select(
                    'ah.id as account_head_id',
                    'ah.name as account_head_name',
                    'ah.name_np as account_head_name_np',
                )
                ->whereNull('vsd.deleted_at')
                ->distinct()
                ->orderBy('ah.id', 'asc');

            $results = $query->get();

            return response()->json([
                'data' => $results,
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'error' => 'Failed to fetch account heads',
                'message' => $e->getMessage(),
            ], 500);
        }
    }





    // public function getOtherIncomeReceiptReport(Request $request): JsonResponse
    // {
    //     try {
    //         $validated = $request->validate([
    //             'from_date' => 'required|date',
    //             'to_date' => 'required|date',
    //         ]);

    //         $fromDate = $validated['from_date'];
    //         $toDate = $validated['to_date'];

    //         $summaries = VoucherSummary::with([
    //             'voucherSummaryDetail.accountHead',
    //             'otherIncomeReceipt:id,voucher_no,customer_id',
    //             'depositEntry:id,voucher_no,customer_id',
    //             'depositReturn:id,voucher_no,customer_id',
    //             'mahsulReceiptEntry:id,voucher_no,customer_id',
    //             'advanceEntry:id,voucher_no,customer_id',
    //             'meterInsurance:id,voucher_no,customer_id',
    //             'neaPayment:id,voucher_no',
    //             'shareEntry:id,voucher_no,customer_id',
    //             'shareReturn:id,voucher_no,customer_id',
    //         ])
    //             ->whereBetween('date', [$fromDate, $toDate])
    //             ->whereIn('type', [
    //                 'OTHER_INCOME_RECEIPT',
    //                 'DEPOSIT_ENTRY',
    //                 'DEPOSIT_RETURN',
    //                 'MAHASUL_RECEIPT',
    //                 'ADVANCE_PAYMENT',
    //                 'METER_INSURANCE',
    //                 'NEA_PAYMENT',
    //                 'SHARE_ENTRY',
    //                 'SHARE_RETURN'
    //             ])
    //             ->get();

    //         $report = $summaries->map(function ($summary) {

    //             $voucherNo = $summary->voucher_number
    //                 ?? optional($summary->voucherSummaryDetail->first())->voucher_number
    //                 ?? 'N/A';

    //             $accounts = $summary->voucherSummaryDetail
    //                 ->map(function ($detail) {
    //                     return [
    //                         'account_head_id' => $detail->account_head_id,
    //                         'account_head_name' => optional($detail->accountHead)->name ?? 'N/A',
    //                         'account_head_name_np' => optional($detail->accountHead)->name_np ?? 'N/A',
    //                         'amount' => $detail->debit > 0 ? $detail->debit : $detail->credit,
    //                         'type' => $detail->debit > 0 ? 'debit' : 'credit',
    //                         'payment_type' => $detail->payment_type ?? 'N/A',
    //                     ];
    //                 })
    //                 ->filter(fn($account) => in_array($account['payment_type'], ['CASH', 'BANK', 'INCOME_HEAD']))
    //                 ->values();

    //             $paymentTypes = $accounts->whereIn('payment_type', ['CASH', 'BANK'])
    //                 ->pluck('payment_type')
    //                 ->unique();
    //             $paymentType = $paymentTypes->count() === 2 ? 'BOTH' : ($paymentTypes->first() ?? 'N/A');

    //             $totalAmount = $accounts->whereIn('payment_type', ['CASH', 'BANK'])
    //                 ->where('type', 'debit')
    //                 ->sum('amount');

    //             $rebateAmount = $summary->voucherSummaryDetail
    //                 ->filter(fn($detail) => optional($detail->accountHead)->name === 'Rebate Amount')
    //                 ->sum(fn($detail) => $detail->debit > 0 ? $detail->debit : $detail->credit);

    //             $discountAmount = $summary->voucherSummaryDetail
    //                 ->filter(fn($detail) => optional($detail->accountHead)->name === 'Discount Expenses')
    //                 ->sum(fn($detail) => $detail->debit > 0 ? $detail->debit : $detail->credit);

    //             $fineAmount = $summary->voucherSummaryDetail
    //                 ->filter(fn($detail) => optional($detail->accountHead)->name === 'Fine Amount')
    //                 ->sum(fn($detail) => $detail->debit > 0 ? $detail->debit : $detail->credit);

    //             $itemTotal = $totalAmount - $rebateAmount - $discountAmount + $fineAmount;

    //             $receipt = match ($summary->type) {
    //                 'OTHER_INCOME_RECEIPT' => $summary->otherIncomeReceipt,
    //                 'DEPOSIT_ENTRY' => $summary->depositEntry,
    //                 'DEPOSIT_RETURN' => $summary->depositReturn,
    //                 'MAHASUL_RECEIPT' => $summary->mahsulReceiptEntry,
    //                 'ADVANCE_PAYMENT' => $summary->advanceEntry,
    //                 'METER_INSURANCE' => $summary->meterInsurance,
    //                 'NEA_PAYMENT' => $summary->neaPayment,
    //                 'SHARE_ENTRY' => $summary->shareEntry,
    //                 'SHARE_RETURN' => $summary->shareReturn,
    //                 default => null,
    //             };

    //             $customer = $receipt && isset($receipt->customer_id)
    //                 ? MemberEntry::where('member_entry_id', $receipt->customer_id)
    //                     ->where('is_active', 1)
    //                     ->whereNull('deleted_at')
    //                     ->first()
    //                 : null;

    //             $customerId = $customer?->customer_id;
    //             $customerName = $customer
    //                 ? ($customer->customer_name_en ?? $customer->customer_name_np ?? $customer->customer_id)
    //                 : null;

    //             return [
    //                 'date' => $summary->date ?? now()->toDateString(),
    //                 'voucher_no' => $voucherNo,
    //                 'member_entry_id' => $customerId,
    //                 'customer_name' => $customerName,
    //                 'type' => $summary->type,
    //                 'payment_type' => $paymentType,
    //                 'rebate_amount' => $rebateAmount,
    //                 'discount_amount' => $discountAmount,
    //                 'fine_amount' => $fineAmount,
    //                 'total_amount' => $totalAmount,
    //                 'item_total' => $itemTotal,
    //             ];
    //         })
    //             ->filter(fn($r) => $r['member_entry_id'] !== null) // remove records with no valid customer
    //             ->values();

    //         return response()->json([
    //             'success' => true,
    //             'data' => $report,
    //         ]);

    //     } catch (\Exception $e) {
          
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error generating report',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
public function getOtherIncomeReceiptReport(Request $request): JsonResponse
{
    try {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date',
        ]);

        $fromDate = $validated['from_date'];
        $toDate   = $validated['to_date'];

        // Map reference_type integers from migration comment:
        // 1=meter_deposit, 2=upgrade_meter, 3=meter_deposit_return,
        // 4=advance_payment, 5=meter_insurance, 6=other_income_receipt,
        // 7=share_entry, 8=share_return, 9=non_member_payment,
        // 10=nea_payment, 11=mahasul_receipt
        $referenceTypeMap = [
            6  => 'OTHER_INCOME_RECEIPT',
            1  => 'DEPOSIT_ENTRY',
            3  => 'DEPOSIT_RETURN',
            11 => 'MAHASUL_RECEIPT',
            4  => 'ADVANCE_PAYMENT',
            5  => 'METER_INSURANCE',
            10 => 'NEA_PAYMENT',
            7  => 'SHARE_ENTRY',
            8  => 'SHARE_RETURN',
        ];

        $summaries = VoucherSummary::with([
            'voucherSummaryDetails.accountHead', // note: likely plural per Laravel convention
        ])
            ->whereBetween('date', [$fromDate, $toDate])
            ->whereIn('reference_type', array_keys($referenceTypeMap))
            ->where('status', 2) // 2=posted only
            ->get();

        $report = $summaries->map(function ($summary) use ($referenceTypeMap) {

            $typeLabel = $referenceTypeMap[$summary->reference_type] ?? 'UNKNOWN';

            $accounts = $summary->voucherSummaryDetails
                ->map(function ($detail) {
                    return [
                        'account_head_id'      => $detail->account_head_id,
                        'account_head_name'    => optional($detail->accountHead)->name    ?? 'N/A',
                        'account_head_name_np' => optional($detail->accountHead)->name_np ?? 'N/A',
                        'amount'               => $detail->debit > 0 ? $detail->debit : $detail->credit,
                       // 'type'                 => $detail->debit > 0 ? 'debit' : 'credit',
                    ];
                });

            // Total = sum of debit side (cash/bank accounts)
            // Since payment info is in the payments table, sum all debits from details
            $totalDebit  = $summary->voucherSummaryDetails->sum('debit');
            $totalCredit = $summary->voucherSummaryDetails->sum('credit');

            $rebateAmount = $summary->voucherSummaryDetails
                ->filter(fn($d) => optional($d->accountHead)->name === 'Rebate Amount')
                ->sum(fn($d) => $d->debit > 0 ? $d->debit : $d->credit);

            $discountAmount = $summary->voucherSummaryDetails
                ->filter(fn($d) => optional($d->accountHead)->name === 'Discount Expenses')
                ->sum(fn($d) => $d->debit > 0 ? $d->debit : $d->credit);

            $fineAmount = $summary->voucherSummaryDetails
                ->filter(fn($d) => optional($d->accountHead)->name === 'Fine Amount')
                ->sum(fn($d) => $d->debit > 0 ? $d->debit : $d->credit);

            // Get payment info from payments table using reference_type + reference_id
            $payment = \App\Models\Payment::where('reference_id', $summary->reference_id)
                ->where('type', $summary->reference_type)
                ->whereNull('deleted_at')
                ->get();

            $paymentModes = $payment->pluck('payment_mode')->unique();
            // 1=cash, 2=bank
            $paymentType = $paymentModes->count() === 2
                ? 'BOTH'
                : match ($paymentModes->first()) {
                    1       => 'CASH',
                    2       => 'BANK',
                    default => 'N/A',
                };

            $totalAmount = $payment->sum('amount');
            $itemTotal   = $totalAmount - $rebateAmount - $discountAmount + $fineAmount;

            // Get customer from member_entry_id on voucher_summary_details
            $memberEntryId = $summary->voucherSummaryDetails
                ->whereNotNull('member_entry_id')
                ->first()
                ?->member_entry_id;

            $customer = $memberEntryId
                ? MemberEntry::where('member_entry_id', $memberEntryId)
                    ->where('is_active', 1)
                    ->whereNull('deleted_at')
                    ->first()
                : null;

            if (!$customer) {
                return null; // filtered out below
            }

            return [
                'date'            => $summary->date,
                'voucher_no'      => $summary->voucher_no,
                'member_entry_id' => $customer->customer_id,
                'customer_name'   => $customer->customer_name_en
                                  ?? $customer->customer_name_np
                                  ?? $customer->customer_id,
                'type'            => $typeLabel,
                'payment_type'    => $paymentType,
                'rebate_amount'   => $rebateAmount,
                'discount_amount' => $discountAmount,
                'fine_amount'     => $fineAmount,
                'total_amount'    => $totalAmount,
                'item_total'      => $itemTotal,
            ];
        })
            ->filter() // removes nulls (no customer found)
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $report,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error generating report',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

}
