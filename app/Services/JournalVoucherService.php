<?php

namespace App\Services;

use App\Repositories\Interfaces\JournalVoucherRepositoryInterface;

class JournalVoucherService
{
    protected $repository;
    protected $voucherEntryService;

    public function __construct(
        JournalVoucherRepositoryInterface $repository,
        VoucherEntryService $voucherEntryService
    ) {
        $this->repository = $repository;
        $this->voucherEntryService = $voucherEntryService;
    }

    public function store(array $data)
    {
        // 1. Store Journal Voucher
        $journalVoucher = $this->repository->store($data);

        // 2. Prepare VoucherSummary lines
        $lines = [];

        foreach ($data['details'] as $detail) {

            $debit  = $detail['debit'] ?? 0;
            $credit = $detail['credit'] ?? 0;

            $lines[] = [
                'account_head_id' => $detail['account_head_id'],
                'debit'  => $debit > 0 ? $debit : 0,
                'credit' => $credit > 0 ? $credit : 0,
                'particulars' => $detail['particulars'] ?? null,
            ];
        }

        // 3. Create Voucher Summary using your service
        $this->voucherEntryService->create([
            'date' => now(),
            'fiscal_year_id' => $data['fiscal_year_id'] ?? null,
            'voucher_no' => $data['voucher_no'],
            'reference_type' => 14, // define constant if needed
            'reference_id' => $journalVoucher->id,
            'lines' => $lines,
        ]);

        return $journalVoucher;
    }
}