<?php
namespace App\Imports;

use App\Helpers\Helper;
use App\Models\MeterIssue;
use App\Models\MeterReadingEntry;
use App\Models\FiscalYear;
use App\Services\CustomerTransactionService; 
use App\Helpers\NepaliCalendar;
use App\Models\AdvancePayment;
use App\Services\MemberResolverService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;

class OpeningMahasulFromMeterIssueImport implements ToCollection, WithHeadingRow
{
    protected $allowDuplicates = false;
    protected $CustomerTransactionService;  
    protected $memberResolver;

    public $skippedMessages = [];
    protected $payloadDateInBs;

    public function __construct(
        CustomerTransactionService $CustomerTransactionService,  
        MemberResolverService $memberResolver,
        bool $allowDuplicates = false,
        $dateInBs = null
    ) {
        $this->CustomerTransactionService = $CustomerTransactionService;  
        $this->memberResolver = $memberResolver;
        $this->allowDuplicates = $allowDuplicates;
        $this->payloadDateInBs = $dateInBs;
    }

    public function collection(Collection $rows)
    {
        DB::connection('tenant')->transaction(function () use ($rows) {

            $fiscalYearId = Helper::getActiveFiscalYearId();
            if (!$fiscalYearId) {
                throw new Exception("Active Fiscal Year not found.");
            }

            $ignoredByReason = [
                'Opening Mahasul Entry already exists' => [],
                'Opening Advance Payment already exists' => [],
                'Meter Reading Entry already exists' => [],
            ];

            foreach ($rows as $row) {
                $row = collect($row)->mapWithKeys(function ($value, $key) {
                    $cleanKey = strtolower(trim($key));
                    return [$cleanKey => $value];
                });
                $meterIssueId = $row['meter_issue_id'] ?? $row['meterid'] ?? null;
                $unitAmount = $row['unit_amount'] ?? $row['unit'] ?? 0;
                $fineAmount = $row['fine_amount'] ?? $row['fine'] ?? 0;
                $demandCharge = $row['demand_charge'] ?? $row['demand'] ?? 0;
                $subsidyCharge = $row['subsidy_charge'] ?? $row['subsidy'] ?? 0;
                $serviceCharge = $row['service_charge'] ?? $row['service'] ?? 0;
                $otherCharge = $row['other_charge'] ?? $row['other'] ?? 0;
                $totalCharge = $row['total_charge'] ?? $row['total'] ?? 0;

                if (!$meterIssueId) {
                    throw new Exception("Meter Issue ID is missing.");
                }

                $meterIssue = MeterIssue::where('id', $meterIssueId)
                    ->where('is_active', 1)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$meterIssue) {
                    throw new Exception("Meter Issue ID {$meterIssueId} not found or inactive.");
                }

                if (
                    MeterReadingEntry::where('meter_issue_id', $meterIssueId)
                        ->where('entry_type', 2)->exists() && !$this->allowDuplicates
                ) {

                    $ignoredByReason['Opening Mahasul Entry already exists'][] = $meterIssueId;
                    continue;
                }

                if (
                    AdvancePayment::where('meter_issue_id', $meterIssueId)
                        ->where('type', 1)->exists()
                ) {

                    $ignoredByReason['Opening Advance Payment already exists'][] = $meterIssueId;
                    continue;
                }

                if (
                    MeterReadingEntry::where('meter_issue_id', $meterIssueId)
                        ->where('entry_type', 1)->exists()
                ) {

                    $ignoredByReason['Meter Reading Entry already exists'][] = $meterIssueId;
                    continue;
                }

                $sum = $unitAmount + $fineAmount + $demandCharge + $subsidyCharge + $serviceCharge + $otherCharge;

                $sum = round((float) $sum, 2);
                $totalCharge = round((float) $totalCharge, 2);

                if ($sum != $totalCharge) {
                    throw new Exception("Total charge mismatch for Meter Issue ID {$meterIssueId}. Expected {$sum}, got {$totalCharge}.");
                }

                if (!empty($this->payloadDateInBs)) {
                    try {
                        $readingDateBs = $this->payloadDateInBs;
                        $readingDateAd = NepaliCalendar::bsToAd($readingDateBs);
                    } catch (\Exception $e) {
                        throw new Exception("Invalid BS date provided in payload.");
                    }
                } else {
                    $readingDateAd = Carbon::now()->format('Y-m-d');
                    $readingDateBs = NepaliCalendar::adToBs($readingDateAd);
                }

                $meterReadingEntry = MeterReadingEntry::create([
                    'meter_issue_id' => $meterIssueId,
                    'unit_amount' => $unitAmount,
                    'fine_amount' => $fineAmount,
                    'total_charge' => $totalCharge,
                    'sub_total_charge' => $totalCharge - $fineAmount,
                    'entry_type' => 2,
                    'status' => 0,
                    'fiscal_year_id' => $fiscalYearId,
                    'reading_date_in_ad' => $readingDateAd,
                    'reading_date_in_bs' => $readingDateBs,
                ]);

                $memberEntryId = $this->memberResolver->getMemberEntryIdFromMeterIssue($meterIssueId);

                $charges = [
                    'unit_amount' => $unitAmount,
                    'demand_charge' => $demandCharge,
                    'service_charge' => $serviceCharge,
                    'subsidy_charge' => $subsidyCharge,
                    'other_charge' => $otherCharge,
                    'fine_charge' => $fineAmount,
                ];

                $this->CustomerTransactionService->createForOpeningMahasul(
                    $memberEntryId,
                    $meterReadingEntry->id,
                    $charges,
                    $readingDateAd
                );
            }

            foreach ($ignoredByReason as $reason => $ids) {
                if (!empty($ids)) {
                    $this->skippedMessages[] =
                        "{$reason} for Meter Issue IDs: " . implode(', ', $ids);
                }
            }
        });
    }
}