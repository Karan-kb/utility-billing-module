<?php

namespace App\Services;

use App\Models\MahasulReceiptEntry;
use App\Models\VoucherSummary;
use App\Models\CustomerTransaction;
use App\Models\MemberEntry;
use App\Models\MeterReadingEntry;
use Illuminate\Support\Facades\DB;

class MahasulVoucherCancelService
{
    protected $meterReadingService;

    public function __construct()
    {
        $this->meterReadingService = new \App\Services\MeterReadingFindService();
    }

    /**
     * Cancel a Mahasul voucher with optional related advance-payment receipts
     *
     * @param VoucherSummary $voucher
     * @param string $reason
     * @param bool $cancelRelated Whether to cancel related advance-payment receipt
     * @return void
     */
    public function cancel(VoucherSummary $voucher, string $reason, bool $cancelRelated = false)
    {
        DB::transaction(function () use ($voucher, $reason, $cancelRelated) {
            
            $voucher->update([
                'status' => 3,
                'reason' => $reason,
            ]);

            // Reverse voucher details
            foreach ($voucher->voucherSummaryDetail as $detail) {
                \App\Models\VoucherSummaryDetail::create([
                    'voucher_summary_id' => $voucher->id,
                    'particulars' => $detail->particulars,
                    'debit' => $detail->credit,
                    'credit' => $detail->debit,
                    'account_head_id' => $detail->account_head_id,
                    'member_entry_id' => $detail->member_entry_id,
                    'is_cancelled' => true,
                ]);
            }

            // Handle Mahasul receipt
            if ($voucher->reference_type == 11) {
                $this->cancelMahasulReceipt($voucher->reference_id, $voucher->voucherSummaryDetail, $cancelRelated);
            }
        });
    }

    protected function cancelMahasulReceipt($receiptId, $voucherDetails, $cancelRelated)
    {
        $receipt = MahasulReceiptEntry::find($receiptId);
        if (!$receipt) return;

        // Detect related advance-payment receipts for the same meter reading
        $relatedReceipts = MahasulReceiptEntry::where('meter_reading_entry_id', $receipt->meter_reading_entry_id)
            ->where('id', '<>', $receipt->id)
            ->where('advance_payment', '>', 0)
            ->where('is_cancel', 0)
            ->get();

        // If related exists and user did NOT choose cancelRelated, return info
        if ($relatedReceipts->isNotEmpty() && !$cancelRelated) {
            throw new \Exception("This voucher has related Mahasul receipts created from advance payment.", 409);
        }

        // Cancel main receipt
        $this->processReceiptCancellation($receipt, $voucherDetails);

        // Cancel related receipts if requested
        if ($cancelRelated) {
            foreach ($relatedReceipts as $relReceipt) {
                $relVoucher = VoucherSummary::where('reference_type', 11)
                    ->where('reference_id', $relReceipt->id)
                    ->first();

                if ($relVoucher) {
                    $relVoucher->update(['status' => 3, 'reason' => 'Cancelled with related voucher']);
                }

                $this->processReceiptCancellation($relReceipt, collect());
            }
        }
    }

    protected function processReceiptCancellation(MahasulReceiptEntry $receipt, $voucherDetails)
    {
        // Soft delete customer transactions
        CustomerTransaction::where('receipt_id', $receipt->id)->delete();

        // Mark receipt canceled
        $receipt->update(['is_cancel' => 1]);

        // Blacklist members if needed
        $blacklistDetails = $voucherDetails->where('account_head_id', 20);
        foreach ($blacklistDetails as $detail) {
            if (!is_null($detail->member_entry_id)) {
                MemberEntry::where('id', $detail->member_entry_id)
                    ->update(['is_blacklisted' => 1]);
            }
        }

        // Revert meter readings
        $meterReadingIds = $this->meterReadingService->getMeterReadingIdsBetweenReceipts(
            $receipt->meter_issue_id,
            $receipt->id
        );

        MeterReadingEntry::whereIn('id', $meterReadingIds)
            ->where('status', 2)
            ->update(['status' => 1]);

        // Restore advance payment back to customer's balance
        if ($receipt->advance_payment > 0) {
            $memberEntryId = MeterReadingEntry::find($receipt->meter_reading_entry_id)->member_entry_id ?? null;
            if ($memberEntryId) {
                CustomerTransaction::create([
                    'member_entry_id' => $memberEntryId,
                    'amount' => $receipt->advance_payment,
                    'direction' => 'CR',
                    'charge_type' => 9,
                    'transaction_type' => 6,
                    'reference_id' => $receipt->id,
                ]);
            }
        }
    }
}