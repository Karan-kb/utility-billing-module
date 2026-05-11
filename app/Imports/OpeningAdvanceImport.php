<?php

namespace App\Imports;

use App\Models\MeterIssue;
use App\Models\MeterReadingEntry;
use App\Models\AdvancePayment;
use App\Services\CustomerTransactionService;
use App\Services\MeterIssueResolverService; 
use App\Helpers\NepaliCalendar;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;

class OpeningAdvanceImport implements ToCollection, WithHeadingRow
{
    protected $customerTransactionService;
    protected $meterIssueResolver;
protected $hasSkipped = false;
    protected $payloadDateInBs;

    public $skippedMessages = [];

    public function __construct(
        CustomerTransactionService $customerTransactionService,
        MeterIssueResolverService $meterIssueResolver,
        $dateInBs = null 
    ) {
        $this->customerTransactionService = $customerTransactionService;
        $this->meterIssueResolver = $meterIssueResolver;
        $this->payloadDateInBs = $dateInBs;
    }

    public function collection(Collection $rows)
    {
        DB::connection('tenant')->transaction(function () use ($rows) {

            $ignoredByReason = [
                'Opening Mahasul Entry already exists' => [],
                'Meter Reading Entry already exists' => [],
                'Opening Advance Payment already exists' => [],
                'Member or Meter Issue not found' => [],
            ];

            foreach ($rows as $row) {

                $row = collect($row)->mapWithKeys(function ($value, $key) {
                    return [strtolower(trim($key)) => $value];
                });

                $memberNo = $row['member_no'] ?? $row['memberno'] ?? $row['member_number'] ?? null;

               if (!$memberNo) {
                $ignoredByReason['Member or Meter Issue not found'][] = 'EMPTY_MEMBER_NO';
                $this->hasSkipped = true;
                continue;
            }

                try {
                    $meterIssueId = $this->meterIssueResolver
                        ->getMeterIssueIdFromMemberNo($memberNo);
                } catch (\Exception $e) {
                    $ignoredByReason['Member or Meter Issue not found'][] = $memberNo;
                    $this->hasSkipped = true;
                    continue;
                }

               $amount = $this->normalizeAmount($row['amount'] ?? 0);

                $meterIssue = MeterIssue::where('id', $meterIssueId)
                    ->where('is_active', 1)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$meterIssue) {
                    $ignoredByReason['Member or Meter Issue not found'][] = $memberNo;
                    $this->hasSkipped = true;
                    continue;
                }

                if (MeterReadingEntry::where('meter_issue_id', $meterIssueId)
                    ->where('entry_type', 2)->exists()) {

                    $ignoredByReason['Opening Mahasul Entry already exists'][] = $memberNo;
                    $this->hasSkipped = true;
                    continue;
                }

                if (MeterReadingEntry::where('meter_issue_id', $meterIssueId)
                    ->where('entry_type', 1)->exists()) {

                    $ignoredByReason['Meter Reading Entry already exists'][] = $memberNo;
                    $this->hasSkipped = true;
                    continue;
                }

                if (AdvancePayment::where('meter_issue_id', $meterIssueId)
                    ->where('type', 1)->exists()) {

                    $ignoredByReason['Opening Advance Payment already exists'][] = $memberNo;
                    $this->hasSkipped = true;
                    continue;
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

                $entry = AdvancePayment::create([
                    'type' => 1,
                    'voucher_no' => null,
                    'date_in_bs' => $readingDateBs,
                    'date_in_ad' => $readingDateAd,
                    'meter_issue_id' => $meterIssueId,
                    'amount' => $amount,
                ]);

                $memberEntryId = $meterIssue->member_entry_id;

               \App\Models\CustomerTransaction::create([
                    'member_entry_id' => $memberEntryId,
                    'transaction_date' => $readingDateAd,
                    'transaction_type' => 6,
                    'charge_type' => 9,
                    'amount' => $amount,
                    'direction' => 'CR',
                    'reference_id' => $entry->id,
                ]);
            }

            foreach ($ignoredByReason as $reason => $ids) {
                    if (!empty($ids)) {
                        $this->skippedMessages[] =
                            "{$reason} for Member Numbers: " . implode(', ', $ids);
                    }
                }

                if ($this->hasSkipped) {
                    throw new Exception(implode(' | ', $this->skippedMessages));
                }
        });
    }
    private function normalizeAmount($value): float
{
    $value = (float) $value;
    $value = round($value, 2);
    return (abs($value) < 0.01) ? 0 : $value;
}
}