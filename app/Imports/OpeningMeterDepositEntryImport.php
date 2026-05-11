<?php

namespace App\Imports;

use App\Helpers\Helper;
use App\Helpers\NepaliCalendar;
use App\Models\MeterDepositTransaction;
use App\Models\MeterIssue;
use App\Services\MeterIssueResolverService;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class OpeningMeterDepositEntryImport implements ToCollection, WithHeadingRow
{
    protected $meterIssueResolver;
    protected $payloadDateInBs;

    public $importedCount = 0;
    public $failures = [];      

    protected $readingDateBs;
    protected $readingDateAd;
    protected $fiscalYearId;

    public function __construct(MeterIssueResolverService $meterIssueResolver, $dateInBs = null)
    {
        $this->meterIssueResolver = $meterIssueResolver;
        $this->payloadDateInBs = $dateInBs;
    }

    /**
     * Main import logic – all or nothing.
     */
    public function collection(Collection $rows)
    {
        // 1. Resolve fiscal year once
        $this->fiscalYearId = Helper::getActiveFiscalYearId();

        // 2. Resolve the date to be used for all rows
        $this->resolveImportDate();

        DB::connection('tenant')->beginTransaction();

        try {
            $rowNumber = 2; // heading row is row 1

            foreach ($rows as $row) {
                // Normalise keys to lowercase
                $row = collect($row)->mapWithKeys(function ($value, $key) {
                    return [strtolower(trim($key)) => $value];
                });

                $memberNo = $row['member_no'] ?? $row['memberno'] ?? $row['member_number'] ?? null;

                // Validate member_no presence
                if (empty($memberNo)) {
                    $this->addFailure($rowNumber, $memberNo, 'Member number is missing or empty');
                    $rowNumber++;
                    continue;
                }

                // Resolve meter_issue_id from member_no
                try {
                    $meterIssueId = $this->meterIssueResolver->getMeterIssueIdFromMemberNo($memberNo);
                } catch (Exception $e) {
                    $this->addFailure($rowNumber, $memberNo, 'Member not found or meter issue not active');
                    $rowNumber++;
                    continue;
                }

                // Verify meter issue exists and is active
                $meterIssue = MeterIssue::where('id', $meterIssueId)
                    ->where('is_active', 1)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$meterIssue) {
                    $this->addFailure($rowNumber, $memberNo, 'Meter issue is not active or does not exist');
                    $rowNumber++;
                    continue;
                }

                // Check for existing opening meter deposit (transaction_type = 4)
                $exists = MeterDepositTransaction::withoutTrashed()
                    ->where('meter_issue_id', $meterIssueId)
                    ->where('transaction_type', 4)
                    ->exists();

                if ($exists) {
                    $this->addFailure($rowNumber, $memberNo, 'An active opening meter deposit already exists for this meter');
                    $rowNumber++;
                    continue;
                }

                // Normalize amount
                $amount = $this->normalizeAmount($row['amount'] ?? 0);

                // Create deposit transaction
                MeterDepositTransaction::create([
                    'date_in_bs'       => $this->readingDateBs,
                    'date_in_ad'       => $this->readingDateAd,
                    'meter_issue_id'   => $meterIssueId,
                    'fiscal_year_id'   => $this->fiscalYearId,
                    'transaction_type' => 4,          // Fixed for opening deposit
                    'amount'           => $amount,
                    'service_charge'   => 0,
                    'is_cancel'        => 0,
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