<?php

namespace App\Repositories;

use App\Helpers\Helper;
use App\Models\OpeningBalanceEntry;
use App\Repositories\Interfaces\OpeningBalanceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Exception;

class OpeningBalanceRepository implements OpeningBalanceRepositoryInterface
{
   public function store(array $data)
{
    return DB::connection('tenant')->transaction(function () use ($data) {

        $entries = $data['entries'] ?? [];
        $fiscalYearId = Helper::getActiveFiscalYearId();

        $inserted = [];
        foreach ($entries as $entry) {
            $inserted[] = OpeningBalanceEntry::create([
                'fiscal_year_id' => $fiscalYearId,
                'account_head_id' => $entry['account_head_id'],
                'particulars' => $entry['particulars'] ?? 'Opening Balance',
                'debit' => $entry['debit'] ?? 0,
                'credit' => $entry['credit'] ?? 0,
            ]);
        }

        return $inserted;
    });
}
  public function getAll()
{
    $entries = OpeningBalanceEntry::with(['accountHead:id,name,name_np'])
        ->select([
            'id',
            'fiscal_year_id',
            'account_head_id',
            'particulars',
            'debit',
            'credit',
            'created_at',
        ])
        ->latest()
        ->get();

    return $entries->map(function ($entry) {
        return [
            'id' => $entry->id,
            'fiscal_year_id' => $entry->fiscal_year_id,
            'account_head_id' => $entry->account_head_id,
            'account_head_name' => $entry->accountHead->name ?? null,
            'particulars' => $entry->particulars,
            'debit' => $entry->debit,
            'credit' => $entry->credit,
            'created_at' => $entry->created_at,
        ];
    });
}

   public function getByFiscalYear()
{
    $fiscalYearId = Helper::getActiveFiscalYearId();

    $entries = OpeningBalanceEntry::with(['accountHead:id,name,name_np'])
        ->where('fiscal_year_id', $fiscalYearId)
        ->select([
            'id',
            'fiscal_year_id',
            'account_head_id',
            'particulars',
            'debit',
            'credit',
        ])
        ->get();

    return $entries->map(function ($entry) {
        return [
            'id' => $entry->id,
            'fiscal_year_id' => $entry->fiscal_year_id,
            'account_head_id' => $entry->account_head_id,
            'account_head_name' => $entry->accountHead->name ?? null,
            'particulars' => $entry->particulars,
            'debit' => $entry->debit,
            'credit' => $entry->credit,
           
        ];
    });
}

    public function changeAll(array $data)
{
    return DB::connection('tenant')->transaction(function () use ($data) {

        $entries = $data['entries'] ?? [];
        $fiscalYearId = Helper::getActiveFiscalYearId();

        if (empty($entries)) {
            throw new \Exception('Entries cannot be empty.');
        }

        if (!$fiscalYearId) {
            throw new \Exception('Active fiscal year not found.');
        }

        // Soft-delete old records
        OpeningBalanceEntry::where('fiscal_year_id', $fiscalYearId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        // Insert new entries
        $newEntries = [];
        foreach ($entries as $entry) {
            $newEntries[] = OpeningBalanceEntry::create([
                'fiscal_year_id' => $fiscalYearId,
                'account_head_id' => $entry['account_head_id'],
                'particulars' => $entry['particulars'] ?? 'Opening Balance',
                'debit' => (float) ($entry['debit'] ?? 0),
                'credit' => (float) ($entry['credit'] ?? 0),
            ]);
        }

        return $newEntries;
    });
}

   public function deleteAll()
{
    $fiscalYearId = Helper::getActiveFiscalYearId();

    OpeningBalanceEntry::where('fiscal_year_id', $fiscalYearId)->delete();

    return true;
}
}