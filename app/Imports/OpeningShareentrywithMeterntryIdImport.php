<?php

namespace App\Imports;

use App\Helpers\Helper;
use App\Helpers\NepaliCalendar;
use App\Models\ShareTransaction;
use App\Models\MemberEntry;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class OpeningShareEntryImport implements ToCollection, WithHeadingRow, WithValidation
{
    use Importable;
protected $payloadDateInBs;

public function __construct($dateInBs = null)
{
    $this->payloadDateInBs = $dateInBs;
}
    public $importedCount = 0;
    protected $rowData = []; // Store all rows keyed by index for cross-field access

    public function collection(Collection $rows)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $fiscalYearId = Helper::getActiveFiscalYearId();

            foreach ($rows as $row) {
                $data = $row->toArray();

                //$dateInBs = $this->convertExcelDate($data['date_in_bs'] ?? null);
                $dateInBs = $this->payloadDateInBs
                    ? $this->convertExcelDate($this->payloadDateInBs)
                    : $this->convertExcelDate($data['date_in_bs'] ?? null);

                // If still empty → default to today (BS)
                if (empty($dateInBs)) {
                    $dateInBs = NepaliCalendar::adToBs(date('Y-m-d'));
                }

                if (isset($data['share_certificate_no']) && is_numeric($data['share_certificate_no'])) {
                    $data['share_certificate_no'] = (string) $data['share_certificate_no'];
                }

                $dateInAd = null;
                if ($dateInBs) {
                    try {
                        $dateInAd = NepaliCalendar::bsToAd($dateInBs);
                    } catch (\Exception $e) {
                        throw new \Exception("Invalid BS date - {$dateInBs}");
                    }
                }

                ShareTransaction::on('tenant')->create([
                    'date_in_bs'           => $dateInBs,
                    'date_in_ad'           => $dateInAd,
                    'member_entry_id'      => $data['member_entry_id'],
                    'fiscal_year_id'       => $fiscalYearId,
                    'share_type'           => 'electricity',
                    'transaction_type'     => 3,
                    'share_certificate_no' => $data['share_certificate_no'],
                    'share_quantity'       => $data['share_quantity'],
                    'share_value'          => 100.00,
                    'amount'               => $data['amount'],
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
     * Called before validation — store all row data for cross-field access
     */
    public function withValidator(Validator $validator): void
    {
        // The full sheet data is available as the validator's data
        $this->rowData = $validator->getData();
    }

    public function rules(): array
    {
        return [
           '*.date_in_bs' => [
                'nullable',
                function ($attribute, $value, $fail) {

                    // Priority: payload → excel → default(today)
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

            '*.member_entry_id' => [
                'required',
                'integer',
                'exists:tenant.member_entries,id,deleted_at,NULL,is_active,1',
                function ($attribute, $value, $fail) {
                    if (
                        ShareTransaction::withoutTrashed()
                            ->where('member_entry_id', $value)
                            ->where('transaction_type', 3)
                            ->exists()
                    ) {
                        $fail('The member_no is already in use by an active share opening entry record.');
                    }
                },
            ],

            '*.share_certificate_no' => [
                'required',
                'max:20',
            ],

            '*.share_quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            '*.amount' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) {
                    // $attribute is like "0.amount" — extract the row index
                    $rowIndex = explode('.', $attribute)[0];

                    // Now safely read the sibling share_quantity from stored row data
                    $quantity = data_get($this->rowData, "{$rowIndex}.share_quantity", 0);
                    $expected = (float) $quantity * 100.00;

                    if (abs((float) $value - $expected) > 0.01) {
                        $fail("The amount must be equal to share_quantity * 100.00 (expected: {$expected}).");
                    }
                },
            ],
        ];
    }

    private function convertExcelDate($value)
    {
        if (empty($value)) return null;

        if (is_numeric($value) && $value > 40000) {
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value))
                         ->format('Y-m-d');
        }

        $value = trim((string) $value);

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
            return sprintf('%s-%s-%s',
                $matches[3],
                str_pad($matches[1], 2, '0', STR_PAD_LEFT),
                str_pad($matches[2], 2, '0', STR_PAD_LEFT)
            );
        }

        return $value;
    }

    public function chunkSize(): int
    {
        return 500;
    }
}