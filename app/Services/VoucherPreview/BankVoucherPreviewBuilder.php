<?php

namespace App\Services\VoucherPreview;

use App\Models\BankVoucher;
use App\Models\AccountHead;
use App\Services\PaymentService;
use App\Services\DateConverterService;

class BankVoucherPreviewBuilder
{
    public function build(
        $voucher,
        PaymentService $paymentService,
        DateConverterService $dateService
    ): array {

        // Load BankVoucher
        $bankVoucher = BankVoucher::findOrFail($voucher->reference_id);

        $payment = $paymentService->getByReference(13, $bankVoucher->id);

        $bankName = null;
        $chequeNo = null;
        $bankId = null;

        if ($payment) {
            $bankId = $payment->bank_id ?? null;
            $chequeNo = $payment->cheque_no ?? null;

            if ($bankId) {
                $bank = AccountHead::find($bankId);
                $bankName = $bank->name ?? null;
            }
        }

        $typeText = match ($bankVoucher->type) {
            1 => 'Bank Deposit',
            2 => 'Bank Withdraw',
            default => 'Unknown',
        };

        return [
            'voucher_id' => $voucher->id,
            'voucher_no' => $voucher->voucher_no,

            'date_in_ad' => $bankVoucher->created_at->format('Y-m-d'),
            'date_in_bs' => $dateService->adToBs($bankVoucher->created_at),

            'reference_type' => config('voucher.' . $voucher->reference_type),
            'reference_id' => $voucher->reference_id,
            'is_cancel' => $voucher->is_cancel,

            'fiscal_year_id' => $bankVoucher->fiscal_year_id,

            
            'type' => $typeText,
                'bank_name' => $bankName,
                'cheque_no' => $chequeNo,
            'amount' => $bankVoucher->amount,
            'balance_after' => $bankVoucher->balance_after,
            'remarks' => $bankVoucher->remarks,


        ];
    }
}