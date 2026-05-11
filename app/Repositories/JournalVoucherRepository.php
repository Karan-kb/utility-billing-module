<?php

namespace App\Repositories;

use App\Models\JournalVoucher;
use App\Models\JournalVoucherDetail;
use App\Repositories\Interfaces\JournalVoucherRepositoryInterface;
use App\Services\VoucherEntryService;
use Illuminate\Support\Facades\DB;
use App\Helpers\Helper;
use App\Helpers\NepaliCalendar;
use App\Models\VoucherSummary;
use App\Models\VoucherSummaryDetail;

class JournalVoucherRepository implements JournalVoucherRepositoryInterface
{
    protected $voucherService;

    public function __construct(VoucherEntryService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    public function store(array $data)
    {
        return DB::connection('tenant')->transaction(function () use ($data) {

            $fiscalYearId = Helper::getActiveFiscalYearId();

            $dateAd = NepaliCalendar::bsToAd($data['date_in_bs']);

            $voucher = JournalVoucher::create([
                'fiscal_year_id' => $fiscalYearId,
                'date_in_bs'     => $data['date_in_bs'],
                'voucher_no'     => $data['voucher_no'],
            ]);

            $totalDebit = 0;
            $totalCredit = 0;
            $lines = [];

            foreach ($data['details'] as $index => $detail) {

                $debit  = $detail['debit'] ?? 0;
                $credit = $detail['credit'] ?? 0;

                if ($debit > 0 && $credit > 0) {
                    throw new \Exception("Only one of debit or credit allowed at row {$index}");
                }

                if ($debit == 0 && $credit == 0) {
                    throw new \Exception("Either debit or credit required at row {$index}");
                }

                JournalVoucherDetail::create([
                    'journal_voucher_id' => $voucher->id,
                    'account_head_id'    => $detail['account_head_id'],
                    'cheque_no'          => $detail['cheque_no'] ?? null,
                    'particulars'        => $detail['particulars'] ?? null,
                    'debit'              => $debit > 0 ? $debit : 0,
                    'credit'             => $credit > 0 ? $credit : 0,
                ]);

                $lines[] = [
                    'account_head_id' => $detail['account_head_id'],
                    'debit'  => $debit > 0 ? $debit : 0,
                    'credit' => $credit > 0 ? $credit : 0,
                    'particulars' => $detail['particulars'] ?? null,
                ];

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if ($totalDebit !== $totalCredit) {
                throw new \Exception(
                    "Journal Voucher not balanced: Debit ({$totalDebit}) != Credit ({$totalCredit})"
                );
            }

            $this->voucherService->create([
                'date' => $dateAd,
                'fiscal_year_id' => $fiscalYearId,
                'voucher_no' => $data['voucher_no'],
                'particulars' => 'Journal Voucher - ' . $data['voucher_no'],
                'status' => 2,
                'reference_type' => 14,
                'reference_id' => $voucher->id,
                'member_entry_id' => null,
                'lines' => $lines,
            ]);

            return $voucher->load('details');
        });
    }

    public function getAll()
    {
        return JournalVoucher::with('details')->latest()->get();
    }

    public function find($id)
    {
        return JournalVoucher::with('details')->findOrFail($id);
    }

  public function update($id, array $data)
{
    return DB::connection('tenant')->transaction(function () use ($id, $data) {

        $fiscalYearId = Helper::getActiveFiscalYearId();

        $journalVoucher = JournalVoucher::findOrFail($id);

        $journalVoucher->update([
            'date_in_bs' => $data['date_in_bs'],
            'voucher_no' => $data['voucher_no'],
        ]);

        // Delete old details
        JournalVoucherDetail::where('journal_voucher_id', $journalVoucher->id)->delete();

        // Delete old voucher + details (VoucherSummary side)
        $voucher = VoucherSummary::where('reference_id', $journalVoucher->id)
            ->where('reference_type', 14)
            ->first();

        if ($voucher) {
            VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)->delete();
            $voucher->delete();
        }

        $totalDebit = 0;
        $totalCredit = 0;
        $lines = [];

        foreach ($data['details'] as $index => $detail) {

            $debit  = $detail['debit'] ?? 0;
            $credit = $detail['credit'] ?? 0;

            if ($debit > 0 && $credit > 0) {
                throw new \Exception("Only one of debit or credit allowed at row {$index}");
            }

            if ($debit == 0 && $credit == 0) {
                throw new \Exception("Either debit or credit required at row {$index}");
            }

            JournalVoucherDetail::create([
                'journal_voucher_id' => $journalVoucher->id,
                'account_head_id'    => $detail['account_head_id'],
                'cheque_no'          => $detail['cheque_no'] ?? null,
                'particulars'        => $detail['particulars'] ?? null,
                'debit'              => $debit > 0 ? $debit : 0,
                'credit'             => $credit > 0 ? $credit : 0,
            ]);

            $lines[] = [
                'account_head_id' => $detail['account_head_id'],
                'debit'  => $debit > 0 ? $debit : 0,
                'credit' => $credit > 0 ? $credit : 0,
                'particulars' => $detail['particulars'] ?? null,
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if ($totalDebit !== $totalCredit) {
            throw new \Exception(
                "Journal Voucher not balanced: Debit ({$totalDebit}) != Credit ({$totalCredit})"
            );
        }

        $dateAd = NepaliCalendar::bsToAd($data['date_in_bs']);

        $this->voucherService->create([
            'date' => $dateAd,
            'fiscal_year_id' => $fiscalYearId,
            'voucher_no' => $data['voucher_no'],
            'particulars' => 'Journal Voucher - ' . $data['voucher_no'],
            'status' => 2,
            'reference_type' => 14,
            'reference_id' => $journalVoucher->id,
            'member_entry_id' => null,
            'lines' => $lines,
        ]);

        return $journalVoucher->load('details');
    });
}

public function delete($id)
{
    return DB::connection('tenant')->transaction(function () use ($id) {

        $journalVoucher = JournalVoucher::findOrFail($id);

        JournalVoucherDetail::where('journal_voucher_id', $journalVoucher->id)->delete();

        // Delete related VoucherSummary + details
        $voucher = VoucherSummary::where('reference_id', $journalVoucher->id)
            ->where('reference_type', 14)
            ->first();

        if ($voucher) {
            VoucherSummaryDetail::where('voucher_summary_id', $voucher->id)->delete();
            $voucher->delete();
        }

        $journalVoucher->delete();

        return true;
    });
}
}