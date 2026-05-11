<?php

namespace App\Imports;

use App\Helpers\Helper;
use App\Helpers\NepaliCalendar;
use App\Models\ShareTransaction;
use App\Models\MemberEntry;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class OpeningShareEntryImport implements ToCollection, WithHeadingRow
{
    protected $payloadDateInBs;

    public $importedCount = 0;
    public $failures = [];    

    protected $readingDateBs;
    protected $readingDateAd;
    protected $fiscalYearId;

    public function __construct($dateInBs = null)
    {
        $this->payloadDateInBs = $dateInBs;
    }

    
    public function collection(Collection $rows)
    {
        $this->fiscalYearId = Helper::getActiveFiscalYearId();

        $this->resolveImportDate();

        DB::connection('tenant')->beginTransaction();

        try {
            $rowNumber = 2; 

            foreach ($rows as $row) {
                $row = collect($row)->mapWithKeys(function ($value, $key) {
                    return [strtolower(trim($key)) => $value];
                });

                $memberNo = $row['member_no'] ?? $row['memberno'] ?? $row['member_number'] ?? null;

                if (empty($memberNo)) {
                    $this->addFailure($rowNumber, $memberNo, 'Member number is missing or empty');
                    $rowNumber++;
                    continue;
                }

                $memberEntry = MemberEntry::on('tenant')
                    ->where('member_no', $memberNo)
                    ->where('is_active', 1)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$memberEntry) {
                    $this->addFailure($rowNumber, $memberNo, 'Member not found or inactive');
                    $rowNumber++;
                    continue;
                }

                $memberEntryId = $memberEntry->id;

                $exists = ShareTransaction::on('tenant')
                    ->withoutTrashed()
                    ->where('member_entry_id', $memberEntryId)
                    ->where('transaction_type', 3)
                    ->exists();

                if ($exists) {
                    $this->addFailure($rowNumber, $memberNo, 'An active share opening entry already exists for this member');
                    $rowNumber++;
                    continue;
                }

                $shareCertificateNo = trim((string)($row['share_certificate_no'] ?? ''));
                if (empty($shareCertificateNo)) {
                    $this->addFailure($rowNumber, $memberNo, 'Share certificate number is required');
                    $rowNumber++;
                    continue;
                }
                if (strlen($shareCertificateNo) > 20) {
                    $this->addFailure($rowNumber, $memberNo, 'Share certificate number must not exceed 20 characters');
                    $rowNumber++;
                    continue;
                }

                $shareQuantity = $this->normalizeInteger($row['share_quantity'] ?? 0);
                if ($shareQuantity < 1) {
                    $this->addFailure($rowNumber, $memberNo, 'Share quantity must be at least 1');
                    $rowNumber++;
                    continue;
                }

                $providedAmount = $this->normalizeAmount($row['amount'] ?? 0);
                $expectedAmount = $shareQuantity * 100.00;
                if (abs($providedAmount - $expectedAmount) > 0.01) {
                    $this->addFailure($rowNumber, $memberNo, "Amount must be equal to share_quantity * 100.00 (expected: {$expectedAmount}). Got: {$providedAmount}");
                    $rowNumber++;
                    continue;
                }

                ShareTransaction::on('tenant')->create([
                    'date_in_bs'           => $this->readingDateBs,
                    'date_in_ad'           => $this->readingDateAd,
                    'member_entry_id'      => $memberEntryId,
                    'fiscal_year_id'       => $this->fiscalYearId,
                    'share_type'           => 'electricity',
                    'transaction_type'     => 3,
                    'share_certificate_no' => $shareCertificateNo,
                    'share_quantity'       => $shareQuantity,
                    'share_value'          => 100.00,
                    'amount'               => $providedAmount,
                ]);

                $rowNumber++;
            }

            // If any failure occurred, rollback and throw custom exception
            if (!empty($this->failures)) {
                DB::connection('tenant')->rollBack();
                throw new ImportValidationException('Validation failed for some rows', $this->failures);
            }

            DB::connection('tenant')->commit();
            $this->importedCount = $rows->count();

        } catch (ImportValidationException $e) {
            DB::connection('tenant')->rollBack();
            throw $e;
        } catch (Exception $e) {
            DB::connection('tenant')->rollBack();
            throw new Exception('Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Determine the BS and AD dates to be used for all rows.
     */
    protected function resolveImportDate(): void
    {
        if (!empty($this->payloadDateInBs)) {
            try {
                $this->readingDateBs = $this->payloadDateInBs;
                $this->readingDateAd = NepaliCalendar::bsToAd($this->readingDateBs);
            } catch (Exception $e) {
                throw new Exception("Invalid BS date provided in payload: {$this->payloadDateInBs}");
            }
        } else {
            $this->readingDateAd = Carbon::now()->format('Y-m-d');
            $this->readingDateBs = NepaliCalendar::adToBs($this->readingDateAd);
        }

        // Additional check: do not allow future date
        if ($this->readingDateAd > date('Y-m-d')) {
            throw new Exception("The date_in_bs cannot be a future date. Provided: {$this->readingDateBs}");
        }
    }

    /**
     * Normalize amount: round to 2 decimals, non-negative.
     */
    private function normalizeAmount($value): float
    {
        $value = (float) $value;
        $value = round($value, 2);
        return ($value < 0) ? 0 : $value;
    }

    /**
     * Normalize integer: round and ensure non-negative.
     */
    private function normalizeInteger($value): int
    {
        $value = (int) round((float) $value);
        return $value < 0 ? 0 : $value;
    }

    /**
     * Add a failure with row number and member number.
     */
    protected function addFailure(int $rowNumber, ?string $memberNo, string $errorMessage): void
    {
        $this->failures[] = [
            'row'        => $rowNumber,
            'member_no'  => $memberNo ?? 'N/A',
            'errors'     => [$errorMessage],
        ];
    }
}

/**
 * Custom exception to carry structured failures.
 */
class ImportValidationException extends Exception
{
    protected $failures;

    public function __construct($message, array $failures = [])
    {
        parent::__construct($message);
        $this->failures = $failures;
    }

    public function getFailures()
    {
        return $this->failures;
    }
}