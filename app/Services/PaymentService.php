<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PaymentService
{

    public function transformPayments($payments)
    {
        $paymentByCash = false;
        $cashAmount = "0.00";
        $bankAmount = "0.00";
        $paymentByBank = false;
        $chequeNo = null;
        $bankId = null;
        $bankNameEn = null;
        $bankNameNp = null;

        foreach ($payments as $payment) {
            Log::debug("Payment key", $payment->toArray());
            if ($payment->payment_mode == 1) { // Cash
                $paymentByCash = true;
                $cashAmount = $payment->amount;
            } elseif ($payment->payment_mode == 2) { // Cheque/Bank
                $paymentByBank = true;
                $bankAmount = $payment->amount; // ← correct variable
                $chequeNo = $payment->cheque_no;
                $bankId = $payment->bank_id;
                $bankNameEn = $payment->bank?->name;
                $bankNameNp = $payment->bank?->name_np;
            }



        }

        return [
            'payment_by_cash' => $paymentByCash,
            'cash_amount' => $cashAmount,
            'bank_amount' => $bankAmount,
            'payment_by_bank' => $paymentByBank,
            'cheque_no' => $chequeNo,
            'bank_id' => $bankId,
            'bank_name_en' => $bankNameEn,
            'bank_name_np' => $bankNameNp,
        ];
    }


    public function create(
        int $referenceId,
        float $amount,
        int $paymentMode,
        array $options = []
    ): Payment {
        $data = [
            'reference_id' => $referenceId,
            'amount' => round($amount, 2),
            'payment_mode' => $paymentMode,
            'type' => $options['type'] ?? 0, // default type if not provided
            'cheque_no' => $options['cheque_no'] ?? null,
            'bank_id' => $options['bank_id'] ?? null,

        ];

        return Payment::create($data);
    }
    public function createPayments(int $referenceId, array $payments, int $type): array
    {
        $created = [];

        if (!empty($payments['cash_amount']) && $payments['cash_amount'] > 0) {
            $created[] = $this->create($referenceId, $payments['cash_amount'], 1, [
                'type' => $type
            ]);
        }

        if (!empty($payments['bank_amount']) && $payments['bank_amount'] > 0) {
            $created[] = $this->create($referenceId, $payments['bank_amount'], 2, [
                'type' => $type,
                'cheque_no' => $payments['cheque_no'] ?? null,
                'bank_id' => $payments['bank_id'] ?? null,
            ]);
        }

        return $created;
    }

    public function updatePayments(int $referenceId, array $payments, int $type): array
    {
        Payment::where('reference_id', $referenceId)
            ->where('type', $type)
            ->delete();

        $updated = [];
        if (!empty($payments['cash_amount']) && $payments['cash_amount'] > 0) {
            $updated[] = $this->create($referenceId, $payments['cash_amount'], 1, [
                'type' => $type
            ]);
        }
        if (!empty($payments['bank_amount']) && $payments['bank_amount'] > 0) {
            $updated[] = $this->create($referenceId, $payments['bank_amount'], 2, [
                'type' => $type,
                'cheque_no' => $payments['cheque_no'] ?? null,
                'bank_id' => $payments['bank_id'] ?? null,
            ]);
        }

        return $updated;
    }


    public function getByReference(int $type, int $referenceId)
{
    return Payment::where('type', $type)
        ->where('reference_id', $referenceId)
        ->latest()
        ->first();
}
}
