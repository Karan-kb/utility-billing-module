<?php

namespace App\Services;

use App\Models\CustomerTransaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CustomerTransactionService
{
    /**
     * Create customer transactions for a meter reading entry.
     *
     * @param int $memberEntryId
     * @param int $meterReadingEntryId
     * @param array $charges
     *        [
     *          'unit_amount' => 0,
     *          'minimum_demand' => 0,
     *          'service_charge' => 0,
     *          'subsidy_charge' => 0,
     *          'other_charge' => 0,
     *          'fine_amount' => 0,
     *        ]
     * @param string $transactionDate
     */
    public function createForMeterReading(int $memberEntryId, int $meterReadingEntryId, array $charges, string $transactionDate)
    {
        $timestamp = Carbon::now();
        $transactions = [];

        $mapping = [
            'unit_amount' => 6,      // energy charge
            'minimum_demand' => 1,   // demand charge
            'service_charge' => 2,   // service charge
            'subsidy_charge' => 4,   // subsidy
            'other_charge' => 5,     // other charge
            'fine_amount' => 3,      // fine
            'discount_amount' => 8,  //discount
        ];

        foreach ($mapping as $key => $chargeType) {
            if (!isset($charges[$key]) || $charges[$key] <= 0)
                continue;

            $direction = ($key === 'discount_amount') ? 'CR' : 'DR';

            $transactions[] = [
                'member_entry_id' => $memberEntryId,
                'transaction_date' => $transactionDate,
                'transaction_type' => 1,
                'charge_type' => $chargeType,
                'amount' => $charges[$key],
                'direction' => $direction,
                'reference_id' => $meterReadingEntryId,
                'created_at' => $timestamp->copy()->subSeconds(2),
                'updated_at' => now(),
            ];
        }

        if (!empty($transactions)) {
            CustomerTransaction::insert($transactions);

        }
    }


    public function createForOpeningMahasul(int $memberEntryId, int $meterReadingEntryId, array $charges, string $transactionDate)
    {
        $transactions = [];

        $existingTransaction = CustomerTransaction::where('reference_id', $meterReadingEntryId)->where('transaction_type', 5)->delete();


        $mapping = [
            'unit_amount' => 6,      // energy charge
            'demand_charge' => 1,   // demand charge
            'service_charge' => 2,   // service charge
            'subsidy_charge' => 4,   // subsidy
            'other_charge' => 5,     // other charge
            'fine_charge' => 3,      // fine
            'discount_amount' => 8,  //discount
        ];

        foreach ($mapping as $key => $chargeType) {
            if (!isset($charges[$key]) || $charges[$key] <= 0)
                continue;

            $direction = ($key === 'discount_amount') ? 'CR' : 'DR';

            $transactions[] = [
                'member_entry_id' => $memberEntryId,
                'transaction_date' => $transactionDate,
                'transaction_type' => 5,
                'charge_type' => $chargeType,
                'amount' => $charges[$key],
                'direction' => $direction,
                'reference_id' => $meterReadingEntryId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($transactions)) {
            CustomerTransaction::insert($transactions);

        }
    }
}
