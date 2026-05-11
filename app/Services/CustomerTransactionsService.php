<?php

namespace App\Services;

use App\Models\CustomerTransaction;

class CustomerTransactionsService
{
    public function createForOpeningMahasul($memberEntryId, $meterReadingEntryId, $charges, $readingDateAd)
    {
        // Calculate total amount from charges
        $totalAmount = array_sum($charges);
        
        return CustomerTransaction::create([
            'member_entry_id' => $memberEntryId,
            'reference_id' => $meterReadingEntryId, // Use the passed ID directly
            'transaction_date' => $readingDateAd,
            'transaction_type' => 5, // Opening Mahasul type
            'charge_type' => 8,
            'amount' => $totalAmount,
            'direction' => 'DR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}