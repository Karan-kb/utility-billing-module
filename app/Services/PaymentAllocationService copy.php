<?php

// namespace App\Services;

// use App\Models\MeterReadingEntry;
// use App\Models\MeterIssue;
// use App\Models\CustomerTransaction;

// class PaymentAllocationService
// {
//     public function allocate(
//         int $memberEntryId,
//         int $receiptId,
//         float $paidAmount,
//         string $transactionDate,
//         float $discountAmount = 0,
//         float $rebateAmount = 0,
//         float $disableDiscountAmount = 0,
//     ): void {
//         $remainingCash = $paidAmount + $discountAmount + $rebateAmount + $disableDiscountAmount;
//         $availableAdvance = $this->getAvailableAdvance($memberEntryId);
//         $totalUsableAmount = $remainingCash + $availableAdvance;
//         $advanceUsed = 0;

//         if($rebateAmount > 0 || $discountAmount > 0 || $disableDiscountAmount || $paidAmount == 0){
//         $priority = [
//             'unit_amount'       => 6,
//             'fine_amount'       => 3,
//             // 'black_list_charge' => 7,
//             'demand_charge'     => 1,
//             'subsidy_charge'    => 4,
//             'service_charge'    => 2,
//             'other_charge'      => 5,

//         ];
//     }else{
//         $priority = [
//             'fine_amount'       => 3,
//             // 'black_list_charge' => 7,
//             'demand_charge'     => 1,
//             'subsidy_charge'    => 4,
//             'service_charge'    => 2,
//             'other_charge'      => 5,
//             'unit_amount'       => 6,

//         ];
//     }


//         $bills = MeterReadingEntry::whereHas('meterIssue', fn ($q) =>
//             $q->where('member_entry_id', $memberEntryId)
//         )
//         ->whereIn('status', [0,1])
//         ->orderBy('reading_date_in_ad', 'asc')
//         ->get();


//         if ($discountAmount > 0) {
//             $this->allocateDiscountToBills($memberEntryId,$receiptId, $bills, $discountAmount, $transactionDate);
//         }
//         if ($rebateAmount > 0) {
//             $this->allocateRebateToBills($memberEntryId,$receiptId, $bills, $rebateAmount, $transactionDate);
//         }
//         if ($disableDiscountAmount > 0) {
//             $this->allocatedisableDiscountToBills($memberEntryId,$receiptId, $bills, $disableDiscountAmount, $transactionDate);
//         }


//         // foreach ($bills as $bill) {
//         //     if ($totalUsableAmount <= 0) break;
//         foreach ($bills as $bill) {

//             if (($remainingCash + $availableAdvance) <= 0) {
//                 break;
//             }


//             $billDue = $this->calculateBillDue($bill->id);
//             if ($billDue <= 0) continue;


//             $dr = CustomerTransaction::where('reference_id', $bill->id)
//                 ->where('direction', 'DR')
//                 ->selectRaw('charge_type, SUM(amount) as amount')
//                 ->groupBy('charge_type')
//                 ->get()
//                 ->keyBy('charge_type')
//                 ->map(function($item) {
//                     return floatval($item->amount);
//                 })
//                 ->toArray();


//             $cr = CustomerTransaction::where('reference_id', $bill->id)
//                 ->where('direction', 'CR')
//                 ->selectRaw('charge_type, SUM(amount) as amount')
//                 ->groupBy('charge_type')
//                 ->get()
//                 ->keyBy('charge_type')
//                 ->map(function($item) {
//                     return floatval($item->amount);
//                 })
//                 ->toArray();


//             foreach ($priority as $key => $chargeType) {
//                 if ($totalUsableAmount <= 0) break;

//                 $due = ($dr[$chargeType] ?? 0) - ($cr[$chargeType] ?? 0);
//                 if ($due <= 0) continue;


//                 $cashToUse = min($due, $remainingCash);

//                 if ($cashToUse > 0) {
//                     CustomerTransaction::create([
//                         'member_entry_id'  => $memberEntryId,
//                         'transaction_date' => $transactionDate,
//                         'transaction_type' => 2,
//                         'charge_type'      => $chargeType,
//                         'amount'           => $cashToUse,
//                         'direction'        => 'CR',
//                         'reference_id'     => $bill->id,
//                         'receipt_id'     => $receiptId,
//                     ]);

//                     $remainingCash -= $cashToUse;
//                     $due -= $cashToUse;
//                 }


//                 if ($due > 0 && $remainingCash <= 0 && $availableAdvance > 0) {
//                     $advanceToUse = min($due, $availableAdvance);

//                     if ($advanceToUse > 0) {

//                         // Apply advance to bill
//                         CustomerTransaction::create([
//                             'member_entry_id'  => $memberEntryId,
//                             'transaction_date' => $transactionDate,
//                             'transaction_type' => 2,
//                             'charge_type'      => $chargeType,
//                             'amount'           => $advanceToUse,
//                             'direction'        => 'CR',
//                             'reference_id'     => $bill->id,
//                             'receipt_id'       => $receiptId,
//                         ]);

//                         $availableAdvance -= $advanceToUse;
//                         $advanceUsed += $advanceToUse;
//                     }
//                 }

//                 $totalUsableAmount = $remainingCash + $availableAdvance;
//             }


//             $this->updateBillStatus($bill);
//             $billDueAfter = $this->calculateBillDue($bill->id);

//             if ($billDueAfter > 0) {
//                 break;
//             }
//         }


//         if ($remainingCash > 0) {
//             CustomerTransaction::create([
//                 'member_entry_id'  => $memberEntryId,
//                 'transaction_date' => $transactionDate,
//                 'transaction_type' => 2,
//                 'charge_type'      => 9, // advance payment
//                 'amount'           => $remainingCash,
//                 'direction'        => 'CR',
//                 'reference_id'     => null,
//                 'receipt_id'     => $receiptId,
//             ]);
//         }
//         if ($advanceUsed > 0) {
//             CustomerTransaction::create([
//                 'member_entry_id'  => $memberEntryId,
//                 'transaction_date' => $transactionDate,
//                 'transaction_type' => 2,
//                 'charge_type'      => 9,
//                 'amount'           => $advanceUsed,
//                 'direction'        => 'DR',
//                 'due'              => NULL,
//                 'reference_id'     => null,
//                 'receipt_id'       => $receiptId,
//             ]);
//         }
//     }


//     private function allocateDiscountToBills(
//         int $memberEntryId,
//         int $receiptId,
//         $bills,
//         float $totalDiscount,
//         string $transactionDate,
//     ): void {

//         $remainingDiscount = $totalDiscount;

//         foreach ($bills as $bill) {
//             if ($remainingDiscount <= 0) {
//                 break;
//             }

//             $billDue = $this->calculateBillDue($bill->id);
//             if ($billDue <= 0) {
//                 continue;
//             }

//             $discountForThisBill = min($billDue, $remainingDiscount);

//             CustomerTransaction::create([
//                 'member_entry_id'  => $memberEntryId,
//                 'transaction_date' => $transactionDate,
//                 'transaction_type' => 2,
//                 'charge_type'      => 8, // Discount
//                 'amount'           => $discountForThisBill,
//                 'direction'        => 'CR',
//                 'reference_id'     => $bill->id,
//                 'receipt_id'       => $receiptId,
//             ]);

//             $remainingDiscount -= $discountForThisBill;
//         }

//         // If discount still remains, treat it as advance
//         if ($remainingDiscount > 0) {
//             CustomerTransaction::create([
//                 'member_entry_id'  => $memberEntryId,
//                 'transaction_date' => $transactionDate,
//                 'transaction_type' => 2,
//                 'charge_type'      => 9, // Advance
//                 'amount'           => $remainingDiscount,
//                 'direction'        => 'CR',
//                 'reference_id'     => null,
//                 'receipt_id'       => $receiptId,
//             ]);
//         }
//     }

//     private function allocateRebateToBills(
//         int $memberEntryId,
//         int $receiptId,
//         $bills,
//         float $totalRebate,
//         string $transactionDate,
//     ): void {

//         $remainingRebate = $totalRebate;

//         foreach ($bills as $bill) {
//             if ($remainingRebate <= 0) {
//                 break;
//             }

//             $billDue = $this->calculateBillDue($bill->id);
//             if ($billDue <= 0) {
//                 continue;
//             }

//             $rebateForThisBill = min($billDue, $remainingRebate);

//             CustomerTransaction::create([
//                 'member_entry_id'  => $memberEntryId,
//                 'transaction_date' => $transactionDate,
//                 'transaction_type' => 2,
//                 'charge_type'      => 7, // Rebate
//                 'amount'           => $rebateForThisBill,
//                 'direction'        => 'CR',
//                 'reference_id'     => $bill->id,
//                 'receipt_id'       => $receiptId,
//             ]);

//             $remainingRebate -= $rebateForThisBill;
//         }

//         // Remaining rebate becomes advance
//         if ($remainingRebate > 0) {
//             CustomerTransaction::create([
//                 'member_entry_id'  => $memberEntryId,
//                 'transaction_date' => $transactionDate,
//                 'transaction_type' => 2,
//                 'charge_type'      => 9,
//                 'amount'           => $remainingRebate,
//                 'direction'        => 'CR',
//                 'reference_id'     => null,
//                 'receipt_id'       => $receiptId,
//             ]);
//         }
//     }


//     private function allocatedisableDiscountToBills(
//         int $memberEntryId,
//         int $receiptId,
//         $bills,
//         float $totalDisableDiscount,
//         string $transactionDate,
//     ): void {

//         $remainingDisableDiscount = $totalDisableDiscount;

//         foreach ($bills as $bill) {
//             if ($remainingDisableDiscount <= 0) {
//                 break;
//             }

//             $billDue = $this->calculateBillDue($bill->id);
//             if ($billDue <= 0) {
//                 continue;
//             }

//             $disableDiscountForThisBill = min($billDue, $remainingDisableDiscount);

//             CustomerTransaction::create([
//                 'member_entry_id'  => $memberEntryId,
//                 'transaction_date' => $transactionDate,
//                 'transaction_type' => 2,
//                 'charge_type'      => 10,
//                 'amount'           => $disableDiscountForThisBill,
//                 'direction'        => 'CR',
//                 'reference_id'     => $bill->id,
//                 'receipt_id'       => $receiptId,
//             ]);

//             $remainingDisableDiscount -= $disableDiscountForThisBill;
//         }


//         if ($remainingDisableDiscount > 0) {
//             CustomerTransaction::create([
//                 'member_entry_id'  => $memberEntryId,
//                 'transaction_date' => $transactionDate,
//                 'transaction_type' => 2,
//                 'charge_type'      => 9,
//                 'amount'           => $remainingDisableDiscount,
//                 'direction'        => 'CR',
//                 'reference_id'     => null,
//                 'receipt_id'       => $receiptId,
//             ]);
//         }
//     }


//     private function calculateBillDue(int $billId): float
//     {
//         $totalDr = CustomerTransaction::where('reference_id', $billId)
//             ->where('direction', 'DR')
//             ->whereNotIn('charge_type', [9])
//             ->sum('amount');

//         $totalCr = CustomerTransaction::where('reference_id', $billId)
//             ->where('direction', 'CR')
//             ->whereNotIn('charge_type', [7,8,10])
//             ->sum('amount');

//         return max(0, floatval($totalDr - $totalCr));
//     }


//     private function updateBillStatus(MeterReadingEntry $bill): void
//     {
//         $totalDr = CustomerTransaction::where('reference_id', $bill->id)
//             ->where('direction', 'DR')
//             ->whereNotIn('charge_type', [9])
//             ->sum('amount');

//         $totalCr = CustomerTransaction::where('reference_id', $bill->id)
//             ->where('direction', 'CR')
//             ->whereNotIn('charge_type', [7,8,10])
//             ->sum('amount');

//         if ($totalCr >= $totalDr) {
//             $bill->update(['status' => 2]); // Fully paid
//         } else {
//             $bill->update(['status' => 1]); // Partially paid
//         }
//     }




//     private function getAvailableAdvance(int $memberEntryId): float
//     {
//         $cr = CustomerTransaction::where('member_entry_id', $memberEntryId)
//             ->where('charge_type', 9)
//             ->where('direction', 'CR')
//             ->sum('amount');

//         $dr = CustomerTransaction::where('member_entry_id', $memberEntryId)
//             ->where('charge_type', 9)
//             ->where('direction', 'DR')
//             ->sum('amount');



//         return max(0, floatval($cr - $dr));
//     }
// }