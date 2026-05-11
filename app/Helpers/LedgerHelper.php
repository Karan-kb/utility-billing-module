<?php

namespace App\Helpers;

use App\Models\MemberEntry;
use App\Models\MeterIssue;
use App\Models\MeterReadingEntry;
use App\Models\AdvancePayment;
use App\Models\Fine;
use App\Models\MahasulReceiptEntry;
use App\Models\OtherIncomeReceipt;
use App\Models\MeterInsurance;
use App\Models\MeterDepositTransaction;
use App\Models\NEAPaymentEntry;
use App\Models\NEAPurchase;
use App\Models\ShareTransaction;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class LedgerHelper
{
    public static function getMemberLedger($identifier)
    {
        $member = MemberEntry::where('member_no', $identifier)
            ->orWhere('customer_name_en', 'LIKE', "%{$identifier}%")
            ->orWhere('customer_name_np', 'LIKE', "%{$identifier}%")
            ->first();

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        $transactions = new Collection();
        $meterIssueIds = $member->meterIssues()->pluck('id');

        $addTransaction = function ($data, $createdAt, $originalId, $source) use ($transactions) {
            $transactions->push(array_merge($data, [
                'created_at'  => $createdAt instanceof Carbon ? $createdAt : Carbon::parse($createdAt ?? now()),
                'original_id' => (int)$originalId,
                'source'      => $source,
            ]));
        };

        // 1. Opening Mahasul (entry_type = 2)
       $openingMahasul = MeterReadingEntry::whereIn('meter_issue_id', $meterIssueIds)
            ->where('entry_type', 2)->get();

            foreach ($openingMahasul as $item) {
                $addTransaction([
                    'date_in_bs' => $item->reading_date_in_bs,
                    'particular' => 'Opening Mahasul entry',
                    'debit'      => (float)$item->total_charge - (float)($item->fine_amount ?? 0),
                    'credit'     => 0,
                    'is_cancel'  => 0,
                    'priority'   => 1,
                ], $item->created_at, $item->id, 'opening_mahasul');

                // 2. FINE FOR OPENING MAHASUL (NEW ADDITION)
                if (!empty($item->fine_amount) && $item->fine_amount > 0) {

                    $addTransaction([
                        'date_in_bs' => $item->reading_date_in_bs,
                        'particular' => "Fine amount of Opening Mahasul Entry",
                        'debit'      => (float)$item->fine_amount,
                        'credit'     => 0,
                        'is_cancel'  => 0,
                        'priority'   => 2,
                    ], $item->created_at, $item->id . '-fine', 'opening_mahasul_fine');
                }
            }
        // 2. Opening Advance Payment (type = 1) - Old logic (credit only, no double entry)
        $openingAdvances = AdvancePayment::whereIn('meter_issue_id', $meterIssueIds)
            ->where('type', 1)->get();

        foreach ($openingAdvances as $item) {
            $addTransaction([
                'date_in_bs' => $item->date_in_bs,
                'particular' => 'Opening Advance Payment entry',
                'debit'      => 0,
                'credit'     => (float)$item->amount,
                'is_cancel'  => 0,
            ], $item->created_at, $item->id, 'advance_payment_opening');
        }

        // 3. Advance Payment with type = 0 (New - treated like Mahasul Receipt)
        $advanceReceipts = AdvancePayment::whereIn('meter_issue_id', $meterIssueIds)
            ->where('type', 0)->get();

        foreach ($advanceReceipts as $item) {
            $amount  = (float)$item->amount;
            $voucher = $item->voucher_no ?? 'N/A';   // Use voucher_no if available, else fallback

            $base = [
                'date_in_bs' => $item->date_in_bs,
                'debit'      => 0,
                'credit'     => $amount,
                'is_cancel'  => (int)($item->is_cancel ?? 0),
            ];

            if (!empty($item->is_cancel) && $item->is_cancel == 1) {
                // Cancelled receipt
                $addTransaction(array_merge($base, [
                    'particular' => "Advance Payment Entry - bill {$voucher} (Cancelled)"
                ]), $item->created_at, $item->id, 'advance_payment');

                // Cancellation entry
                $addTransaction([
                    'date_in_bs' => $item->date_in_bs,
                    'particular' => "Bill cancelled of {$voucher}",
                    'debit'      => $amount,
                    'credit'     => 0,
                    'is_cancel'  => 1,
                ], $item->created_at, $item->id, 'advance_payment_cancel');
            } else {
                // Normal receipt
                $addTransaction(array_merge($base, [
                    'particular' => "Advance Payment Entry - bill {$voucher}"
                ]), $item->created_at, $item->id, 'advance_payment');
            }

            // Always add the opposite "bill XXXX" entry (as per your requirement)
            $addTransaction([
                'date_in_bs' => $item->date_in_bs,
                'particular' => "bill {$voucher}",
                'debit'      => $amount,
                'credit'     => 0,
                'is_cancel'  => (int)($item->is_cancel ?? 0),
            ], $item->created_at, $item->id, 'advance_payment_opposite');
        }

        // 4. Regular Meter Reading (entry_type = 1)
        $readings = MeterReadingEntry::whereIn('meter_issue_id', $meterIssueIds)
            ->where('entry_type', 1)->get();

        foreach ($readings as $item) {
            $monthName = self::getNepaliMonthName($item->reading_month_in_bs ?? 1);
            $addTransaction([
                'date_in_bs' => $item->reading_date_in_bs,
                'particular' => "Meter Reading entry for {$monthName}",
                'debit'      => (float)$item->total_charge - (float)($item->fine_amount ?? 0),
                'credit'     => 0,
                'is_cancel'  => 0,
                'priority'   => 1, 
            ], $item->created_at, $item->id, 'meter_reading');
        }

        // 5. Fine
      $fines = Fine::whereIn('meter_issue_id', $meterIssueIds)->get();
            $groupedFines = $fines->groupBy(function ($item) {
                return $item->meter_reading_entry_id . '|' . $item->date_in_bs . '|' . $item->fine_type;
            });

            foreach ($groupedFines as $group) {

                $first = $group->first();
                $totalAmount = $group->sum('amount');

                $reading = MeterReadingEntry::find($first->meter_reading_entry_id);
                if ($reading && $reading->entry_type != 2) {

                    $monthName = self::getNepaliMonthName($reading->reading_month_in_bs ?? 1);

                    if ((int)$first->fine_type === 2) {
                        $particular = "Blacklist Charge of {$monthName}";
                        $priority = 3;
                    }
                    else if((int)$first->fine_type === 3) {
                        $particular = "Fine Post from due of mahsul receipt of {$monthName}";
                         $priority = 3;
                    }
                     else {
                        $particular = "Fine Post of {$monthName}";
                        $priority = 2;
                    }
                }
                else {
                    if ((int)$first->fine_type === 2) {
                        $particular = "Blacklist Charge of Opening Mahasul Entry";
                        $priority = 3;
                    } else {
                        $particular = "Fine Post of Opening Mahasul Entry";
                        $priority = 2;
                    }
                }

                $addTransaction([
                    'date_in_bs' => $first->date_in_bs,
                    'particular' => $particular,
                    'debit'      => (float)$totalAmount,
                    'credit'     => 0,
                    'is_cancel'  => 0,
                    'priority'   => $priority,
                ], $first->created_at, $first->meter_reading_entry_id ?? $first->id, 'fine');
            }

        // 6. Mahasul Receipt Entry (FIXED CANCEL ORDER LOGIC)
        $receipts = MahasulReceiptEntry::whereIn('meter_issue_id', $meterIssueIds)->get();

                foreach ($receipts as $item) {

                    $paid     = (float)$item->paid_amount;
                    $rebate   = (float)($item->rebate_amount ?? 0);
                    $discount = (float)($item->discount_amount ?? 0);

                    $voucher  = $item->voucher_no;
                    $isCancel = (int)$item->is_cancel;

                    $net = $paid;

                    // ================= NORMAL =================
                    if ($isCancel !== 1) {

                        // $addTransaction([
                        //     'date_in_bs' => $item->date_in_bs,
                        //     'particular' => "Mahasul Receipt Entry - bill {$voucher}",
                        //     'debit'      => 0,
                        //     'credit'     => $net,
                        //     'is_cancel'  => 0,
                        //     'priority'   => 5,
                        // ], $item->created_at, $item->id, 'mahasul_receipt');
                        $isAdvanceAdjusted = (int)($item->advance_status ?? 0) !== 0;

                        $addTransaction([
                            'date_in_bs' => $item->date_in_bs,
                            'particular' => $isAdvanceAdjusted
                                ? "Advance Adjusted - bill {$voucher}"
                                : "Mahasul Receipt Entry - bill {$voucher}",

                            'debit'      => 0,
                            'credit'     => $net, 
                            'is_cancel'  => 0,
                            'priority'   => 5,
                            'affects_balance' => !$isAdvanceAdjusted,
                        ], $item->created_at, $item->id, 'mahasul_receipt');

                        if ($rebate > 0) {
                            $addTransaction([
                                'date_in_bs' => $item->date_in_bs,
                                'particular' => "Rebate Amount - bill {$voucher}",
                                'debit'      => 0,
                                'credit'     => $rebate,
                                'is_cancel'  => 0,
                            ], $item->created_at, $item->id.'-rebate', 'rebate');
                        }

                        if ($discount > 0) {
                            $addTransaction([
                                'date_in_bs' => $item->date_in_bs,
                                'particular' => "Discount Amount - bill {$voucher}",
                                'debit'      => 0,
                                'credit'     => $discount,
                                'is_cancel'  => 0,
                            ], $item->created_at, $item->id.'-discount', 'discount');
                        }
                    }

                    // ================= CANCEL =================
                    else {

                        // 1. Cancelled main bill (CREDIT)
                        $addTransaction([
                            'date_in_bs' => $item->date_in_bs,
                            'particular' => "Mahasul Receipt Entry - bill {$voucher} (Cancelled)",
                            'debit'      => 0,
                            'credit'     => $net,
                            'is_cancel'  => 1,
                        ], $item->created_at, $item->id, 'cancel_main');

                        // 2. Discount (CREDIT SIDE FIRST - your required format)
                        if ($discount > 0) {
                            $addTransaction([
                                'date_in_bs' => $item->date_in_bs,
                                'particular' => "Discount - bill {$voucher} (Cancelled)",
                                'debit'      => 0,
                                'credit'     => $discount,
                                'is_cancel'  => 1,
                            ], $item->created_at, $item->id.'-disc-view', 'discount_cancel_view');

                            // 3. Discount reversal (DEBIT)
                            $addTransaction([
                                'date_in_bs' => $item->date_in_bs,
                                'particular' => "Discount cancelled - bill {$voucher}",
                                'debit'      => $discount,
                                'credit'     => 0,
                                'is_cancel'  => 1,
                            ], $item->created_at, $item->id.'-disc-rev', 'discount_cancel_reverse');
                        }

                        // 4. Rebate (if needed same pattern)
                        if ($rebate > 0) {
                            $addTransaction([
                                'date_in_bs' => $item->date_in_bs,
                                'particular' => "Rebate - bill {$voucher} (Cancelled)",
                                'debit'      => 0,
                                'credit'     => $rebate,
                                'is_cancel'  => 1,
                            ], $item->created_at, $item->id.'-reb-view', 'rebate_cancel_view');

                            $addTransaction([
                                'date_in_bs' => $item->date_in_bs,
                                'particular' => "Rebate cancelled - bill {$voucher}",
                                'debit'      => $rebate,
                                'credit'     => 0,
                                'is_cancel'  => 1,
                            ], $item->created_at, $item->id.'-reb-rev', 'rebate_cancel_reverse');
                        }

                        // 5. Final bill reversal (DEBIT LAST)
                        $addTransaction([
                            'date_in_bs' => $item->date_in_bs,
                            'particular' => "Bill cancelled of {$voucher}",
                            'debit'      => $net,
                            'credit'     => 0,
                            'is_cancel'  => 1,
                        ], $item->created_at, $item->id.'-rev', 'cancel_reverse');
                    }
                }
        // 7. Other Income Receipt
        $otherIncomes = OtherIncomeReceipt::whereIn('meter_issue_id', $meterIssueIds)->get();

        foreach ($otherIncomes as $item) {
            $amount  = (float)$item->amount;
            $voucher = $item->voucher_no ?? 'N/A';

            $addTransaction([
                'date_in_bs' => $item->date_in_bs,
                'particular' => "Other Income Receipt Entry - bill {$voucher}",
                'debit'      => $amount,
                'credit'     => 0,
                'is_cancel'  => (int)($item->is_cancel ?? 0),
            ], $item->created_at, $item->id, 'other_income');

            $addTransaction([
                'date_in_bs' => $item->date_in_bs,
                'particular' => "bill {$voucher}",
                'debit'      => 0,
                'credit'     => $amount,
                'is_cancel'  => (int)($item->is_cancel ?? 0),
            ], $item->created_at, $item->id, 'other_income_opposite');

            if (!empty($item->is_cancel) && $item->is_cancel == 1) {
                $addTransaction([
                    'date_in_bs' => $item->date_in_bs,
                    'particular' => "Other Income Receipt Entry Cancelled - bill {$voucher}",
                    'debit'      => 0,
                    'credit'     => $amount,
                    'is_cancel'  => 1,
                ], $item->created_at, $item->id, 'other_income_cancel');

                $addTransaction([
                    'date_in_bs' => $item->date_in_bs,
                    'particular' => "Bill cancelled {$voucher}",
                    'debit'      => $amount,
                    'credit'     => 0,
                    'is_cancel'  => 1,
                ], $item->created_at, $item->id, 'other_income_cancel_opposite');
            }
        }

        // 8. Meter Insurance (same as Other Income)
        $meterInsurances = MeterInsurance::whereIn('meter_issue_id', $meterIssueIds)->get();

        foreach ($meterInsurances as $item) {
            $amount  = (float)$item->amount;
            $voucher = $item->voucher_no ?? 'N/A';

            $addTransaction([
                'date_in_bs' => $item->date_in_bs,
                'particular' => "Meter Insurance Entry - bill {$voucher}",
                'debit'      => $amount,
                'credit'     => 0,
                'is_cancel'  => (int)($item->is_cancel ?? 0),
            ], $item->created_at, $item->id, 'meter_insurance');

            $addTransaction([
                'date_in_bs' => $item->date_in_bs,
                'particular' => "bill {$voucher}",
                'debit'      => 0,
                'credit'     => $amount,
                'is_cancel'  => (int)($item->is_cancel ?? 0),
            ], $item->created_at, $item->id, 'meter_insurance_opposite');
        }

        // 9. ShareTransaction
       $shares = ShareTransaction::where('member_entry_id', $member->id)
        ->where('transaction_type', '!=', 3)
        ->get();

        foreach ($shares as $item) {
            $amount  = (float)$item->amount;
            $voucher = $item->voucher_no ?? 'N/A';

            $particularMain = ($item->transaction_type == 1) ? 'Share Purchase entry' : 'Share Return entry';
            $debitFirst = ($item->transaction_type != 1); // credit first for purchase (type 1)

            $addTransaction([
                'date_in_bs' => $item->date_in_bs,
                'particular' => $particularMain,
                'debit'      => $debitFirst ? $amount : 0,
                'credit'     => $debitFirst ? 0 : $amount,
                'is_cancel'  => 0,
            ], $item->created_at, $item->id, 'share');

            $addTransaction([
                'date_in_bs' => $item->date_in_bs,
                'particular' => "bill {$voucher}",
                'debit'      => $debitFirst ? 0 : $amount,
                'credit'     => $debitFirst ? $amount : 0,
                'is_cancel'  => 0,
            ], $item->created_at, $item->id, 'share_opposite');
        }

        // 10. Meter Deposit Transaction
       
        $deposits = MeterDepositTransaction::whereIn('meter_issue_id', $meterIssueIds)
            ->where('transaction_type', '!=', 4) 
            ->get();
        foreach ($deposits as $item) {
            $amount = (float)$item->amount;
            $voucher = $item->voucher_no ?? 'N/A';

            $particularMain = match((int)$item->transaction_type) {
                1 => 'Meter Deposit entry',
                2 => 'Meter Deposit Return entry',
                3 => 'Upgrade Meter Capacity entry',
                default => 'Meter Deposit Transaction',
            };

            $debitFirst = in_array((int)$item->transaction_type, [1, 3]);

            $addTransaction([
                'date_in_bs' => $item->date_in_bs,
                'particular' => $particularMain,
                'debit'      => $debitFirst ? $amount : 0,
                'credit'     => $debitFirst ? 0 : $amount,
                'is_cancel'  => 0,
            ], $item->created_at, $item->id, 'meter_deposit');

            $addTransaction([
                'date_in_bs' => $item->date_in_bs,
                'particular' => "bill {$voucher}",
                'debit'      => $debitFirst ? 0 : $amount,
                'credit'     => $debitFirst ? $amount : 0,
                'is_cancel'  => 0,
            ], $item->created_at, $item->id, 'meter_deposit_opposite');
        }

        // ==================== FINAL SORTING (by date_in_bs → created_at → id) ====================
        $sorted = $transactions->sortBy([
           // ['date_in_bs', 'asc'],
            ['created_at', 'asc'],
            ['priority', 'asc'],
            ['original_id', 'asc'],
        ])->values();

        $balance = 0.0;
        $ledger = $sorted->map(function ($trans) use (&$balance) {
            $debit  = $trans['debit'] ?? 0;
            $credit = $trans['credit'] ?? 0;
            // $balance += $debit - $credit;
            $affects = $trans['affects_balance'] ?? true;
                if ($affects) {
                    $balance += $debit - $credit;
                }

            return [
                'date_in_bs' => $trans['date_in_bs'],
                'particular' => $trans['particular'],
                'debit'      => round($debit, 2),
                'credit'     => round($credit, 2),
                'balance'    => round($balance, 2),
                'is_cancel'  => $trans['is_cancel'] ?? 0,
            ];
        });

        return [
            'member' => [
                'member_no'         => $member->member_no,
                'customer_name_en'  => $member->customer_name_en,
                'customer_name_np'  => $member->customer_name_np,
            ],
            'ledger' => $ledger,
            'opening_balance' => 0,
            'closing_balance' => round($balance, 2),
        ];
    }

    private static function getNepaliMonthName($monthNum)
    {
        $months = [
            1 => 'Baishak', 2 => 'Jestha', 3 => 'Ashad', 4 => 'Shrawan',
            5 => 'Bhadra',  6 => 'Ashwin', 7 => 'Kartik', 8 => 'Mangsir',
            9 => 'Poush',  10 => 'Magh',  11 => 'Falgun', 12 => 'Chaitra'
        ];

        return $months[(int)$monthNum] ?? "Month {$monthNum}";
    }

    

public static function getNeaLedger($transformerId = null)
{
    $transactions = new Collection();

    $addTransaction = function ($data, $createdAt, $originalId, $source) use ($transactions) {
        $transactions->push(array_merge($data, [
            'created_at'  => $createdAt instanceof Carbon ? $createdAt : Carbon::parse($createdAt ?? now()),
            'original_id' => (int)$originalId,
            'source'      => $source,
        ]));
    };

    $purchaseQuery = NEAPurchase::query();
    $paymentQuery  = NEAPaymentEntry::query();

    if (!empty($transformerId)) {
        $purchaseQuery->where('transformer_id', $transformerId);
        $paymentQuery->where('transformer_id', $transformerId);
    }

    $purchases = $purchaseQuery->get();

    foreach ($purchases as $item) {
        $amount = (float)$item->amount;

        $addTransaction([
            'date_in_bs' => $item->date_in_bs,
            'particular' => "NEA Purchase Entry (Month {$item->month})",
            'debit'      => 0,
            'credit'     => $amount,
            'is_cancel'  => 0,
        ], $item->created_at, $item->id, 'nea_purchase');

        
    }

    // ===================== 2. NEA PAYMENT (DEBIT) =====================
    $payments = $paymentQuery->get();

            foreach ($payments as $item) {

                $paid     = (float)$item->paid_amount;
                $fine     = (float)($item->fine_amount ?? 0);
                $rebate   = (float)($item->rebate_amount ?? 0);
                $voucher  = $item->voucher_no ?? 'N/A';
                $isCancel = (int)$item->is_cancel;

                // ================= NORMAL =================
                if ($isCancel !== 1) {

                    // 1. MAIN PAYMENT (ONLY PAID AMOUNT)
                    if ($paid > 0) {
                        $addTransaction([
                            'date_in_bs' => $item->date_in_bs,
                            'particular' => "NEA Payment Entry - bill {$voucher}",
                            'debit'      => $paid,
                            'credit'     => 0,
                            'is_cancel'  => 0,
                        ], $item->created_at, $item->id, 'nea_payment');
                    }

                    // 2. FINE (CREDIT)
                    if ($fine > 0) {
                        $addTransaction([
                            'date_in_bs' => $item->date_in_bs,
                            'particular' => "Fine - bill {$voucher}",
                            'debit'      => 0,
                            'credit'     => $fine,
                            'is_cancel'  => 0,
                        ], $item->created_at, $item->id.'-fine', 'nea_fine');
                    }

                    // 3. REBATE (DEBIT)
                    if ($rebate > 0) {
                        $addTransaction([
                            'date_in_bs' => $item->date_in_bs,
                            'particular' => "Rebate - bill {$voucher}",
                            'debit'      => $rebate,
                            'credit'     => 0,
                            'is_cancel'  => 0,
                        ], $item->created_at, $item->id.'-reb', 'nea_rebate');
                    }
                }

                // ================= CANCEL =================
                else {

                    // 1. PAYMENT CANCEL VIEW
                    if ($paid > 0) {
                        $addTransaction([
                            'date_in_bs' => $item->date_in_bs,
                            'particular' => "NEA Payment Entry - bill {$voucher} (Cancelled)",
                            'debit'      => $paid,
                            'credit'     => 0,
                            'is_cancel'  => 1,
                        ], $item->created_at, $item->id, 'nea_payment_cancel');
                    }

                    // 2. FINE VIEW + REVERSAL
                    if ($fine > 0) {

                        // view
                        $addTransaction([
                            'date_in_bs' => $item->date_in_bs,
                            'particular' => "Fine - bill {$voucher} (Cancelled)",
                            'debit'      => 0,
                            'credit'     => $fine,
                            'is_cancel'  => 1,
                        ], $item->created_at, $item->id.'-fine-view', 'nea_fine_view');

                        // reversal
                        $addTransaction([
                            'date_in_bs' => $item->date_in_bs,
                            'particular' => "Fine cancelled - bill {$voucher}",
                            'debit'      => $fine,
                            'credit'     => 0,
                            'is_cancel'  => 1,
                        ], $item->created_at, $item->id.'-fine-rev', 'nea_fine_reverse');
                    }

                    // 3. REBATE VIEW + REVERSAL
                    if ($rebate > 0) {

                        // view
                        $addTransaction([
                            'date_in_bs' => $item->date_in_bs,
                            'particular' => "Rebate - bill {$voucher} (Cancelled)",
                            'debit'      => $rebate,
                            'credit'     => 0,
                            'is_cancel'  => 1,
                        ], $item->created_at, $item->id.'-reb-view', 'nea_rebate_view');

                        // reversal
                        $addTransaction([
                            'date_in_bs' => $item->date_in_bs,
                            'particular' => "Rebate cancelled - bill {$voucher}",
                            'debit'      => 0,
                            'credit'     => $rebate,
                            'is_cancel'  => 1,
                        ], $item->created_at, $item->id.'-reb-rev', 'nea_rebate_reverse');
                    }

                    // 4. FINAL REVERSAL (CREDIT)
                    if ($paid > 0) {
                        $addTransaction([
                            'date_in_bs' => $item->date_in_bs,
                            'particular' => "Bill cancelled of {$voucher}",
                            'debit'      => 0,
                            'credit'     => $paid,
                            'is_cancel'  => 1,
                        ], $item->created_at, $item->id.'-rev', 'nea_payment_reverse');
                    }
                }
            }

    // ===================== SORT =====================
    $sorted = $transactions->sortBy([
        ['created_at', 'asc'],
        ['original_id', 'asc'],
    ])->values();

    // ===================== BALANCE =====================
    $balance = 0;

    $ledger = $sorted->map(function ($item) use (&$balance) {
        $balance += ($item['debit'] ?? 0) - ($item['credit'] ?? 0);

        return [
            'date_in_bs' => $item['date_in_bs'],
            'particular' => $item['particular'],
            'debit'      => round($item['debit'], 2),
            'credit'     => round($item['credit'], 2),
            'balance'    => round($balance, 2),
            'is_cancel'  => $item['is_cancel'] ?? 0,
        ];
    });

    return [
        'transformer_id' => $transformerId,
        'ledger' => $ledger,
        'closing_balance' => round($balance, 2),
    ];
}
}