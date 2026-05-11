<?php

namespace App\Services;

use App\Models\AccountHead;
use Illuminate\Http\Request;
use App\Models\MasterSetup;

class PaymentValidationService
{

    private static function getTotal(Request $request)
    {
        if ($request->has('paid_amount')) {
            return (float) $request->input('paid_amount');
        }
        return $request->input('amount', 0);
    }


    public static function validatePaymentMethods(Request $request, $attribute, $value, $fail)
    {
        $cash = (bool) $request->input('payment_by_cash', false);
        $bank = (bool) $request->input('payment_by_bank', false);

        if (!$cash && !$bank) {
            $fail('At least one of payment_by_cash or payment_by_bank must be true.');
        }
    }

    // public static function validateBank(Request $request, $attribute, $value, $fail)
    // {
    //     if (!MasterSetup::on('tenant')->where('id', $value)->where('master_setup_type_id', 8)->exists()) {
    //         $fail("The selected {$attribute} is invalid. Must be a bank (type_id = 8).");
    //     }
    // }
public static function validateBank(Request $request, $attribute, $value, $fail)
{
    if (!AccountHead::on('tenant')
        ->where('id', $value)
        ->where('account_group_id', 10)
        ->where('is_active', 1)
        ->exists()) {
            
        $fail("The selected {$attribute} is invalid. Must be a bank in account head whose account group must be Bank Accounts.");
    }
}



    public static function validateCashAmount(Request $request, $value, $fail)
    {
        $total = self::getTotal($request);
        $cash = (bool) $request->input('payment_by_cash', false);
        $bank = (bool) $request->input('payment_by_bank', false);
        $bankAmount = (float) $request->input('bank_amount', 0);

        if ($cash) {
            if ($bank && ($value + $bankAmount) != $total) {
                $fail("The sum of cash_amount and bank_amount must equal the amount ($total).");
            } elseif (!$bank && $value != $total) {
                $fail("cash_amount must be equal to the amount ($total).");
            }
        } elseif ($value > 0) {
            $fail("cash_amount cannot be greater than 0 if payment_by_cash is not selected.");
        }

        if ($value == 0 && $bankAmount == 0) {
            $fail("Both cash_amount and bank_amount cannot be zero.");
        }
    }

    public static function validateBankAmount(Request $request, $value, $fail)
    {
        $total = self::getTotal($request);
        $cash = (bool) $request->input('payment_by_cash', false);
        $bank = (bool) $request->input('payment_by_bank', false);
        $cashAmount = (float) $request->input('cash_amount', 0);

        if ($bank) {
            if ($cash && ($value + $cashAmount) != $total) {
                $fail("The sum of cash_amount and bank_amount must equal the amount ($total).");
            } elseif (!$cash && $value != $total) {
                $fail("bank_amount must be equal to the amount ($total).");
            }
        } elseif ($value > 0) {
            $fail("bank_amount cannot be greater than 0 if payment_by_bank is not selected.");
        }

        if ($value == 0 && $cashAmount == 0) {
            $fail("Both cash_amount and bank_amount cannot be zero.");
        }
    }


    public static function validateCashAmountWithServiceCharge(Request $request, $value, $fail)
    {
        $total = $request->input('amount', 0) - $request->input('service_charge', 0);
        $cash = (bool) $request->input('payment_by_cash', false);
        $bank = (bool) $request->input('payment_by_bank', false);
        $bankAmount = $request->input('bank_amount', 0);

        if ($cash) {
            if ($bank) {
                if (($value + $bankAmount) != $total) {
                    $fail("The sum of cash_amount and bank_amount must equal the meter deposit amount ($total).");
                }
            } else {
                if ($value != $total) {
                    $fail("cash_amount must be equal to the meter deposit amount ($total).");
                }
            }
        } elseif ($value > 0) {
            $fail("cash_amount cannot be greater than 0 if payment_by_cash is not selected.");
        }

        if ($value == 0 && $bankAmount == 0) {
            $fail("Both cash_amount and bank_amount cannot be zero.");
        }
    }

    public static function validateCashAmountWithServiceChargeforReturn(Request $request, $value, $fail)
    {
        $total = $request->input('amount', 0);
        $cash = (bool) $request->input('payment_by_cash', false);
        $bank = (bool) $request->input('payment_by_bank', false);
        $bankAmount = $request->input('bank_amount', 0);

        if ($cash) {
            if ($bank) {
                if (($value + $bankAmount) != $total) {
                    $fail("The sum of cash_amount and bank_amount must equal the meter deposit amount ($total).");
                }
            } else {
                if ($value != $total) {
                    $fail("cash_amount must be equal to the meter deposit amount ($total).");
                }
            }
        } elseif ($value > 0) {
            $fail("cash_amount cannot be greater than 0 if payment_by_cash is not selected.");
        }

        if ($value == 0 && $bankAmount == 0) {
            $fail("Both cash_amount and bank_amount cannot be zero.");
        }
    }

    public static function validateBankAmountWithServiceCharge(Request $request, $value, $fail)
    {
        $total = $request->input('amount', 0) - $request->input('service_charge', 0);
        $cash = (bool) $request->input('payment_by_cash', false);
        $bank = (bool) $request->input('payment_by_bank', false);
        $cashAmount = $request->input('cash_amount', 0);

        if ($bank) {
            if ($cash) {
                if (($value + $cashAmount) != $total) {
                    $fail("The sum of cash_amount and bank_amount must equal the meter deposit amount ($total).");
                }
            } else {
                if ($value != $total) {
                    $fail("bank_amount must be equal to the meter deposit amount ($total).");
                }
            }
        } elseif ($value > 0) {
            $fail("bank_amount cannot be greater than 0 if payment_by_bank is not selected.");
        }

        if ($value == 0 && $cashAmount == 0) {
            $fail("Both cash_amount and bank_amount cannot be zero.");
        }
    }

     public static function validateBankAmountWithServiceChargeforReturn(Request $request, $value, $fail)
    {
        $total = $request->input('amount', 0);
        $cash = (bool) $request->input('payment_by_cash', false);
        $bank = (bool) $request->input('payment_by_bank', false);
        $cashAmount = $request->input('cash_amount', 0);

        if ($bank) {
            if ($cash) {
                if (($value + $cashAmount) != $total) {
                    $fail("The sum of cash_amount and bank_amount must equal the meter deposit amount ($total).");
                }
            } else {
                if ($value != $total) {
                    $fail("bank_amount must be equal to the meter deposit amount ($total).");
                }
            }
        } elseif ($value > 0) {
            $fail("bank_amount cannot be greater than 0 if payment_by_bank is not selected.");
        }

        if ($value == 0 && $cashAmount == 0) {
            $fail("Both cash_amount and bank_amount cannot be zero.");
        }
    }


    public static function validateCashAmountForUpgrade(Request $request, $value, $fail)
    {
        $cashAmount = $request->input('cash_amount', 0);
        $bankAmount = $request->input('bank_amount', 0);
        $serviceCharge = $request->input('service_charge', 0);
        $totalAmount = $request->input('amount', 0);

        $sum = $cashAmount + $bankAmount;

        $sumTotal = $serviceCharge + $totalAmount;

        if (bccomp($sum, $sumTotal, 2) !== 0) {
            $fail("Cash amount + bank amount ($sum) must equal the total amount + service charge ($sumTotal).");
        }
    }

    public static function validateBankAmountForUpgrade(Request $request, $value, $fail)
    {
        // The logic is the same as cash, just separate function for clarity
        $cashAmount = $request->input('cash_amount', 0);
        $bankAmount = $request->input('bank_amount', 0);
        $serviceCharge = $request->input('service_charge', 0);
        $totalAmount = $request->input('amount', 0);

        $sum = $cashAmount + $bankAmount;
        $sumTotal = $serviceCharge + $totalAmount;

        if (bccomp($sum, $sumTotal, 2) !== 0) {
            $fail("Cash amount + bank amount ($sum) must equal the total amount + service charge ($sumTotal).");
        }
    }


}
