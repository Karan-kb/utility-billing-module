<?php

namespace App\Imports;

use App\Helpers\Helper;
use App\Models\MasterSetup;
use App\Models\MemberEntry;
use App\Models\TenantDistrict;
use App\Models\TenantMunicipality;
use App\Models\WiringPerson;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\Importable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MemberEntryImport implements ToCollection, WithHeadingRow, WithValidation
{
    use Importable;

    public $importedCount = 0;
    protected $currentRow = [];

    /**
     * Main Import Logic
     */
    /**
     * Main Import Logic
     */
    public function collection(Collection $rows)
    {
        DB::connection('tenant')->beginTransaction();

        try {
            
            $fiscalYearId = Helper::getActiveFiscalYearId();

            foreach ($rows as $row) {
                $data = $row->toArray();
                $this->currentRow = $data;

                if (isset($data['pan_no']) && is_numeric($data['pan_no'])) {
                    $data['pan_no'] = (string) $data['pan_no'];
                }
                if (isset($data['contact_no']) && is_numeric($data['contact_no'])) {
                    $data['contact_no'] = (string) $data['contact_no'];
                }
                if (isset($data['citizenship_no']) && is_numeric($data['citizenship_no'])) {
                    $data['citizenship_no'] = (string) $data['citizenship_no'];
                }

                if (
                    !TenantDistrict::on('tenant')
                        ->where('id', $data['district_id'])
                        ->where('province_id', $data['province_id'])
                        ->exists()
                ) {
                    throw new \Exception("Row {$row->getRowIndex()}: District does not belong to selected province");
                }

                if (
                    !TenantMunicipality::on('tenant')
                        ->where('id', $data['municipality_id'])
                        ->where('district_id', $data['district_id'])
                        ->exists()
                ) {
                    throw new \Exception("Row {$row->getRowIndex()}: Municipality does not belong to selected district");
                }

                if (
                    MemberEntry::on('tenant')
                        ->where('citizenship_no', $data['citizenship_no'])
                        ->whereNull('deleted_at')
                        ->exists()
                ) {
                    throw new \Exception("Citizenship already exists: {$data['citizenship_no']}");
                }

               

                MemberEntry::on('tenant')->create([
                    'member_no' => $data['member_no'],
                    'fiscal_year_id' => $fiscalYearId,
                    'customer_name_en' => $data['customer_name_en'],
                    'customer_name_np' => $data['customer_name_np'],
                    'citizenship_no' => $data['citizenship_no'],
                    'gender' => $data['gender'],
                    'occupation_id' => $data['occupation_id'],
                    'pan_no' => $data['pan_no'] ?? null,
                    'contact_no' => $data['contact_no'] ?? null,
                    'province_id' => $data['province_id'],
                    'district_id' => $data['district_id'],
                    'municipality_id' => $data['municipality_id'],
                    'ward_no' => $data['ward_no'],
                    'area_id' => $data['area_id'],
                    'house_no' => $data['house_no'] ?? null,
                    'location_description' => $data['location_description'] ?? null,
                    'floor' => $data['floor'] ?? null,
                    'father_or_husband_name' => $data['father_or_husband_name'] ?? null,
                    'grandfather_or_father_in_law_name' => $data['grandfather_or_father_in_law_name'] ?? null,
                    'house_owner_name' => $data['house_owner_name'] ?? null,
                    'is_disable' => $data['is_disable'] ?? 0,
                    'is_active' => $data['is_active'] ?? 1,
                ]);
            }

            $this->importedCount = $rows->count();   // Updated count

            DB::connection('tenant')->commit();
        } catch (\Throwable $e) {
            DB::connection('tenant')->rollBack();
            throw new \Exception("Import failed: " . $e->getMessage());
        }
    }

    /**
     * Validation Rules - No changes needed
     */
    public function rules(): array
    {
        return [
            '*.member_no' => [
                'required',
                'integer',
                function ($attr, $value, $fail) {
                    if (
                        MemberEntry::on('tenant')
                            ->where('member_no', $value)
                            ->whereNull('deleted_at')
                            ->exists()
                    ) {
                        $fail('Member number already exists.');
                    }
                }
            ],
            '*.customer_name_en' => 'required|string|max:100',
            '*.customer_name_np' => 'required|string|max:100',
            '*.citizenship_no' => [
                'required',
                'max:50',
                function ($attr, $value, $fail) {
                    if (MemberEntry::on('tenant')->where('citizenship_no', $value)->exists()) {
                        $fail('Citizenship already exists.');
                    }
                }
            ],
            '*.gender' => 'required|in:0,1,2',
            '*.occupation_id' => [
                'required',
                'integer',
                'exists:tenant.master_setups,id',
                function ($attr, $value, $fail) {
                    if (
                        !MasterSetup::on('tenant')->where('id', $value)
                            ->where('master_setup_type_id', 7)
                            ->where('is_active', 1)
                            ->exists()
                    ) {
                        $fail('Invalid occupation');
                    }
                }
            ],
            '*.pan_no' => [
                'nullable',
                Rule::unique('tenant.member_entries', 'pan_no')
            ],
            '*.contact_no' => ['nullable'],
            '*.province_id' => ['required', 'integer', 'exists:tenant.provinces,id'],
            '*.district_id' => ['required', 'integer'],
            '*.municipality_id' => ['required', 'integer'],
            '*.ward_no' => ['required', 'integer', 'min:1', 'max:50'],
            '*.area_id' => ['required', 'exists:tenant.master_setups,id'],
            '*.house_no' => 'nullable|string|max:50',
            '*.location_description' => 'nullable|string',
            '*.floor' => 'nullable|max:50',
            '*.wiring_person_id' => [
                'nullable',
                function ($attr, $value, $fail) {
                    if ($value && !WiringPerson::on('tenant')->where('id', $value)->exists()) {
                        $fail('Invalid wiring person');
                    }
                }
            ],
            '*.father_or_husband_name' => 'nullable|string|max:50',
            '*.grandfather_or_father_in_law_name' => 'nullable|string|max:50',
            '*.house_owner_name' => 'nullable|string|max:50',
            '*.is_disable' => 'sometimes|boolean',
            '*.is_active' => 'sometimes|boolean',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}