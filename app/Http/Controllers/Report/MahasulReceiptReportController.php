<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\AccountHead;
use App\Models\AdvancePayment;
use App\Models\MahasulReceiptEntry;
use App\Models\MeterDepositTransaction;
use App\Models\MeterInsurance;
use App\Models\NEAPaymentEntry;
use App\Models\NonMemberPayment;
use App\Models\OtherIncomeReceipt;
use App\Models\ShareTransaction;
use App\Models\VoucherSummary;
use App\Models\VoucherSummaryDetail;

use Doctrine\DBAL\Query\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

use Illuminate\Http\Request;

class MahasulReceiptReportController extends Controller
{
      public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'date_from' => ['required', 'date_format:Y-m-d'],
                'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
                'reference_types' => 'nullable|array',
                'reference_types.*' => 'integer|in:1,2,3,4,5,6,7,8,9,10,11',
                'search' => 'nullable|string|max:100',
            ]);

            $query = VoucherSummary::withoutTrashed()
                ->whereBetween('date', [$validated['date_from'], $validated['date_to']])
                ->whereIn('status', [2, 3])
                ->whereNotIn('reference_type', [10, 12, 13, 14, 15])
                ->when(!empty($validated['reference_types']), function ($q) use ($validated) {
                    $q->whereIn('reference_type', $validated['reference_types']);
                })
                ->when($validated['search'] ?? false, function ($q, $search) {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('voucher_no', 'like', "%{$search}%")
                            ->orWhere('particulars', 'like', "%{$search}%")
                            ->orWhereHas('voucherSummaryDetail', function ($d) use ($search) {
                                $d->where('particulars', 'like', "%{$search}%");
                            });
                    });
                })
                ->with([
                    'voucherSummaryDetail:id,voucher_summary_id,particulars,debit,credit,account_head_id,member_entry_id',
                    'voucherSummaryDetail.memberEntry.meterIssues:id,member_entry_id,meter_no'
                ])
                ->orderBy('date')
                // ->orderBy('voucher_no');
                ->orderBy('created_at')
            ->orderBy('id');

            $vouchers = $query->paginate(200);

            $transformed = $vouchers->getCollection()->map(function ($voucher) {
                $lines = collect($voucher->voucherSummaryDetail);

                $detailWithMember = $lines->firstWhere('member_entry_id', '!=', null);
                $member = $detailWithMember?->memberEntry;
                $meterIssue = $member?->meterIssues->first();

                // Check if it's Meter Deposit Return (3) or Share Return (8)
                $isReturnType = in_array((int) $voucher->reference_type, [3, 8], true);

                // Check if it's Mahasul Receipt (11)
                $isMahasulReceipt = $voucher->reference_type == 11;

                // For return types, include ALL account heads
                // For Mahasul Receipt: bill = [11,17,16,15,14], fine = [13,20]
                $billLines = $isReturnType
                    ? $lines
                    : ($isMahasulReceipt
                        ? $lines->whereIn('account_head_id', [11, 17, 16, 15, 14])
                        : $lines->whereIn('account_head_id', [7, 8, 11, 12, 13, 14, 15, 16, 17, 19]));

                $totalBill = $billLines->sum('credit');

                // Fine amount only for Mahasul Receipt (13, 20)
                $fineAmount = $isMahasulReceipt
                    ? $lines->whereIn('account_head_id', [13, 20])->sum('credit')
                    : 0;

               // SERVICE CHARGE (account_head_id = 17) → ONLY for 3 & 8
                        $serviceCharge = 0;

                        if ($isReturnType) {
                            $serviceCharge = $lines->where('account_head_id', 17)->sum('credit');
                        }

                        // Paid calculation
                        if ($isReturnType) {

                            // total debit (actual cash/bank given)
                            $totalDebit = $lines->sum('debit');

                            // subtract service charge (account_head_id 17 credit)
                            $paidAmount = $totalDebit - $serviceCharge;

                            // make paid negative
                            $paidAmount = -$paidAmount;

                            // bill should be total credit (including 17)
                            $totalBill = -$lines->sum('credit');

                        } else {

                            $paidAmount = $lines
                                ->whereIn('account_head_id', [1, 2])
                                ->sum('debit');
                        }

                $advanceAmount = $lines->where('account_head_id', 9)->sum('credit');
                $advanceAmountDr = $lines->where('account_head_id', 9)->sum('debit');

                $byHead = $lines->groupBy('account_head_id')->map(fn($g) => [
                    'debit' => $g->sum('debit'),
                    'credit' => $g->sum('credit'),
                ]);

                $mahasulData = null;
                if ($voucher->reference_type == 11 && $voucher->reference_id) {
                    $mahasulData = MahasulReceiptEntry::select('id', 'is_cancel', 'date_in_bs')
                        ->find($voucher->reference_id);
                }

                if ((in_array((int) $voucher->reference_type, [1, 2, 3], true)) && $voucher->reference_id) {
                    $mahasulData = MeterDepositTransaction::select('id', 'is_cancel', 'date_in_bs')
                        ->find($voucher->reference_id);
                }
                if ((in_array((int) $voucher->reference_type, [7, 8], true)) && $voucher->reference_id) {
                    $mahasulData = ShareTransaction::select('id', 'is_cancel', 'date_in_bs')
                        ->find($voucher->reference_id);
                }

                if ((in_array((int) $voucher->reference_type, [4], true)) && $voucher->reference_id) {
                    $mahasulData = AdvancePayment::select('id', 'is_cancel', 'date_in_bs')
                    ->where('type', 0)
                        ->find($voucher->reference_id);
                }

                if ((in_array((int) $voucher->reference_type, [5], true)) && $voucher->reference_id) {
                    $mahasulData = MeterInsurance::select('id', 'is_cancel', 'date_in_bs')
                        ->find($voucher->reference_id);
                }
                if ((in_array((int) $voucher->reference_type, [6], true)) && $voucher->reference_id) {
                    $mahasulData = OtherIncomeReceipt::select('id', 'is_cancel', 'date_in_bs')
                        ->find($voucher->reference_id);
                }
                if ((in_array((int) $voucher->reference_type, [9], true)) && $voucher->reference_id) {
                    $mahasulData = NonMemberPayment::select('id', 'is_cancel', 'date_in_bs')
                        ->find($voucher->reference_id);
                }
                if ((in_array((int) $voucher->reference_type, [10], true)) && $voucher->reference_id) {
                    $mahasulData = NEAPaymentEntry::select('id', 'is_cancel', 'date_in_bs')
                        ->find($voucher->reference_id);
                }

                $isCancelled = (int) ($mahasulData?->is_cancel ?? 0) === 1;

                return [
                    'voucher_id' => $voucher->id,
                    'voucher_no' => $voucher->voucher_no,
                    'date' => $voucher->date,
                    'reference_type' => $voucher->reference_type,
                    'reference_type_name' => $this->getReferenceTypeName($voucher->reference_type),
                    'reference_id' => $voucher->reference_id,
                    'particulars' => $voucher->particulars,
                    'member_entry_id' => $member?->member_no,
                    'customer_name_en' => $voucher->reference_type == 9
                    ? NonMemberPayment::where('id', $voucher->reference_id)->value('customer_name_en')
                    : $member?->customer_name_en,

                   'customer_name_np' => $voucher->reference_type == 9
                    ? NonMemberPayment::where('id', $voucher->reference_id)->value('customer_name_np')
                    : $member?->customer_name_np,
                    'meter_no' => $meterIssue?->meter_no,

                    'bill_amount' => $isCancelled ? 0 : $totalBill,
                    'fine_amount' => $isCancelled ? 0 : $fineAmount,
                    'advance_amount' => $isCancelled ? 0 : $advanceAmount,
                    'advance_used' => $isCancelled ? 0 : $advanceAmountDr,
                    'paid_amount' => $isCancelled ? 0 : $paidAmount,
                        'service_charge' => $isReturnType
                            ? ($isCancelled ? 0 : $serviceCharge)
                            : 0,
                    'discount_given' => $isCancelled ? 0 : ($byHead[22]['debit'] ?? 0),
                    'rebate_given' => $isCancelled ? 0 : ($byHead[23]['debit'] ?? 0),

                    'cancel_status' => $isCancelled ? 1 : 0,
                    'mahasul_date_in_bs' => $mahasulData?->date_in_bs,
                ];
            });

            $vouchers->setCollection($transformed);

            $totals = [
                'bill_amount' => round($transformed->sum('bill_amount'), 2),
                'fine_amount' => round($transformed->sum('fine_amount'), 2),
                'paid_amount' => round($transformed->sum('paid_amount'), 2),
                'service_charge' => round($transformed->sum('service_charge'), 2),
                'advance_amount' => round($transformed->sum('advance_amount'), 2),
                'total_bill_amount_without_discount_and_rebate' => round($transformed->sum('total_bill_amount_without_discount_and_rebate'), 2),
                'discount_given' => round($transformed->sum('discount_given'), 2),
                'rebate_given' => round($transformed->sum('rebate_given'), 2),
            ];

            return response()->json([
                'success' => true,
                'count' => $vouchers->count(),
                'data' => $vouchers->items(),

                'totals' => $totals,
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
                'next_page_url' => $vouchers->nextPageUrl(),
                'prev_page_url' => $vouchers->previousPageUrl(),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed !!',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error !',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    private function getReferenceTypeName(int $type): string
    {
        return match ($type) {
            1 => 'Meter Deposit',
            2 => 'Upgrade Meter',
            3 => 'Meter Deposit Return',
            4 => 'Advance Payment',
            5 => 'Meter Insurance',
            6 => 'Other Income Receipt',
            7 => 'Share Entry',
            8 => 'Share Return',
            9 => 'Non-Member Payment',
            10 => 'NEA Payment',
            11 => 'Mahasul Receipt',
            default => 'Unknown',
        };
    }

   public function mahasulIndex(Request $request)
{
    try {
        $validated = $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => 'nullable|string|max:100',
              'is_synced' => 'nullable|in:0,1',
        ]);

        $query = VoucherSummary::withoutTrashed()
            ->whereBetween('date', [$validated['date_from'], $validated['date_to']])
            ->where('reference_type', 11) // ONLY Mahasul
            ->whereIn('status', [2, 3])
             ->when(isset($validated['is_synced']), function ($q) use ($validated) {
                $q->whereExists(function ($sub) use ($validated) {
                    $sub->select(\DB::raw(1))
                        ->from('mahasul_receipts as mr')
                        ->whereColumn('mr.voucher_no', 'voucher_summaries.voucher_no')
                        ->where('mr.is_synced', $validated['is_synced'])
                        ->whereNull('mr.deleted_at');
                });
            })
            ->when($validated['search'] ?? false, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('voucher_no', 'like', "%{$search}%")
                        ->orWhere('particulars', 'like', "%{$search}%")
                        ->orWhereHas('voucherSummaryDetail', function ($d) use ($search) {
                            $d->where('particulars', 'like', "%{$search}%");
                        });
                });
            })
            ->with([
                'voucherSummaryDetail:id,voucher_summary_id,particulars,debit,credit,account_head_id,member_entry_id',
                'voucherSummaryDetail.memberEntry.meterIssues:id,member_entry_id,meter_no'
            ])
            ->orderBy('date')
            ->orderBy('created_at')
            ->orderBy('id');

        $vouchers = $query->paginate(200);

        $transformed = $vouchers->getCollection()->map(function ($voucher) {

            $lines = collect($voucher->voucherSummaryDetail);

            $detailWithMember = $lines->firstWhere('member_entry_id', '!=', null);
            $member = $detailWithMember?->memberEntry;
            $meterIssue = $member?->meterIssues->first();

            $isReturnType = false; 
            $isMahasulReceipt = true;

            $billLines = $lines->whereIn('account_head_id', [11, 17, 16, 15, 14]);
            $totalBill = $billLines->sum('credit');

            $fineAmount = $lines->whereIn('account_head_id', [13, 20])->sum('credit');

            $serviceCharge = 0;

            $paidAmount = $lines
                ->whereIn('account_head_id', [1, 2])
                ->sum('debit');

            $advanceAmount = $lines->where('account_head_id', 9)->sum('credit');
            $advanceAmountDr = $lines->where('account_head_id', 9)->sum('debit');

            $byHead = $lines->groupBy('account_head_id')->map(fn($g) => [
                'debit' => $g->sum('debit'),
                'credit' => $g->sum('credit'),
            ]);

            $mahasulData = null;
            if ($voucher->reference_id) {
                $mahasulData = MahasulReceiptEntry::select('id', 'is_cancel', 'date_in_bs')
                    ->find($voucher->reference_id);
            }
                $mahasulData = MahasulReceiptEntry::select('id', 'is_cancel', 'date_in_bs', 'is_synced')
                ->where('voucher_no', $voucher->voucher_no)
                ->first();
            $isCancelled = (int) ($mahasulData?->is_cancel ?? 0) === 1;

            return [
                'voucher_id' => $voucher->id,
                'voucher_no' => $voucher->voucher_no,
                'date' => $voucher->date,

                'reference_type' => 11,
                'reference_type_name' => $this->getReferenceTypeName(11),
                'reference_id' => $voucher->reference_id,

                'particulars' => $voucher->particulars,

                'member_entry_id' => $member?->member_no,
                'customer_name_en' => $member?->customer_name_en,
                'customer_name_np' => $member?->customer_name_np,
                'meter_no' => $meterIssue?->meter_no,

                // EXACT SAME ZEROING LOGIC
                'bill_amount' => $isCancelled ? 0 : $totalBill,
                'fine_amount' => $isCancelled ? 0 : $fineAmount,
                'advance_amount' => $isCancelled ? 0 : $advanceAmount,
                'advance_used' => $isCancelled ? 0 : $advanceAmountDr,
                'paid_amount' => $isCancelled ? 0 : $paidAmount,
                'service_charge' => 0, // IMPORTANT

                'discount_given' => $isCancelled ? 0 : ($byHead[22]['debit'] ?? 0),
                'rebate_given' => $isCancelled ? 0 : ($byHead[23]['debit'] ?? 0),

                'cancel_status' => $isCancelled ? 1 : 0,
                'mahasul_date_in_bs' => $mahasulData?->date_in_bs,
                 'is_synced' => $mahasulData?->is_synced ?? null,
            ];
        });

        $vouchers->setCollection($transformed);

        // EXACT SAME TOTALS STRUCTURE
        $totals = [
            'bill_amount' => $transformed->sum('bill_amount'),
            'fine_amount' => $transformed->sum('fine_amount'),
            'paid_amount' => $transformed->sum('paid_amount'),
            'service_charge' => $transformed->sum('service_charge'),
            'advance_amount' => $transformed->sum('advance_amount'),
            'total_bill_amount_without_discount_and_rebate' => 0, // same as original output
            'discount_given' => $transformed->sum('discount_given'),
            'rebate_given' => $transformed->sum('rebate_given'),
        ];

        return response()->json([
            'success' => true,
            'count' => $vouchers->count(),
            'data' => $vouchers->items(),
            'totals' => $totals,
            'current_page' => $vouchers->currentPage(),
            'last_page' => $vouchers->lastPage(),
            'per_page' => $vouchers->perPage(),
            'total' => $vouchers->total(),
            'next_page_url' => $vouchers->nextPageUrl(),
            'prev_page_url' => $vouchers->previousPageUrl(),
        ]);

    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed !!',
            'errors' => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Server error !',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}
