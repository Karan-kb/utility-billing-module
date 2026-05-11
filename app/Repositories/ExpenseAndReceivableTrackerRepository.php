<?php

namespace App\Repositories;

use App\Models\ExpenseAndReceivableTracker;
use App\Models\ExpenseAndReceivableItem;
use App\Repositories\Interfaces\ExpenseAndReceivableTrackerRepositoryInterface;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;
use App\Helpers\NepaliCalendar;
use App\Models\AccountHead;
use App\Models\Payment;
use App\Models\VoucherSummary;
use App\Models\VoucherSummaryDetail;

class ExpenseAndReceivableTrackerRepository implements ExpenseAndReceivableTrackerRepositoryInterface
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }
 public function getQueryByType(int $type)
    {
        return ExpenseAndReceivableTracker::where('type', $type)->with('items.accountHead');
    }

  
public function store(array $data)
{
    DB::beginTransaction();

    try {

        $fiscalYearId = Helper::getActiveFiscalYearId();

        $tracker = ExpenseAndReceivableTracker::create([
            'voucher_no' => $data['voucher_no'],
            'date_in_bs' => $data['date_in_bs'],
            'fiscal_year_id' => $fiscalYearId,
            'type' => $data['type'] ?? 0,
        ]);

        $cashTotal = 0;
        $bankTotals = [];
        $debitEntries = [];

        foreach ($data['expenses'] as $expense) {

            ExpenseAndReceivableItem::create([
                'tracker_id' => $tracker->id,
                'account_head_id' => $expense['account_head_id'],
                'ref_bill_no' => $expense['ref_bill_no'] ?? null,
                'bank_id' => $expense['bank_id'] ?? null,
                'amount' => $expense['amount'],
                'particular' => $expense['particular'] ?? null
            ]);


          $debitEntries[] = [
            'account_head_id' => $expense['account_head_id'],
            'amount' => $expense['amount'],
            'particular' => $expense['particular'] ?? null
        ];

            if (empty($expense['bank_id'])) {
                $cashTotal += $expense['amount'];
            }

            if (!empty($expense['bank_id'])) {

                $bankTotals[$expense['bank_id']] =
                    ($bankTotals[$expense['bank_id']] ?? 0) + $expense['amount'];

                $this->paymentService->create(
                    $tracker->id,
                    $expense['amount'],
                    2,
                    [
                        'type' => 12,
                        'bank_id' => $expense['bank_id'],
                        'cheque_no' => $expense['cheque_no'] ?? null
                    ]
                );
            }
        }

        if ($cashTotal > 0) {
            $this->paymentService->create(
                $tracker->id,
                $cashTotal,
                1,
                ['type' => 12]
            );
        }

        $dateInAd = NepaliCalendar::bsToAd($data['date_in_bs']);
        $totalDebit = array_sum(array_column($debitEntries, 'amount'));

                $totalCredit = $cashTotal + array_sum($bankTotals);

                if ($totalDebit !== $totalCredit) {
                    DB::rollBack();
                    throw new \Exception("Voucher entry not balanced: Total Debit ({$totalDebit}) does not equal Total Credit ({$totalCredit})");
                }
        $voucher = VoucherSummary::create([
            'date' => $dateInAd,
            'voucher_no' => $data['voucher_no'],
            'particulars' => 'Expense Tracker - ' . $data['voucher_no'],
            'reference_type' => ($data['type'] == 1) ? 15 : 12, 
            'reference_id' => $tracker->id,
            'fiscal_year_id' => $fiscalYearId,
            'status' => 2
        ]);

       foreach ($debitEntries as $entry) {

            $accountHead = AccountHead::find($entry['account_head_id']);

            $accountHeadName = $accountHead?->name ?? 'Account Head';

            VoucherSummaryDetail::create([
                'voucher_summary_id' => $voucher->id,
                'account_head_id' => $entry['account_head_id'],
                'particulars' => "{$accountHeadName} (Expense {$data['voucher_no']})",
                'debit' => $entry['amount'],
                'credit' => 0,
                'member_entry_id' => null
            ]);
        }

        if ($cashTotal > 0) {

            VoucherSummaryDetail::create([
                'voucher_summary_id' => $voucher->id,
                'account_head_id' => 1,
                 'particulars' => "Cash Received (Expense {$data['voucher_no']})",
                'debit' => 0,
                'credit' => $cashTotal,
                'member_entry_id' => null
            ]);
        }

        foreach ($bankTotals as $bankId => $amount) {

            VoucherSummaryDetail::create([
                'voucher_summary_id' => $voucher->id,
                'account_head_id' => $bankId,
             'particulars' => "Bank Received (Expense {$data['voucher_no']})",
                'debit' => 0,
                'credit' => $amount,
                'member_entry_id' => null
            ]);
        }

        DB::commit();

        return $tracker->load('items');

    } catch (\Throwable $e) {

        DB::rollBack();

        throw $e; 
    }
}

    // public function getAll()
    // {
    //     return ExpenseAndReceivableTracker::with('items')->latest()->get();
    // }

    public function find($id)
    {
        return ExpenseAndReceivableTracker::with('items')->findOrFail($id);
    }

    public function update($id, array $data)
{
    DB::beginTransaction();

    try {

        $tracker = ExpenseAndReceivableTracker::findOrFail($id);

        $fiscalYearId = Helper::getActiveFiscalYearId();

        $tracker->update([
            'voucher_no' => $data['voucher_no'],
            'date_in_bs' => $data['date_in_bs'],
            'fiscal_year_id' => $fiscalYearId,
            'type' => $data['type'] ?? 0,
        ]);

        // Delete old items
        ExpenseAndReceivableItem::where('tracker_id', $tracker->id)->delete();

        // Delete old payments
        Payment::where('reference_id', $tracker->id)
            ->where('type', 12)
            ->delete();

        // Delete old voucher + details
        $voucher = VoucherSummary::where('reference_id', $tracker->id)
            ->where('reference_type', 12)
            ->first();

        if ($voucher) {
            VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)->delete();
            $voucher->delete();
        }

        $cashTotal = 0;
        $bankTotals = [];
        $debitEntries = [];

        foreach ($data['expenses'] as $expense) {

            ExpenseAndReceivableItem::create([
                'tracker_id' => $tracker->id,
                'account_head_id' => $expense['account_head_id'],
                'ref_bill_no' => $expense['ref_bill_no'] ?? null,
                'bank_id' => $expense['bank_id'] ?? null,
                'amount' => $expense['amount'],
                'particular' => $expense['particular'] ?? null
            ]);

            $debitEntries[] = [
                'account_head_id' => $expense['account_head_id'],
                'amount' => $expense['amount'],
                'particular' => $expense['particular'] ?? null
            ];

            if (empty($expense['bank_id'])) {
                $cashTotal += $expense['amount'];
            }

            if (!empty($expense['bank_id'])) {

                $bankTotals[$expense['bank_id']] =
                    ($bankTotals[$expense['bank_id']] ?? 0) + $expense['amount'];

                $this->paymentService->create(
                    $tracker->id,
                    $expense['amount'],
                    2,
                    [
                        'type' => 12,
                        'bank_id' => $expense['bank_id'],
                        'cheque_no' => $expense['cheque_no'] ?? null
                    ]
                );
            }
        }

        if ($cashTotal > 0) {
            $this->paymentService->create(
                $tracker->id,
                $cashTotal,
                1,
                ['type' => 12]
            );
        }
            $totalDebit = array_sum(array_column($debitEntries, 'amount'));
            $totalCredit = $cashTotal + array_sum($bankTotals);

            if ($totalDebit !== $totalCredit) {
                DB::rollBack();
                throw new \Exception("Voucher entry not balanced: Total Debit ({$totalDebit}) does not equal Total Credit ({$totalCredit})");
            }
        $dateInAd = NepaliCalendar::bsToAd($data['date_in_bs']);

        $voucher = VoucherSummary::create([
            'date' => $dateInAd,
            'voucher_no' => $data['voucher_no'],
            'particulars' => 'Expense Tracker - ' . $data['voucher_no'],
            'reference_type' => 12,
            'reference_id' => $tracker->id,
            'fiscal_year_id' => $fiscalYearId,
            'status' => 2
        ]);

        // Debit entries
        foreach ($debitEntries as $entry) {

            $accountHead = AccountHead::find($entry['account_head_id']);
            $accountHeadName = $accountHead?->name ?? 'Account Head';

            VoucherSummaryDetail::create([
                'voucher_summary_id' => $voucher->id,
                'account_head_id' => $entry['account_head_id'],
                'particulars' => "{$accountHeadName} (Expense {$data['voucher_no']})",
                'debit' => $entry['amount'],
                'credit' => 0,
                'member_entry_id' => null
            ]);
        }

        // Cash credit
        if ($cashTotal > 0) {

            VoucherSummaryDetail::create([
                'voucher_summary_id' => $voucher->id,
                'account_head_id' => 1,
                'particulars' => "Cash Received (Expense {$data['voucher_no']})",
                'debit' => 0,
                'credit' => $cashTotal,
                'member_entry_id' => null
            ]);
        }

        // Bank credit
        foreach ($bankTotals as $bankId => $amount) {

            VoucherSummaryDetail::create([
                'voucher_summary_id' => $voucher->id,
                'account_head_id' => $bankId,
                'particulars' => "Bank Received (Expense {$data['voucher_no']})",
                'debit' => 0, 
                'credit' => $amount,
                'member_entry_id' => null
            ]);
        }

        DB::commit();

        return $tracker->load('items');

    } catch (\Throwable $e) {

        DB::rollBack();
        throw $e;
    }
}
  public function delete($id)
{
    DB::beginTransaction();

    try {

        $tracker = ExpenseAndReceivableTracker::findOrFail($id);

        ExpenseAndReceivableItem::where('tracker_id', $tracker->id)->delete();

        Payment::where('reference_id', $tracker->id)
            ->where('type', 12)
            ->delete();

        $voucher = VoucherSummary::where('reference_id', $tracker->id)
            ->where('reference_type', 12)
            ->first();

        if ($voucher) {

            VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)->delete();
            $voucher->delete();
        }

        $tracker->delete();

        DB::commit();

        return true;

    } catch (\Throwable $e) {

        DB::rollBack();
        throw $e;
    }
}
}