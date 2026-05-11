<?php

namespace App\Services;

use App\Models\CustomerTransaction;
use App\Models\MeterReadingEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentAllocationService
{
    /**
     * Allocate payments and create customer transactions
     *
     * @param int $memberEntryId
     * @param int $receiptId
     * @param float $paidAmount
     * @param string $transactionDate
     * @param float $rebateAmount
     * @param float $discountAmount
     * @param float $disableDiscountAmount
     * @param MeterReadingEntry $currentReading
     */



public function allocate(
    int $memberEntryId,
    int $receiptId,
    float $paidAmount, // this is available advance
    string $transactionDate,
    float $rebateAmount = 0, // unused now
    float $discountAmount = 0, // unused
    float $disableDiscountAmount = 0, // unused
    MeterReadingEntry $currentReading
): void {

    DB::connection('tenant')->transaction(function () use ($memberEntryId, $receiptId, $paidAmount, $transactionDate, $currentReading) {

        $allocationPool = round($paidAmount, 2);
        $priority = [3, 1, 2, 4, 5, 6];
        $remainingPool = $allocationPool;
        $totalCrCreated = 0;

        foreach ($priority as $chargeType) {
            $totalDr = CustomerTransaction::where('member_entry_id', $memberEntryId)
                ->where('charge_type', $chargeType)
                ->where('direction', 'DR')
                ->sum('amount');

            $totalCr = CustomerTransaction::where('member_entry_id', $memberEntryId)
                ->where('charge_type', $chargeType)
                ->where('direction', 'CR')
                ->sum('amount');

            $outstanding = max(0, round($totalDr - $totalCr, 2));
            if ($outstanding <= 0 || $remainingPool <= 0) continue;

            $amountToAllocate = min($outstanding, $remainingPool);

            if ($amountToAllocate > 0) {
                $this->createCrRecord($memberEntryId, $receiptId, $chargeType, $amountToAllocate, $transactionDate, $currentReading);
                $remainingPool = round($remainingPool - $amountToAllocate, 2);
                $totalCrCreated = round($totalCrCreated + $amountToAllocate, 2);
            }
        }

        // Advance used = total CR created
        $advanceUsed = max(0, $totalCrCreated);

        if ($advanceUsed > 0) {
            CustomerTransaction::create([
                'member_entry_id' => $memberEntryId,
                'transaction_date' => $transactionDate,
                'transaction_type' => 2,
                'charge_type' => 9,
                'amount' => $advanceUsed,
                'direction' => 'DR',
                'reference_id' => $currentReading->id,
                'receipt_id' => $receiptId,
            ]);
        }

        $this->updateBillStatus($currentReading);
    });
}

    /**
     * Create credit record for a charge type
     */
    private function createCrRecord(int $memberEntryId, int $receiptId, int $chargeType, float $amount, string $transactionDate, MeterReadingEntry $reading): void
    {
        CustomerTransaction::create([
            'member_entry_id' => $memberEntryId,
            'transaction_date' => $transactionDate,
            'transaction_type' => 2,
            'charge_type' => $chargeType,
            'amount' => round($amount, 2),
            'direction' => 'CR',
            'reference_id' => $reading->id,
            'receipt_id' => $receiptId,
        ]);

        Log::info("Created CR record", [
            'charge_type' => $chargeType,
            'amount' => $amount,
            'receipt_id' => $receiptId
        ]);
    }

    /**
     * Get available advance for a member
     */
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

        return max(0, round($cr - $dr, 2));
    }

    /**
     * Update bill status based on payments
     */
    private function updateBillStatus(MeterReadingEntry $reading): void
    {
        $totalDr = CustomerTransaction::where('reference_id', $reading->id)
            ->where('direction', 'DR')
            ->sum('amount');

        $totalCr = CustomerTransaction::where('reference_id', $reading->id)
            ->where('direction', 'CR')
            ->sum('amount');

        $difference = round($totalDr - $totalCr, 2);

        Log::info('Updating bill status', [
            'reading_id' => $reading->id,
            'total_dr' => $totalDr,
            'total_cr' => $totalCr,
            'difference' => $difference
        ]);

        // Status: 0 = Unpaid, 1 = Partially Paid, 2 = Fully Paid
        if ($difference <= 0) {
            $reading->status = 2; // Fully Paid
        } elseif ($totalCr > 0) {
            $reading->status = 1; // Partially Paid
        } else {
            $reading->status = 0; // Unpaid
        }

        $reading->save();
    }

    /**
     * Verify allocation totals
     */
    private function verifyAllocation(int $memberEntryId, int $receiptId, float $totalCr, float $rebate, float $disableDiscount, float $advanceUsed): void
    {
        // Get all transactions for this receipt
        $transactions = CustomerTransaction::where('receipt_id', $receiptId)->get();

        $totalDebit = $transactions->where('direction', 'DR')->sum('amount');
        $totalCredit = $transactions->where('direction', 'CR')->sum('amount');

        // Debits should equal credits
        if (abs($totalDebit - $totalCredit) > 0.01) {
            Log::warning('Allocation imbalance detected', [
                'receipt_id' => $receiptId,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'difference' => $totalDebit - $totalCredit
            ]);
        }

        // Verify advance used formula
        $expectedAdvanceUsed = round($totalCr - $rebate - $disableDiscount, 2);
        if (abs($expectedAdvanceUsed - $advanceUsed) > 0.01) {
            Log::warning('Advance used calculation mismatch', [
                'expected' => $expectedAdvanceUsed,
                'actual' => $advanceUsed,
                'total_cr' => $totalCr,
                'rebate' => $rebate,
                'disable_discount' => $disableDiscount
            ]);
        }
    }
}