<?php

namespace App\Services;

use App\Helpers\ProcessLogger;
use App\Models\CustomerTransaction;
use App\Models\MeterReadingEntry;
use App\Models\MemberEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class PriorityChargeService
{
    public function allocate(
        int $memberEntryId,
        int $receiptId,
        float $paidAmount,
        string $transactionDate,
        float $discountAmount = 0,
        float $rebateAmount = 0,
        float $disableDiscountAmount = 0,
    ): void {
        DB::connection('tenant')->transaction(function () use ($memberEntryId, $receiptId, $paidAmount, $transactionDate, $discountAmount, $rebateAmount, $disableDiscountAmount) {

            $totalAllocatable = round($paidAmount + $discountAmount + $rebateAmount, 2);
            $remainingAmount = $totalAllocatable;

            $priority = [11, 3, 1, 2, 4, 5, 6];

            $availableAdvance = $this->getAvailableAdvance($memberEntryId);
            $advanceUsed = 0;

            foreach ($priority as $chargeType) {

                if ($remainingAmount + $availableAdvance <= 0) {
                    break;
                }

                $totalDr = CustomerTransaction::where('member_entry_id', $memberEntryId)
                    ->where('charge_type', $chargeType)
                    ->where('direction', 'DR')
                    ->whereIn('transaction_type', [1, 5]) 
                    ->sum('amount');

                $totalCr = CustomerTransaction::where('member_entry_id', $memberEntryId)
                    ->where('charge_type', $chargeType)
                    ->where('direction', 'CR')
                    ->where('transaction_type', 2)
                    ->sum('amount');

                $outstanding = max(0, round($totalDr - $totalCr, 2));

                if ($outstanding <= 0) {
                    continue;
                }

                $amountToAllocate = min($outstanding, $remainingAmount + $availableAdvance);

                $cashToUse = min($amountToAllocate, $remainingAmount);

                if ($cashToUse > 0) {
                    $this->createCrRecord(
                        $memberEntryId,
                        $receiptId,
                        $chargeType,
                        $cashToUse,
                        $transactionDate
                    );

                    $remainingAmount = round($remainingAmount - $cashToUse, 2);
                }

                if ($remainingAmount <= 0 && $availableAdvance > 0 && $amountToAllocate > $cashToUse) {

                    $advanceToUse = min($amountToAllocate - $cashToUse, $availableAdvance);

                    if ($advanceToUse > 0) {
                        $this->createCrRecord(
                            $memberEntryId,
                            $receiptId,
                            $chargeType,
                            $advanceToUse,
                            $transactionDate
                        );

                        $availableAdvance = round($availableAdvance - $advanceToUse, 2);
                        $advanceUsed = round($advanceUsed + $advanceToUse, 2);
                    }
                }
            }

            if ($rebateAmount > 0) {
                $bill = MeterReadingEntry::whereIn('status', [0, 1])
                ->where('entry_type', 1)
                    ->whereExists(function ($q) use ($memberEntryId) {
                        $q->select(DB::raw(1))
                            ->from('customer_transactions')
                            ->whereColumn('reference_id', 'meter_reading_entries.id')
                            ->where('member_entry_id', $memberEntryId)
                            ->where('charge_type', 7)
                            ->where('direction', 'DR');
                    })
                    ->orderBy('reading_date_in_ad', 'asc')
                    ->first();

                CustomerTransaction::create([
                    'member_entry_id' => $memberEntryId,
                    'transaction_date' => $transactionDate,
                    'transaction_type' => 2,
                    'charge_type' => 7,
                    'amount' => round($rebateAmount, 2),
                    'direction' => 'DR',
                    'reference_id' => $bill ? $bill->id : null,
                    'receipt_id' => $receiptId,
                ]);
            }

            if ($discountAmount > 0) {
                $bill = MeterReadingEntry::whereIn('status', [0, 1])
                ->where('entry_type', 1)
                    ->whereExists(function ($q) use ($memberEntryId) {
                        $q->select(DB::raw(1))
                            ->from('customer_transactions')
                            ->whereColumn('reference_id', 'meter_reading_entries.id')
                            ->where('member_entry_id', $memberEntryId)
                            ->where('charge_type', 8)
                            ->where('direction', 'DR');
                    })
                    ->orderBy('reading_date_in_ad', 'asc')
                    ->first();

                CustomerTransaction::create([
                    'member_entry_id' => $memberEntryId,
                    'transaction_date' => $transactionDate,
                    'transaction_type' => 2,
                    'charge_type' => 8,
                    'amount' => round($discountAmount, 2),
                    'direction' => 'DR',
                    'reference_id' => $bill ? $bill->id : null,
                    'receipt_id' => $receiptId,
                ]);
            }

            if ($remainingAmount > 0) {
                $bill = MeterReadingEntry::whereIn('status', [0, 1])
                ->where('entry_type', 1)
                    ->whereExists(function ($q) use ($memberEntryId) {
                        $q->select(DB::raw(1))
                            ->from('customer_transactions')
                            ->whereColumn('reference_id', 'meter_reading_entries.id')
                            ->where('member_entry_id', $memberEntryId)
                            ->where('charge_type', 9)
                            ->where('direction', 'DR');
                    })
                    ->orderBy('reading_date_in_ad', 'asc')
                    ->first();

                CustomerTransaction::create([
                    'member_entry_id' => $memberEntryId,
                    'transaction_date' => $transactionDate,
                    'transaction_type' => 2,
                    'charge_type' => 9,
                    'amount' => round($remainingAmount, 2),
                    'direction' => 'CR',
                    'reference_id' => $bill ? $bill->id : null,
                    'receipt_id' => $receiptId,
                ]);
            }

            if ($advanceUsed > 0) {
                $bill = MeterReadingEntry::whereIn('status', [0, 1])
                ->where('entry_type', 1)
                    ->whereExists(function ($q) use ($memberEntryId) {
                        $q->select(DB::raw(1))
                            ->from('customer_transactions')
                            ->whereColumn('reference_id', 'meter_reading_entries.id')
                            ->where('member_entry_id', $memberEntryId)
                            ->where('charge_type', 9)
                            ->where('direction', 'CR');
                    })
                    ->orderBy('reading_date_in_ad', 'asc')
                    ->first();

                CustomerTransaction::create([
                    'member_entry_id' => $memberEntryId,
                    'transaction_date' => $transactionDate,
                    'transaction_type' => 2,
                    'charge_type' => 9,
                    'amount' => round($advanceUsed, 2),
                    'direction' => 'DR',
                    'reference_id' => $bill ? $bill->id : null,
                    'receipt_id' => $receiptId,
                ]);
            }

            $this->updateBlacklistStatus($memberEntryId);
            $this->updateAllBillStatuses($memberEntryId);
        });
    }

    /**
     * Update blacklist status for member
     */
    private function updateBlacklistStatus(int $memberEntryId): void
    {
        $totalBlacklistDr = CustomerTransaction::where('member_entry_id', $memberEntryId)
            ->where('charge_type', 11)
            ->where('direction', 'DR')
            ->where('transaction_type', 1)
            ->sum('amount');

        $totalBlacklistCr = CustomerTransaction::where('member_entry_id', $memberEntryId)
            ->where('charge_type', 11)
            ->where('direction', 'CR')
            ->where('transaction_type', 2)
            ->sum('amount');

        $outstandingBlacklist = max(0, floatval($totalBlacklistDr - $totalBlacklistCr));

        // If blacklist balance is 0, set is_blacklisted to 0
        if ($outstandingBlacklist == 0) {
            MemberEntry::where('id', $memberEntryId)->update(['is_blacklisted' => 0]);
        }
    }

    /**
     * ✅ FIXED: Direct CustomerTransaction query instead of relationship
     */
    private function createCrRecord(int $memberEntryId, int $receiptId, int $chargeType, float $amount, string $transactionDate): void
    {
        // Find oldest unpaid bill with DR for this charge_type
        $bill = MeterReadingEntry::whereIn('status', [0, 1])
        ->where('entry_type', 1)
            ->whereExists(function ($q) use ($memberEntryId, $chargeType) {
                $q->select(DB::raw(1))
                    ->from('customer_transactions')
                    ->whereColumn('reference_id', 'meter_reading_entries.id')
                    ->where('member_entry_id', $memberEntryId)
                    ->where('charge_type', $chargeType)
                    ->where('direction', 'DR');
            })
            ->orderBy('reading_date_in_ad', 'asc')
            ->first();
        // Log::info("CR Allocation", [
        //     'receipt_id' => $receiptId,
        //     'member_entry_id' => $memberEntryId,
        //     'charge_type' => $chargeType,
        //     'amount' => $amount,
        //     'direction' => 'CR'
        // ]);
        ProcessLogger::log(
        service: 'PriorityChargeService',
        action: 'CR Allocation',
        data: [
            'receipt_id' => $receiptId,
            'charge_type' => $chargeType,
            'amount' => $amount,
            'direction' => 'CR',
            'reference_bill_id' => $bill?->id
        ],
        level: 'info',
        stage: 'allocate_cr',
        context: [
            'member_entry_id' => $memberEntryId,
            'entry_id' => $bill?->id ?? null,
            'receipt_id' => $receiptId
        ]
    );

        CustomerTransaction::create([
            'member_entry_id' => $memberEntryId,
            'transaction_date' => $transactionDate,
            'transaction_type' => 2,
            'charge_type' => $chargeType,
            'amount' => $amount,
            'direction' => 'CR',
            'reference_id' => $bill ? $bill->id : null,
            'receipt_id' => $receiptId,
        ]);
    }

    /**
     * ✅ FIXED: Direct CustomerTransaction query for discounts
     */
    private function createDiscountCrRecord(int $memberEntryId, int $receiptId, int $chargeType, float $amount, string $transactionDate): void
    {
        // Find oldest unpaid bill with any DR
        $bill = MeterReadingEntry::whereIn('status', [0, 1])
        ->where('entry_type', 1)
            ->whereExists(function ($q) use ($memberEntryId) {
                $q->select(DB::raw(1))
                    ->from('customer_transactions')
                    ->whereColumn('reference_id', 'meter_reading_entries.id')
                    ->where('member_entry_id', $memberEntryId)
                    ->where('direction', 'DR')
                    ->whereNotIn('charge_type', [7, 8, 9, 10]);
            })
            ->orderBy('reading_date_in_ad', 'asc')
            ->first();
        Log::info("Discount/Rebate Allocation", [
            'receipt_id' => $receiptId,
            'charge_type' => $chargeType, // 8 = discount, 7 = rebate, 10 = disable
            'amount' => $amount
        ]);
        CustomerTransaction::create([
            'member_entry_id' => $memberEntryId,
            'transaction_date' => $transactionDate,
            'transaction_type' => 2,
            'charge_type' => $chargeType,
            'amount' => $amount,
            'direction' => 'CR',
            'reference_id' => $bill ? $bill->id : null,
            'receipt_id' => $receiptId,
        ]);
    }

    private function updateAllBillStatuses(int $memberEntryId): void
    {
        $bills = MeterReadingEntry::whereHas('meterIssue', fn($q) => $q->where('member_entry_id', $memberEntryId))
            // ->where('entry_type', 1)
            ->whereIn('status', [0, 1])
            ->get();

        foreach ($bills as $bill) {
            $this->updateBillStatus($bill);
        }
    }

  
    private function calculateBillDue(int $billId): float
    {
        $totalDr = CustomerTransaction::where('reference_id', $billId)
            ->where('direction', 'DR')
            ->whereNotIn('charge_type', [9])
            ->sum('amount');

        $totalCr = CustomerTransaction::where('reference_id', $billId)
            ->where('direction', 'CR')
            ->whereNotIn('charge_type', [7, 8, 10])
            ->sum('amount');

        return max(0, round($totalDr - $totalCr, 2));
    }

    private function updateBillStatus($bill): void
    {
        $totalDr = CustomerTransaction::where('reference_id', $bill->id)
            ->where('direction', 'DR')
            ->whereNotIn('charge_type', [9])
            ->sum('amount');

        $totalCr = CustomerTransaction::where('reference_id', $bill->id)
            ->where('direction', 'CR')
            ->whereNotIn('charge_type', [7, 8, 10])
            ->sum('amount');

        if (round($totalDr - $totalCr, 2) <= 0) {
            $bill->update(['status' => 2]);
        } else {
            $bill->update(['status' => 1]);
        }
    }

    private function getAvailableAdvance(int $memberEntryId): float
    {
        $cr = CustomerTransaction::where('member_entry_id', $memberEntryId)
            ->where('charge_type', 9)
            ->where('direction', 'CR')
            ->sum('amount');

        $dr = CustomerTransaction::where('member_entry_id', $memberEntryId)
            ->where('charge_type', 9)
            ->where('direction', 'DR')
            ->sum('amount');

        return max(0, floatval($cr - $dr));
    }
}
