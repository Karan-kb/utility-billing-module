<?php

namespace App\Services;

use App\Models\NEAPurchase;
use App\Models\NEAPaymentEntry;

class NeaPaymentCalculationService
{
    public static function calculateTotalAmount(
    int $transformerId,
    int $month,
    float $fineAmount = 0,
    float $rebateAmount = 0
): array {
    // Get latest payment (not for current month)
    $latestPayment = NEAPaymentEntry::on('tenant')
        ->whereNull('deleted_at')
        ->where('is_cancel', 0)
        ->where('transformer_id', $transformerId)
        ->orderByDesc('created_at')
        ->first();

    $previousDueAmount = $latestPayment ? (float)$latestPayment->due_amount : 0;

    // Get purchase amount for the month
    $purchase = NEAPurchase::on('tenant')
        ->whereNull('deleted_at')
        ->where('transformer_id', $transformerId)
        ->where('month', $month)
        ->first();

    $purchaseAmount = $purchase ? (float)$purchase->amount : 0;

    // Base total_amount = purchase + previous due
    $totalAmount = $purchaseAmount + $previousDueAmount;

    // Add fine and subtract rebate
    $totalAmount += $fineAmount;
    $totalAmount -= $rebateAmount;

    if ($totalAmount < 0) {
        $totalAmount = 0;
    }

    return [
        'purchase_amount'     => $purchaseAmount,
        'previous_due_amount' => $previousDueAmount,
        'total_amount'        => $totalAmount,
    ];
}
    /**
     * Validate paid amount against total amount
     *
     * @param float $paidAmount
     * @param float $totalAmount
     * @return float due amount
     */
    public static function validatePaidAmount(float $paidAmount, float $totalAmount): float
    {
        if ($paidAmount > $totalAmount) {
            throw new \InvalidArgumentException("Paid amount cannot be greater than total amount ($totalAmount).");
        }

        // Remaining due
        return max(0, $totalAmount - $paidAmount);
    }
}