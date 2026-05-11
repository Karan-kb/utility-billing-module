<?php
namespace App\Imports;

use App\Models\MeterIssue;
use App\Models\MeterReadingEntry;
use App\Models\AdvancePayment;
use App\Services\CustomerTransactionService;
use App\Services\MemberResolverService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;

class OpeningAdvancefromMeterIssueIdImport implements ToCollection, WithHeadingRow
{
    protected $customerTransactionService;
    protected $memberResolver;

    public $skippedMessages = []; 

    public function __construct(
        CustomerTransactionService $customerTransactionService,
        MemberResolverService $memberResolver
    ) {
        $this->customerTransactionService = $customerTransactionService;
        $this->memberResolver = $memberResolver;
    }

    public function collection(Collection $rows)
    {
        DB::connection('tenant')->transaction(function () use ($rows) {

            $ignoredByReason = [
                'Meter Reading Entry (entry_type=2) already exists' => [],
                'Meter Reading Entry (entry_type=1) already exists' => [],
                'Opening Advance Payment already exists' => [],
            ];

            foreach ($rows as $row) {

                $meterIssueId = $row['meter_issue_id'] ?? null;
                $amount = $row['amount'] ?? 0;

                if (!$meterIssueId) {
                    throw new Exception("Meter Issue ID is missing in row.");
                }

                $meterIssue = MeterIssue::where('id', $meterIssueId)
                    ->where('is_active', 1)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$meterIssue) {
                    throw new Exception("Meter Issue ID {$meterIssueId} not found or inactive.");
                }

                if (MeterReadingEntry::where('meter_issue_id', $meterIssueId)
                    ->where('entry_type', 2)->exists()) {

                    $ignoredByReason['Opening Mahasul Entry already exists'][] = $meterIssueId;
                    continue;
                }

                if (MeterReadingEntry::where('meter_issue_id', $meterIssueId)
                    ->where('entry_type', 1)->exists()) {

                    $ignoredByReason['Meter Reading Entry already exists'][] = $meterIssueId;
                    continue;
                }

                if (AdvancePayment::where('meter_issue_id', $meterIssueId)
                    ->where('type', 1)->exists()) {

                    $ignoredByReason['Opening Advance Payment already exists'][] = $meterIssueId;
                    continue;
                }

                $readingDateAd = Carbon::now()->format('Y-m-d');
                $readingDateBs = \App\Helpers\NepaliCalendar::adToBs($readingDateAd);

                $entry = AdvancePayment::create([
                    'type' => 1,
                    'voucher_no' => null,
                    'date_in_bs' => $readingDateBs,
                    'date_in_ad' => $readingDateAd,
                    'meter_issue_id' => $meterIssueId,
                    'amount' => $amount,
                ]);

                $memberEntryId = $this->memberResolver->getMemberEntryIdFromMeterIssue($meterIssueId);

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
                        "{$reason} for Meter Issue IDs: " . implode(', ', $ids);
                }
            }
        });
    }
}