<?php

namespace App\Imports;

use App\Helpers\Helper;
use App\Helpers\NepaliCalendar;
use App\Models\MeterDepositTransaction;
use App\Models\MeterIssue;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OpeningMeterDepositwithMeterIssueImport implements ToCollection, WithHeadingRow, WithValidation
{
    use Importable;

    public $importedCount = 0;
    protected $currentRow = [];
    protected $payloadDateInBs;

public function __construct($dateInBs = null)
{
    $this->payloadDateInBs = $dateInBs;
}

    /**
     * Main Import Logic - ALL OR NOTHING
     */
    public function collection(Collection $rows)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $fiscalYearId = Helper::getActiveFiscalYearId();

            foreach ($rows as $row) {
                $data = $row->toArray();
                $this->currentRow = $data;

                //$dateInBs = $this->convertExcelDate($data['date_in_bs'] ?? null);
                $dateInBs = $this->payloadDateInBs
                    ? $this->convertExcelDate($this->payloadDateInBs)
                    : $this->convertExcelDate($data['date_in_bs'] ?? null);

                // fallback → today BS
                if (empty($dateInBs)) {
                    $dateInBs = NepaliCalendar::adToBs(date('Y-m-d'));
                }
                $dateInAd = null;

                if ($dateInBs) {
                    try {
                        $dateInAd = NepaliCalendar::bsToAd($dateInBs);
                    } catch (\Exception $e) {
                        throw new \Exception("Row {$row->getRowIndex()}: Invalid BS date - {$dateInBs}");
                    }
                }

                MeterDepositTransaction::on('tenant')->create([
                    'date_in_bs'       => $dateInBs,
                    'date_in_ad'       => $dateInAd,           // Auto-generated from BS
                    'meter_issue_id'   => $data['meter_issue_id'],
                    'fiscal_year_id'   => $fiscalYearId,
                    'transaction_type' => 4,                    // Fixed
                    'amount'           => $data['amount'],
                    'service_charge'   => 0,
                    'is_cancel'        => 0,
                ]);
            }

            $this->importedCount = $rows->count();

            DB::connection('tenant')->commit();
        } catch (\Throwable $e) {
            DB::connection('tenant')->rollBack();
            throw new \Exception("Import failed: " . $e->getMessage());
        }
    }

    /**
     * Convert Excel date serial or MM/DD/YYYY → YYYY-MM-DD
     */
    private function convertExcelDate($value)
    {
        if (empty($value)) return null;

        if (is_numeric($value) && $value > 40000) {
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value))
                         ->format('Y-m-d');
        }

        $value = trim((string)$value);

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
            $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $day   = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year  = $matches[3];
            return "{$year}-{$month}-{$day}";
        }

        return $value;
    }

    /**
     * Validation Rules
     */
    public function rules(): array
    {
        return [
           '*.date_in_bs' => [
                'nullable',
                function ($attribute, $value, $fail) {

                    $bsDate = $this->payloadDateInBs
                        ? $this->convertExcelDate($this->payloadDateInBs)
                        : $this->convertExcelDate($value);

                    if (empty($bsDate)) {
                        $bsDate = NepaliCalendar::adToBs(date('Y-m-d'));
                    }

                    try {
                        $adDate = NepaliCalendar::bsToAd($bsDate);

                        if ($adDate > date('Y-m-d')) {
                            $fail('The date_in_bs cannot be a future date.');
                        }

                    } catch (\Exception $e) {
                        $fail('The date_in_bs is not a valid BS date.');
                    }
                },
            ],

            '*.meter_issue_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $exists = MeterIssue::on('tenant')
                        ->where('id', $value)
                        ->where('is_active', 1)
                        ->exists();

                    if (!$exists) {
                        $fail('The selected meter issue id is invalid or inactive.');
                    }
                },
                function ($attribute, $value, $fail) {
                    if (
                        MeterDepositTransaction::on('tenant')
                            ->withoutTrashed()
                            ->where('meter_issue_id', $value)
                            ->where('transaction_type', 4)
                            ->exists()
                    ) {
                        $fail('An active opening meter deposit already exists for this meter.');
                    }
                },
            ],

            '*.amount' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999999.99',
            ],
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}