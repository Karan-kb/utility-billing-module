<?php

namespace App\Services;

use App\Models\CustomerTransaction;

class MahasulReceiptTransactionService
{
    public function allocatePaidAmount(
        int $memberEntryId,
        int $receiptId,
        array $charges,
        float $paidAmount,
        string $transactionDate
    ): void {
        $remaining = $paidAmount;

        $priority = [
            'fine_amount'       => 3,
            'black_list_charge' => 7,
            'demand_charge'     => 1,
            'subsidy_charge'    => 4,
            'service_charge'    => 2,
            'other_charge'      => 5,
            'unit_amount'       => 6,
        ];

        $transactions = [];

        foreach ($priority as $key => $chargeType) {
            if ($remaining <= 0) break;

            $due = $charges[$key] ?? 0;
            if ($due <= 0) continue;

            $allocated = min($due, $remaining);

            $transactions[] = [
                'member_entry_id'  => $memberEntryId,
                'transaction_date' => $transactionDate,
                'transaction_type' => 2, // receipt
                'charge_type'      => $chargeType,
                'amount'           => $allocated,
                'direction'        => 'CR',
                'reference_id'     => $receiptId,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];

            $remaining -= $allocated;
        }

        if ($transactions) {
            CustomerTransaction::insert($transactions);
        }
    }
}

