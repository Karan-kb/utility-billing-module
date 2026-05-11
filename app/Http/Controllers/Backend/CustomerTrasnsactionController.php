<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\Helper;
use App\Helpers\NepaliCalendar;
use App\Http\Controllers\Controller;
use App\Models\CustomerTransaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\MahasulReceiptEntry;
use App\Models\MeterReadingEntry;

class CustomerTrasnsactionController extends Controller
{
    public function ListAllTransactionbyChargeType($id)
    {
        try {
            $memberEntryId = $id;

            // First collect ALL receipt data including normal and cancelled (independent of bills)
            $allReceiptData = $this->getAllReceiptData($memberEntryId);

            $rows = CustomerTransaction::where('member_entry_id', $memberEntryId)
                ->orderBy('created_at')
                ->get();

            if ($rows->isEmpty() && $allReceiptData->isEmpty()) {
                return response()->json([
                    'message' => 'Data Not Found',
                    'error' => 'No Ledger data'
                ], 404);
            }

            $result = collect();

            // Add bill rows (meter readings, opening balance, fines)
            $billRows = $rows
                ->where('direction', 'DR')
                ->whereIn('transaction_type', [1, 5])
                ->whereNotIn('charge_type', [9])
                //->groupBy(fn($r) => $r->reference_id . '|' . $r->created_at);
                ->groupBy('reference_id');
            foreach ($billRows as $items) {
                $first = $items->first();

                if ($items->every(fn($i) => $i->charge_type == 3)) {
                    continue;
                }

                $billAmount = $items
                    ->whereNotIn('charge_type', [7, 8, 3, 11])
                    ->sum('amount');

                $fineAmount = $items
                    ->whereIn('charge_type', [3])
                    ->sum('amount');
                $fineAmountOpening = 0;

                // Only consider fineAmount if transaction_type is 5 (Opening Mahasul)
                    $fineAmountOpening = $items
                        ->whereIn('charge_type', [3])
                        ->where('transaction_type', 5)
                        ->sum('amount');
                $nepaliMonths = [
                    1 => 'बैशाख',
                    2 => 'जेठ',
                    3 => 'असार',
                    4 => 'श्रावण',
                    5 => 'भदौ',
                    6 => 'आश्विन',
                    7 => 'कार्तिक',
                    8 => 'मंसिर',
                    9 => 'पौष',
                    10 => 'माघ',
                    11 => 'फाल्गुन',
                    12 => 'चैत्र'
                ];
                $month = $nepaliMonths[($this->getMeterReadingDetail($first->reference_id))->reading_month_in_bs] ?? '';
                $meterReading = $this->getMeterReadingDetail($first->reference_id);
                $result->push([
                    'reference_id' => $first->reference_id,
                    'receipt_id' => null,
                    'voucher_no' => $first->voucher_no ?? $first->reference_id,
                    'transaction_type' => (int) $first->transaction_type,
                    'type_name' => ($this->getMeterReadingDetail($first->reference_id))->entry_type == 1 ? "Meter Reading {$month}" : 'Opening Mahasul',
                    'bill_amount' => (float) ($billAmount),
                    'bill_fine' => $meterReading->entry_type == 2 ? $fineAmountOpening : 0,
                    'paid_amount' => 0,
                    'fine_amount' => 0,
                    'rebate_amount' => 0,
                    'discount_amount' => 0,
                    'advance_dr' => 0,
                    'advance_cr_receipt' => 0,
                    'advance_cr' => 0,
                    'created_at' => $first->created_at,
                    'date' => optional($first->created_at)->format('Y-m-d'),
                ]);
            }

           
            $fineRowsGrouped = $rows
            ->where('direction', 'DR')
            ->whereIn('charge_type', [3, 11])
            ->whereNotIn('transaction_type', [5])
            ->groupBy(function ($item) {
                $date = Carbon::parse($item->created_at)->format('Y-m-d');
                return $item->reference_id . '|' . $item->charge_type . '|' . $date;
            });

        foreach ($fineRowsGrouped as $key => $fines) {
            $first = $fines->first();
            $totalAmount = $fines->sum('amount');

            //$typeName = $first->charge_type == 11 ? 'Blacklist Charge' : 'Fine Post';
            $typeName = match (true) {
                $first->charge_type == 11 => 'Blacklist Charge',
                !is_null($first->due) && $first->due > 0 => 'Fine Post from due of mahasul',
                default => 'Fine Post',
            };
            $result->push([
                'reference_id' => $first->reference_id,
                'receipt_id' => null,
                'voucher_no' => $first->voucher_no ?? $first->reference_id,
                'transaction_type' => 1,
                'type_name' => $typeName,
                'bill_amount' => 0,
                'bill_fine' => 0,
                'paid_amount' => 0,
                'fine_amount' => (float) $totalAmount,
                'rebate_amount' => 0,
                'discount_amount' => 0,
                'advance_dr' => 0,
                'advance_cr_receipt' => 0,
                'advance_cr' => 0,
                'created_at' => $first->created_at,
                'date' => Carbon::parse($first->created_at)->format('Y-m-d'),
            ]);
        }

            // Add advance payments
            $advanceRows = $rows
                ->where('charge_type', 9)
                ->whereNull('receipt_id');

            foreach ($advanceRows as $adv) {
                $existsInReceipt = $rows
                    ->where('created_at', $adv->created_at)
                    ->whereNotNull('receipt_id')
                    ->isNotEmpty();
                $adv_amount = $adv->amount;

                if ($existsInReceipt) {
                    continue;
                }

                $result->push([
                    'reference_id' => $adv->reference_id,
                    'receipt_id' => null,
                    'voucher_no' => null,
                    'transaction_type' => 2,
                    'type_name' => 'Advance Payment',
                    'bill_amount' => 0,
                    'bill_fine' => 0,
                    'paid_amount' => 0,
                    'fine_amount' => 0,
                    'rebate_amount' => 0,
                    'discount_amount' => 0,
                    'advance_dr' => 0,
                    'advance_cr_receipt' => 0,
                    'advance_cr' => (float) $adv->amount ?? 0,
                    'created_at' => $adv->created_at,
                    'date' => optional($adv->created_at)->format('Y-m-d'),
                ]);
            }

            // Add ALL receipt data (normal + cancelled) collected earlier
            $result = $result->merge($allReceiptData);

            // $sorted = $result
            //     ->sortBy(fn($row) => Carbon::parse($row['created_at'])->timestamp)
            //     ->values();
            $sortedChronological = $result
                ->sortBy(fn($row) => Carbon::parse($row['created_at'])->timestamp)
                ->values();

            $sorted = collect();

            foreach ($sortedChronological as $row) {

                // If NOT cancelled → just push
                if ($row['type_name'] !== 'cancelled') {
                    $sorted->push($row);
                    continue;
                }

                // If cancelled → find its receipt and insert just after it
                $index = $sorted->search(function ($item) use ($row) {
                    return $item['receipt_id'] === $row['receipt_id']
                        && $item['type_name'] !== 'cancelled';
                });

                if ($index !== false) {
                    $sorted->splice($index + 1, 0, [$row]);
                } else {
                    $sorted->push($row);
                }
            }


            $runningBalance = 0;

            


            $final = $sorted->map(function ($row) use (&$runningBalance) {

                // DR increases balance
                $dr = ($row['bill_amount'] ?? 0)
                    + ($row['fine_amount'] ?? 0)
                    + ($row['bill_fine'] ?? 0);

                // CR decreases balance
                $cr = ($row['paid_amount'] ?? 0)
                    + ($row['discount_amount'] ?? 0)
                    + ($row['rebate_amount'] ?? 0)
                    + ($row['advance_cr'] ?? 0);

                $runningBalance = $runningBalance + $dr - $cr;

                // Round everything to 2 decimals (KEEP FLOAT)
                $row['bill_amount'] = round((float) ($row['bill_amount'] ?? 0), 2);
                $row['bill_fine'] = round((float) ($row['bill_fine'] ?? 0), 2);
                $row['paid_amount'] = round((float) ($row['paid_amount'] ?? 0), 2);
                $row['fine_amount'] = round((float) ($row['fine_amount'] ?? 0), 2);
                $row['rebate_amount'] = round((float) ($row['rebate_amount'] ?? 0), 2);
                $row['discount_amount'] = round((float) ($row['discount_amount'] ?? 0), 2);
                $row['advance_dr'] = round((float) ($row['advance_dr'] ?? 0), 2);
                $row['advance_cr_receipt'] = round((float) ($row['advance_cr_receipt'] ?? 0), 2);
                $row['advance_cr'] = round((float) ($row['advance_cr'] ?? 0), 2);

                $row['balance_amount'] = round((float) $runningBalance, 2);

                return $row;
            });
            // $data = $final
            //     ->sortBy(fn($row) => Carbon::parse($row['created_at'])->timestamp)
            //     ->values();
            $data = $final->values();

            return response()->json(['data' => $data], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'No active member found matching your search criteria.',
                'error' => 'Member Not Found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function getAllReceiptData($memberEntryId)
    {
        $result = collect();

        $receiptGroups = CustomerTransaction::where('member_entry_id', $memberEntryId)
            ->where('transaction_type', 2)
            ->whereNotNull('receipt_id')
            ->withTrashed()
            ->orderBy('created_at')
            ->get()
            ->groupBy('receipt_id');

        foreach ($receiptGroups as $receiptId => $items) {
            $rebate = $items->where('charge_type', 7)->sum('amount');
            $discount = $items->where('charge_type', 8)->sum('amount');

            $advanceCr = $items
                ->where('charge_type', 9)
                ->where('direction', 'CR')
                ->sum('amount');

            $advanceDr = $items
                ->where('charge_type', 9)
                ->where('direction', 'DR')
                ->sum('amount');

            $paidAmount = $items
                ->where('direction', 'CR')
                // ->whereNotIn('charge_type', [7])
                ->sum('amount');

            $paidAmount = $paidAmount - ($rebate + $discount + $advanceDr);

            $first = $items->first();
            $voucherNo = $this->getVocuherByReceiptId($receiptId);

            /*
            |-------------------------------------------------
            | ALWAYS add Normal Receipt Row FIRST
            |-------------------------------------------------
            */
            $result->push([
                'reference_id' => $first->reference_id,
                'receipt_id' => $receiptId,
                'voucher_no' => $voucherNo,
                'transaction_type' => 2,
                'type_name' => "Mahasul Receipt" . ($advanceDr > 0 ? '(Advance Used)' : ''),
                'bill_amount' => 0,
                'bill_fine' => 0,
                'paid_amount' => (float) $paidAmount,
                'fine_amount' => 0,
                'rebate_amount' => (float) $rebate,
                'discount_amount' => (float) $discount,
                'advance_dr' =>-1 * (float) $advanceDr,
                'advance_cr_receipt' => $advanceCr,
                'advance_cr' => 0,
                'created_at' => $first->created_at,
                'date' => optional($first->created_at)->format('Y-m-d'),
            ]);

            /*
            |-------------------------------------------------
            | Add Cancellation Row if cancelled - CHECK TRASHED RECORDS
            |-------------------------------------------------
            */
            $trashedReceiptItems = CustomerTransaction::where('member_entry_id', $memberEntryId)
                ->where('receipt_id', $receiptId)
                ->onlyTrashed()  // ONLY trashed records
                ->exists();

            $voucher = \App\Models\VoucherSummary::where('reference_id', $receiptId)
                ->where('reference_type', 11)
                ->where('status', 3) // Cancelled
                ->first();

            if ($voucher || $trashedReceiptItems) {  // Show cancelled if voucher cancelled OR transactions soft deleted
                $result->push([
                    'reference_id' => $first->reference_id,
                    'receipt_id' => $receiptId,
                    'voucher_no' => $voucherNo,
                    'transaction_type' => 2,
                    'type_name' => 'cancelled',
                    'bill_amount' => 0,
                    'bill_fine' => 0,
                    'paid_amount' => -1 * (float) $paidAmount,
                    'fine_amount' => 0,
                    'rebate_amount' => -1 * (float) $rebate,
                    'discount_amount' => -1 * (float) $discount,
                    'advance_dr' => -1 * $advanceDr,
                    'advance_cr_receipt' => -1 * $advanceCr,
                    'advance_cr' => 0,
                    'created_at' => $voucher->updated_at ?? $first->created_at,
                    'date' => optional($voucher->updated_at ?? $first->created_at)->format('Y-m-d'),
                ]);
            }
        }

        return $result;
    }

    public function getVocuherByReceiptId($id)
    {
        return MahasulReceiptEntry::where('id', $id)
            ->value('voucher_no') ?? 'N/A';
    }

    public function getMeterReadingDetail($id)
    {
        return MeterReadingEntry::where('id', $id)
            ->select('id', 'entry_type', 'reading_month_in_bs')
            ->first();
    }
}
