<?php

namespace App\Repositories;

use App\Models\BankVoucher;
use App\Repositories\Interfaces\BankVoucherRepositoryInterface;
use App\Services\PaymentService;
use App\Services\CashBankSummaryService;
use App\Services\VoucherEntryService;
use App\Helpers\NepaliCalendar;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;

class BankVoucherRepository implements BankVoucherRepositoryInterface
{
    protected $voucherService;

    public function __construct(VoucherEntryService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

   public function store(array $data)
{
    return DB::connection('tenant')->transaction(function () use ($data) {

        $validated = collect($data)->only([
            'option',
            'date_in_bs',
            'voucher_no',
            'amount',
            'bank_id',
            'cheque_no',
            'remarks',
            'balance_after'
        ])->toArray();

        $paymentService = new PaymentService();

        // Get active fiscal year
        $fiscalYearId = Helper::getActiveFiscalYearId();

        // Convert BS date to AD
        $dateAd = NepaliCalendar::bsToAd($validated['date_in_bs']);

        $cashAccount = 1;
        $bankAccount = $validated['bank_id'];
        $amount = $validated['amount'];
        $balanceAfter = $validated['balance_after']; // Already validated by StoreRequest

        // Prepare voucher lines
        $lines = [];
        $voucherNoText = " (Bank Voucher {$validated['voucher_no']})";

        if ($validated['option'] == 1) {
            // Deposit → Bank DR, Cash CR
            $lines[] = [
                'account_head_id' => $bankAccount,
                'debit' => $amount,
                'particulars' => 'Bank Deposit'. $voucherNoText,
            ];
            $lines[] = [
                'account_head_id' => $cashAccount,
                'credit' => $amount,
                'particulars' => 'Cash Payment'. $voucherNoText,
            ];
        } else {
            // Withdraw → Cash DR, Bank CR
            $lines[] = [
                'account_head_id' => $cashAccount,
                'debit' => $amount,
                'particulars' => 'Cash Received'. $voucherNoText,
            ];
            $lines[] = [
                'account_head_id' => $bankAccount,
                'credit' => $amount,
                'particulars' => 'Bank Withdraw'. $voucherNoText,
            ];
        }

        // Create Bank Voucher record
        $bankVoucher = BankVoucher::create([
            'fiscal_year_id' => $fiscalYearId,
            'type' => $validated['option'],
            'date_in_bs' => $validated['date_in_bs'],
            'voucher_no' => $validated['voucher_no'],
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        // Create voucher summary and lines via VoucherEntryService
        $this->voucherService->create([
            'date' => $dateAd,
            'fiscal_year_id' => $fiscalYearId,
            'voucher_no' => $validated['voucher_no'],
            'particulars' => 'Bank Voucher Entry - ' . $validated['voucher_no'],
            'status' => 2,
            'reference_type' => 13, // Bank Voucher reference type
            'reference_id' => $bankVoucher->id,
            'member_entry_id' => null,
            'lines' => $lines,
        ]);

        // Create bank payment entry
        $paymentService->create(
            $bankVoucher->id,
            $amount,
            2, // bank
            [
                'type' => 13,
                'cheque_no' => $validated['cheque_no'] ?? null,
                'bank_id' => $bankAccount,
            ]
        );

        return $bankVoucher;
    });
}

    public function getAll()
    {
        return BankVoucher::latest()->paginate(10);
    }

  

 public function update($id, array $data)
{
    return DB::connection('tenant')->transaction(function () use ($id, $data) {

        $validated = collect($data)->only([
            'option',
            'date_in_bs',
            'voucher_no',
            'amount',
            'bank_id',
            'cheque_no',
            'remarks',
            'balance_after'
        ])->toArray();

        $bankVoucher = BankVoucher::findOrFail($id);
        $paymentService = new PaymentService();
        $balanceService = new CashBankSummaryService();

        // Get active fiscal year
        $fiscalYearId = Helper::getActiveFiscalYearId();

        // Convert BS date to AD
        $dateAd = NepaliCalendar::bsToAd($validated['date_in_bs']);

        $cashAccount = 1;
        $bankAccount = $validated['bank_id'];
        $amount = $validated['amount'];
        $balanceAfter = $validated['balance_after']; // Already validated by UpdateRequest

        // Update Bank Voucher record
        $bankVoucher->update([
            'type' => $validated['option'],
            'date_in_bs' => $validated['date_in_bs'],
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        // Prepare voucher lines
        $lines = [];
        $voucherNoText = " (Bank Voucher {$validated['voucher_no']})";

        if ($validated['option'] == 1) {
            // Deposit → Bank DR, Cash CR
            $lines[] = [
                'account_head_id' => $bankAccount,
                'debit' => $amount,
                'particulars' => 'Bank Deposit'. $voucherNoText,
            ];
            $lines[] = [
                'account_head_id' => $cashAccount,
                'credit' => $amount,
                'particulars' => 'Cash Payment'. $voucherNoText,
            ];
        } else {
            // Withdraw → Cash DR, Bank CR
            $lines[] = [
                'account_head_id' => $cashAccount,
                'debit' => $amount,
                'particulars' => 'Cash Received'. $voucherNoText,
            ];
            $lines[] = [
                'account_head_id' => $bankAccount,
                'credit' => $amount,
                'particulars' => 'Bank Withdraw'. $voucherNoText,
            ];
        }

        // Update Voucher Summary and Details
        $voucherSummary = \App\Models\VoucherSummary::where('reference_type', 13)
            ->where('reference_id', $bankVoucher->id)
            ->first();

        if ($voucherSummary) {
            // Update main voucher summary
            $voucherSummary->update([
                'date' => $dateAd,
                'fiscal_year_id' => $fiscalYearId,
                'voucher_no' => $validated['voucher_no'],
                'particulars' => 'Bank Voucher Entry - ' . $validated['voucher_no'],
                'status' => 2,
            ]);

            // Delete old voucher lines
            \App\Models\VoucherSummaryDetail::where('voucher_summary_id', $voucherSummary->id)->delete();

            // Insert updated voucher lines
            foreach ($lines as $line) {
                $line['voucher_summary_id'] = $voucherSummary->id;
                \App\Models\VoucherSummaryDetail::create($line);
            }
        }

        // Update bank payment entry
        $paymentService->updatePayments($bankVoucher->id, [
            'bank_amount' => $amount,
            'cheque_no' => $validated['cheque_no'] ?? null,
            'bank_id' => $bankAccount,
        ], 13);

        return $bankVoucher;
    });
}
  public function delete($id)
{
    DB::connection('tenant')->beginTransaction();

    try {

        $bankVoucher = BankVoucher::findOrFail($id);
        \App\Models\Payment::where('reference_id', $bankVoucher->id)
            ->where('type', 13) // 13 = Bank Voucher
            ->delete();

        $voucher = \App\Models\VoucherSummary::where('reference_id', $bankVoucher->id)
            ->where('reference_type', 13) // 13 = Bank Voucher reference_type
            ->first();

        if ($voucher) {
            \App\Models\VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)->delete();
            $voucher->delete();
        }

        // Delete the bank voucher itself
        $bankVoucher->delete();

        DB::connection('tenant')->commit();

        return true;

    } catch (\Throwable $e) {

        DB::connection('tenant')->rollBack();
        throw $e;
    }
}
public function find($id)
{
    $bankVoucher = BankVoucher::findOrFail($id);

    $payment = \App\Models\Payment::where('reference_id', $bankVoucher->id)
        ->where('type', 13)
        ->first();

    return [
        'bank_voucher' => $bankVoucher,
        'payment' => $payment,
    ];
}
}