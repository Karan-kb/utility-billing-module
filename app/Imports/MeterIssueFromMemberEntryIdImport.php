<?php

namespace App\Imports;

use App\Helpers\Helper;
use App\Helpers\NepaliCalendar;
use App\Helpers\TenantRuntimeHelper;
use App\Models\MasterSetup;
use App\Models\MeterIssue;
use App\Models\MemberEntry;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MeterIssueFromMemberEntryIdImport implements ToCollection, WithHeadingRow, WithValidation
{
    use Importable;

    public $importedCount = 0;
    protected $currentRow = [];

    /**
     * Main Import Logic - ALL OR NOTHING
     */
    public function collection(Collection $rows)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            $fiscalYearId = Helper::getActiveFiscalYearId();
            $isKhanepani = TenantRuntimeHelper::isRuntimeKhanepani();

            $batch = [];

            foreach ($rows as $row) {
                $data = $row->toArray();
                $this->currentRow = $data;

                $issueDateBs = $this->convertExcelDate($data['issue_date_bs'] ?? null);
                $issueDateAd = null;

                if ($issueDateBs) {
                    try {
                        $issueDateAd = NepaliCalendar::bsToAd($issueDateBs);
                    } catch (\Exception $e) {
                        throw new \Exception("Row {$row->getRowIndex()}: Invalid BS date - {$issueDateBs}");
                    }
                }

                if (empty($issueDateAd) && !empty($data['issue_date_ad'])) {
                    $issueDateAd = $this->convertExcelDate($data['issue_date_ad']);
                }

                $insertData = [
                    'member_entry_id' => $data['member_entry_id'],
                    'meter_no' => $data['meter_no'],
                    'transformer_id' => $data['transformer_id'],
                    'meter_start_no' => $data['meter_start_no'],
                    'issue_date_bs' => $issueDateBs,
                    'issue_date_ad' => $issueDateAd,
                    'construct_company' => $data['construct_company'] ?? null,
                    'issue_meter_capacity' => $data['issue_meter_capacity'] ?? null,
                    'reading_seal_no' => $data['reading_seal_no'] ?? null,
                    'terminal_seal_no' => $data['terminal_seal_no'] ?? null,
                    'meter_box_seal_no' => $data['meter_box_seal_no'] ?? null,
                    'pole_no' => $data['pole_no'] ?? null,
                    'pole_distance' => $data['pole_distance'] ?? null,
                    'area_id' => $data['area_id'],
                    'is_active' => $data['is_active'] ?? 1,
                    'meter_issue_record' => 0,
                    'fiscal_year_id' => $fiscalYearId,
                ];

                // Only add these for non-Khanepani
                if (!$isKhanepani) {
                    $insertData['phase_id'] = $data['phase_id'] ?? null;
                    $insertData['capacity_id'] = $data['capacity_id'] ?? null;
                    $insertData['purpose_id'] = $data['purpose_id'] ?? null;
                }

                $batch[] = $insertData;
            }

            if (!empty($batch)) {
                MeterIssue::on('tenant')->insert($batch);
            }

            $this->importedCount = count($batch);

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
        if (empty($value))
            return null;

        // Excel serial number
        if (is_numeric($value) && $value > 40000) {
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value))
                ->format('Y-m-d');
        }

        $value = trim((string) $value);

        // Convert 12/25/2082 → 2082-12-25
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
            $month = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            return "{$year}-{$month}-{$day}";
        }

        return $value;
    }


    public function rules(): array
    {
        $rules = [
            '*.member_entry_id' => [
                'required',
                'integer',
                'exists:tenant.member_entries,id,deleted_at,NULL,is_active,1',
                function ($attribute, $value, $fail) {
                    $memberEntry = MemberEntry::on('tenant')
                        ->where('id', $value)
                        ->where('is_active', 1)
                        ->first();

                    if (!$memberEntry) {
                        $fail("Invalid member number.");
                        return;
                    }

                    $exists = MeterIssue::on('tenant')
                        ->where('member_entry_id', $memberEntry->id)
                        ->exists();

                    if ($exists) {
                        $fail("A meter has already been issued for this member.");
                    }
                },
            ],

            '*.meter_no' => [
                'required',
                'max:15',
                Rule::unique('tenant.meter_issues', 'meter_no'),
            ],

            '*.transformer_id' => [
                'required',
                'integer',
                'exists:tenant.master_setups,id',
                function ($attribute, $value, $fail) {
                    if (
                        !MasterSetup::on('tenant')->where('id', $value)
                            ->where('master_setup_type_id', 5)
                            ->where('is_active', 1)
                            ->whereNull('deleted_at')
                            ->exists()
                    ) {
                        $fail('The selected transformer must have type Transformer, be active, and not deleted!');
                    }
                },
            ],

            '*.meter_start_no' => 'required|numeric|min:0',

            '*.issue_date_bs' => [
                'required',
                function ($attribute, $value, $fail) {
                    $bsDate = $this->convertExcelDate($value);

                    if (empty($bsDate)) {
                        $fail('The issue_date_bs field is required.');
                        return;
                    }

                    try {
                        $adDate = NepaliCalendar::bsToAd($bsDate);
                        $today = date('Y-m-d');
                        if ($adDate > $today) {
                            $fail('The issue_date_bs cannot be a future date.');
                        }
                    } catch (\Exception $e) {
                        $fail('The issue_date_bs is not a valid BS date.');
                    }
                },
            ],

            '*.issue_date_ad' => 'nullable|date',

            '*.construct_company' => 'nullable|string|max:50',
            '*.issue_meter_capacity' => 'nullable|string|max:50',
            '*.reading_seal_no' => 'nullable|string|max:50',
            '*.terminal_seal_no' => 'nullable|string|max:50',
            '*.meter_box_seal_no' => 'nullable|string|max:50',
            '*.pole_no' => 'nullable|string|max:50',
            '*.pole_distance' => 'nullable|string|max:100',

            '*.area_id' => [
                'required',
                'integer',
                'exists:tenant.master_setups,id',
                function ($attribute, $value, $fail) {
                    if (
                        !MasterSetup::on('tenant')->where('id', $value)
                            ->where('master_setup_type_id', 6)
                            ->where('is_active', 1)
                            ->whereNull('deleted_at')
                            ->exists()
                    ) {
                        $fail('The selected reading_area must have type Area, be active, and not deleted.');
                    }
                },
            ],

            '*.is_active' => 'sometimes|boolean',
        ];

        if (!TenantRuntimeHelper::isRuntimeKhanepani()) {
            $rules['*.phase_id'] = [
                'required',
                'integer',
                'exists:tenant.master_setups,id',
                function ($attribute, $value, $fail) {
                    if (
                        !MasterSetup::on('tenant')->where('id', $value)
                            ->where('master_setup_type_id', 3)
                            ->where('is_active', 1)
                            ->whereNull('deleted_at')
                            ->exists()
                    ) {
                        $fail('The selected demand_phase must have type Phase, be active, and not deleted!');
                    }
                },
            ];

            $rules['*.capacity_id'] = [
                'required',
                'integer',
                'exists:tenant.master_setups,id',
                function ($attribute, $value, $fail) {
                    if (
                        !MasterSetup::on('tenant')->where('id', $value)
                            ->where('master_setup_type_id', 4)
                            ->where('is_active', 1)
                            ->whereNull('deleted_at')
                            ->exists()
                    ) {
                        $fail('The selected demand_capacity must have type Capacity, be active, and not deleted.');
                    }
                },
            ];

            $rules['*.purpose_id'] = [
                'required',
                'integer',
                'exists:tenant.master_setups,id',
                function ($attribute, $value, $fail) {
                    if (
                        !MasterSetup::on('tenant')->where('id', $value)
                            ->where('master_setup_type_id', 1)
                            ->where('is_active', 1)
                            ->whereNull('deleted_at')
                            ->exists()
                    ) {
                        $fail('The selected purpose_type must have type Purpose of Use, be active, and not deleted.');
                    }
                },
            ];
        }

        return $rules;
    }

    public function chunkSize(): int
    {
        return 500;
    }
}