<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\DepositVoucherService;
use App\Services\VoucherService;
use App\Models\OtherIncomeReceipt;
use App\Models\ShareEntry;
use App\Models\ShareTransaction;
use App\Models\ShareReturn;
use App\Models\MahasulReceiptEntry;
use App\Models\MeterInsurance;
use App\Models\AdvancePayment;
use App\Models\NEAPaymentEntry;
use App\Models\NonMemberPayment;
use App\Models\UpgradeMeterCapacity;
use App\Models\BankVoucher;
use App\Models\ExpenseAndReceivableTracker;
use App\Models\JournalVoucher;
use Illuminate\Http\Request;

class GenerateCodeController extends Controller
{
    protected $voucherService;
    protected $depositVoucherService;

    public function __construct(VoucherService $voucherService, DepositVoucherService $depositVoucherService)
    {
        $this->voucherService = $voucherService;
        $this->depositVoucherService = $depositVoucherService;
    }

    public function generateOtherIncomeReceipt(Request $request)
    {
        return $this->generateVoucher($request, OtherIncomeReceipt::class, 'O');
    }


    public function generateDepositEntry(Request $request)
    {
        try {
            $data = $this->depositVoucherService->generateDepositEntry();

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error generating deposit entry voucher: ' . $e->getMessage()
            ], 400);
        }
    }

    public function generateDepositReturnEntry(Request $request)
    {
        try {
            $data = $this->depositVoucherService->generateDepositReturn();

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error generating deposit return voucher: ' . $e->getMessage()
            ], 400);
        }
    }

    public function generateShareEntry(Request $request)
    {
        return $this->generateVoucher($request, ShareTransaction::class, 'SE');
    }


    public function generateShareReturnEntry(Request $request)
    {
        return $this->generateVoucher($request, ShareTransaction::class, 'SR');
    }


    public function generateMahasulReceipt(Request $request)
    {
        return $this->generateVoucher($request, MahasulReceiptEntry::class, 'M');
    }

    public function generateMeterInsurance(Request $request)
    {
        return $this->generateVoucher($request, MeterInsurance::class, 'I');
    }

    public function generateAdvancePayment(Request $request)
    {
        return $this->generateVoucher($request, AdvancePayment::class, 'A');
    }

    public function generateNeaPayment(Request $request)
    {
        return $this->generateVoucher($request, NEAPaymentEntry::class, 'NE');
    }

    public function generateNonMemberPayment(Request $request)
    {
        return $this->generateVoucher($request, NonMemberPayment::class, 'NM');
    }

    public function generateUpgradeMeterCapacity(Request $request)
    {
        return $this->generateVoucher($request, UpgradeMeterCapacity::class, 'UG');
    }

    public function generateBankVoucher(Request $request)
    {
        return $this->generateVoucher($request, BankVoucher::class, 'B', false);
    }
    public function generatExpenseAndReceivableTrackerVoucher(Request $request, $type)
{
    $prefix = $type == 1 ? 'R' : 'E';

    return $this->generateVoucher($request, ExpenseAndReceivableTracker::class, $prefix, false);
}
    public function generateJournalVoucher(Request $request)
    {
        return $this->generateVoucher($request, JournalVoucher::class, 'J', false);
    }

    

    protected function generateVoucher(Request $request, string $modelClass, string $prefix, bool $useNepaliCalendar = true)
    {
        try {
            $voucherNo = $this->voucherService->generateCode($modelClass, $prefix, 'voucher_no', $useNepaliCalendar);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'voucher_no' => $voucherNo,
                    'fiscal_year' => substr($voucherNo, 1, 4), // first 4 chars after prefix
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error generating voucher number: ' . $e->getMessage()
            ], 400);
        }
    }
}
