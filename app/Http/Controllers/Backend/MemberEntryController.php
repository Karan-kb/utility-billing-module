<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Imports\MemberEntryImport;
use App\Models\AccountHead;
use App\Models\District;
use App\Models\MemberEntry;
use App\Models\MasterSetup;
use App\Models\MeterIssue;
use App\Models\Municipality;
use App\Models\TenantDistrict;
use App\Models\TenantMunicipality;
use App\Models\WiringPerson;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Sagautam5\LocalStateNepal\Entities\Province;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class MemberEntryController extends Controller
{
    protected $province;

    public function __construct()
    {
        $this->province = new Province('en');
    }

    public function location()
    {
        try {
            $provincesData = $this->province->getProvincesWithDistrictsWithMunicipalities();
            return response()->json($provincesData);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching location data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function generateCustomerId(): JsonResponse
    {
        try {
            $latestMember = MemberEntry::orderBy('id', 'desc')
                ->first();

            if ($latestMember && preg_match('/(\d+)/', $latestMember->member_no, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            } else {
                $nextNumber = 1;
            }

            $customerId = (string) $nextNumber;

            while (MemberEntry::where('member_no', $customerId)->exists()) {
                $nextNumber++;
                $customerId = $nextNumber;
            }

            return response()->json(['member_no' => $customerId]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while generating customer ID !',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function toggleActiveStatus(Request $request, $id)
    {
        try {
            $memberEntry = MemberEntry::findOrFail($id);

            $memberEntry->is_active = !$memberEntry->is_active;
            $memberEntry->save();

            $meterIssues = MeterIssue::where('member_no', $memberEntry->member_no)
                ->get();

            foreach ($meterIssues as $issue) {
                $issue->is_active = $memberEntry->is_active;
                $issue->save();
            }

            return response()->json([
                'message' => "Member entry {$memberEntry->customer_name_en} active status updated to " . ($memberEntry->is_active ? 'active' : 'inactive'),
                'is_active' => $memberEntry->is_active
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Member entry not found or already deleted'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while toggling the member entry active status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function listOccupationIds(Request $request): JsonResponse
    {


        try {
            $occupations = MasterSetup::withoutTrashed()
                ->where('master_setup_type_id', 7)
                ->where('is_active', 1)
                ->select('id', 'master_setup_type_id', 'name_en', 'name_np')
                ->get()
                ->map(function ($occupation) {
                    return [
                        'id' => $occupation->id,
                        'master_setup_type_id' => $occupation->master_setup_type_id,
                        'name_en' => $occupation->name_en,
                        'name_np' => $occupation->name_np,
                    ];
                })->values();

            return response()->json(['occupations' => $occupations], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching occupation data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    public function listAreaIds(Request $request)
    {


        try {
            $areas = MasterSetup::withoutTrashed()
                ->where('master_setup_type_id', 6)
                ->where('is_active', 1)
                ->select('id', 'master_setup_type_id', 'name_en', 'name_np')
                ->get()
                ->map(function ($area) {
                    return [
                        'id' => $area->id,
                        'master_setup_type_id' => $area->master_setup_type_id,
                        'name_en' => $area->name_en,
                        'name_np' => $area->name_np,
                    ];
                })->values();

            return response()->json(['areas' => $areas], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching area data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


public function listBankIds(Request $request)
{
    try {
        $banks = AccountHead::on('tenant')
            ->where('account_group_id', 10)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'name_np'])
            ->map(function ($bank) {
                return [
                    'id' => $bank->id,
                    'name_en' => $bank->name,
                    'name_np' => $bank->name_np,
                ];
            });

        return response()->json([
            'message' => 'Active banks fetched successfully',
            'data' => $banks
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while fetching banks',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    public function getById(Request $request, $id)
    {
        try {
            $memberEntry = MemberEntry::findOrFail($id);
            $response = $memberEntry->toArray();

            $memberEntry = MemberEntry::with(['province', 'district', 'municipality', 'area', 'occupation'])
                ->findOrFail($id);
            $response['province_name_en'] = $memberEntry->province?->name_en;
            $response['province_name_np'] = $memberEntry->province?->name_np;
            $response['district_name_en'] = $memberEntry->district?->name_en;
            $response['district_name_np'] = $memberEntry->district?->name_np;
            $response['municipality_name_en'] = $memberEntry->municipality?->name_en;
            $response['municipality_name_np'] = $memberEntry->municipality?->name_np;

            $response['area_name_en'] = $memberEntry->area?->name_en ?? null;
            $response['area_name_np'] = $memberEntry->area?->name_np ?? null;

            $response['occupation_name_en'] = $memberEntry->occupation?->name_en ?? null;
            $response['occupation_name_np'] = $memberEntry->occupation?->name_np ?? null;

            $response['gender'] = match ($memberEntry->gender) {
                0 => 'Male',
                1 => 'Female',
                2 => 'Others',
                default => null,
            };

            return response()->json(['member_entry' => $response], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Member entry not found or already deleted',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching the member entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    public function listMemberEntries(Request $request)
    {
        try {
            $search = $request->query('search');
            $gender = $request->query('gender');
            $areaId = $request->query('area_id');
            $meterNo = $request->query('meter_no');

            $entriesQuery = MemberEntry::with([
                    'area' => function ($query) {
                        $query->where('master_setup_type_id', 6)
                            ->where('is_active', 1)
                            ->select('id', 'master_setup_type_id', 'name_en');
                    },
                    'meterIssues' => function ($query) {
                        $query->where('is_active', 1)
                            ->select('member_entry_id', 'meter_no');
                    }
                ]);

            if (!empty($search)) {
                $entriesQuery->where(function ($query) use ($search) {
                    $query->where('member_no', 'like', "%{$search}%")
                        ->orWhere('customer_name_en', 'like', "%{$search}%")
                        ->orWhere('customer_name_np', 'like', "%{$search}%")
                        ->orWhereHas('meterIssues', function ($q) use ($search) {
                            $q->where('meter_no', "{$search}")
                                ->where('is_active', 1);
                        });
                });
            }


            if (!empty($gender)) {
                $entriesQuery->where('gender', $gender);
            }

            if (!empty($areaId)) {
                $entriesQuery->where('area_id', $areaId);
            }

            if (!empty($meterNo)) {
                $entriesQuery->whereHas('meterIssues', function ($query) use ($meterNo) {
                    $query->where('meter_no', 'like', "%{$meterNo}%")
                        ->where('is_active', 1);
                });
            }

            $entries = $entriesQuery->paginate(10)
                ->through(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'member_no' => $entry->member_no,
                        'customer_name_en' => $entry->customer_name_en,
                        'customer_name_np' => $entry->customer_name_np,
                        'is_disable' => $entry->is_disable,
                        'citizenship_no' => $entry->citizenship_no,
                        'gender' => match ($entry->gender) {
                            0 => 'Male',
                            1 => 'Female',
                            2 => 'Others',
                            default => null,
                        },
                        'occupation_id' => $entry->occupation_id,
                        'pan_no' => $entry->pan_no,
                        'father_or_husband_name' => $entry->father_or_husband_name,
                        'grandfather_or_father_in_law_name' => $entry->grandfather_or_father_in_law_name,
                        'house_owner_name' => $entry->house_owner_name,
                        'contact_no' => $entry->contact_no,
                        'province_id' => $entry->province_id,
                        'district_id' => $entry->district_id,
                        'municipality_id' => $entry->municipality_id,
                        'ward_no' => $entry->ward_no,
                        'area_id' => $entry->area_id,
                        'area_name_en' => $entry->area ? $entry->area->name_en : null,
                        'house_no' => $entry->house_no,
                        'location_description' => $entry->location_description,
                        'floor' => $entry->floor,
                        'wiring_person_name' => $entry->wiring_person_name,
                        'wiring_person_no' => $entry->wiring_person_no,
                        'customer_photo' => $entry->customer_photo,
                        'citizenship_front' => $entry->citizenship_front,
                        'citizenship_back' => $entry->citizenship_back,
                        'is_active' => $entry->is_active,
                        'meter_no' => $entry->meterIssues->first()?->meter_no,
                        'created_at' => $entry->created_at,
                        'updated_at' => $entry->updated_at,
                    ];
                });

            return response()->json($entries);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while listing member entries',
                'error' => $e->getMessage(),
            ], 500);
        }
    }






    public function createMemberEntry(Request $request)
    {

        try {

            $validated = $request->validate([
                'member_no' => [
                    'required',
                    'regex:/^\d+$/'

                ],

                'customer_name_en' => 'required|string|max:100',
                'customer_name_np' => 'required|string|max:100',
                'citizenship_no' => [
                    'required',
                    'string',
                    'max:50',

                    function ($attr, $value, $fail) {
                        if (MemberEntry::where('citizenship_no', $value)->exists()) {
                            $fail('The citizenship number is already used by another active member.');
                        }
                    },
                ],
                'gender' => 'required|in:0,1,2',

                'occupation_id' => [
                    'required',
                    'integer',
                    'exists:tenant.master_setups,id',
                    function ($attr, $value, $fail) {
                        if (
                            !MasterSetup::where('id', $value)
                                ->where('master_setup_type_id', 7)
                                ->where('is_active', 1)
                                ->whereNull('deleted_at')
                                ->exists()
                        ) {
                            $fail('Invalid occupation. It must belong to the Occupation type and be active.');
                        }
                    },
                ],

                'pan_no' => ['nullable', 'string', Rule::unique('tenant.member_entries', 'pan_no')],

                'contact_no' => ['nullable', 'string'],

                'province_id' => [
                    'required',
                    'integer',
                    Rule::exists('tenant.provinces', 'id'),
                ],

                'district_id' => [
                    'required',
                    'integer',
                    function ($attr, $value, $fail) use ($request) {
                        if (
                            !TenantDistrict::where('id', $value)
                                ->where('province_id', $request->province_id)
                                ->exists()
                        ) {
                            $fail("Selected district does not belong to the chosen province.");
                        }
                    }
                ],

                'municipality_id' => [
                    'required',
                    'integer',
                    function ($attr, $value, $fail) use ($request) {
                        if (
                            !TenantMunicipality::where('id', $value)
                                ->where('district_id', $request->district_id)
                                ->exists()
                        ) {
                            $fail("Selected municipality does not belong to the chosen district.");
                        }
                    }
                ],

                'ward_no' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:50',
                ],
                'area_id' => ['required', 'exists:tenant.master_setups,id'],

                'house_no' => ['nullable', 'string', 'max:50',],
                'location_description' => 'nullable|string',
                'floor' => 'nullable|string|max:50',
                'wiring_person_id' => [
                    'nullable',
                    function ($attr, $value, $fail) {
                        if ($value && !WiringPerson::where('id', $value)->whereNull('deleted_at')->exists()) {
                            $fail('Selected wiring person is invalid or has been deleted.');
                        }
                    },
                ],
                'father_or_husband_name' => 'nullable|string|max:50',
                'grandfather_or_father_in_law_name' => 'nullable|string|max:50',
                'house_owner_name' => 'nullable|string|max:50',
                'customer_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'citizenship_front' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'citizenship_back' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
                'is_disable' => 'sometimes|boolean',

                'is_active' => 'sometimes|boolean',
            ]);

            $data = $validated;

            foreach (['customer_photo', 'citizenship_front', 'citizenship_back'] as $fileField) {
                if ($request->hasFile($fileField) && $request->file($fileField)->isValid()) {
                    $folder = match ($fileField) {
                        'customer_photo' => 'uploads/customers',
                        'citizenship_front', 'citizenship_back' => 'uploads/citizenships',
                    };
                    $data[$fileField] = $this->resizeImage($request->file($fileField), $folder);
                } else {
                    $data[$fileField] = null;
                }
            }
            $fiscalYearId = Helper::getActiveFiscalYearId();
            $data['fiscal_year_id'] = $fiscalYearId;
            $maxMemberNo = MemberEntry::max('member_no');
            $memberNo = max($validated['member_no'], $maxMemberNo + 1);
            $data['member_no'] = $memberNo;

            $memberEntry = MemberEntry::create($data);

            return response()->json([
                'message' => "Member entry {$memberEntry->customer_name_en} created successfully",
                'data' => $memberEntry,
            ], 201);

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();

            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors,
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred while creating the member entry !',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while creating the member entry !',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function editMemberEntry(Request $request, $id)
    {

        $memberEntry = MemberEntry::findOrFail($id);

        try {
            $input = $request->all();

            $validator = Validator::make($input, [

                'customer_name_en' => 'required|string|max:255',
                'customer_name_np' => 'required|string|max:255',

                'citizenship_no' => [
                    'required',
                    'string',
                    'max:50',

                    function ($attr, $value, $fail) use ($memberEntry) {
                        if (
                            MemberEntry::where('citizenship_no', $value)
                                ->where('id', '!=', $memberEntry->id)
                                ->exists()
                        ) {
                            $fail('The citizenship number is already used by another active member.');
                        }
                    },
                ],

                'gender' => 'required|in:0,1,2',

                'occupation_id' => [
                    'required',
                    'integer',
                    'exists:tenant.master_setups,id',
                    function ($attr, $value, $fail) {
                        if (
                            !MasterSetup::where('id', $value)
                                ->where('master_setup_type_id', 7)
                                ->where('is_active', 1)
                                ->whereNull('deleted_at')
                                ->exists()
                        ) {
                            $fail('Invalid occupation. It must belong to the Occupation type and be active.');
                        }
                    },
                ],

                'pan_no' => [
                    'nullable',
                    'string',
                    Rule::unique('tenant.member_entries', 'pan_no')
                        
                        ->ignore($memberEntry->id)
                ],

                'contact_no' => [
                    'nullable',
                    'string'
                ],
                'father_or_husband_name' => 'nullable|string|max:50',
                'grandfather_or_father_in_law_name' => 'nullable|string|max:50',
                'house_owner_name' => 'nullable|string|max:50',

                'province_id' => [
                    'required',
                    'integer',
                    Rule::exists('tenant.provinces', 'id'),
                ],

                'district_id' => [
                    'required',
                    'integer',
                    function ($attr, $value, $fail) use ($request) {
                        if (
                            !TenantDistrict::where('id', $value)
                                ->where('province_id', $request->province_id)
                                ->exists()
                        ) {
                            $fail("Selected district does not belong to the chosen province.");
                        }
                    }
                ],

                'municipality_id' => [
                    'required',
                    'integer',
                    function ($attr, $value, $fail) use ($request) {
                        if (
                            !TenantMunicipality::where('id', $value)
                                ->where('district_id', $request->district_id)
                                ->exists()
                        ) {
                            $fail("Selected municipality does not belong to the chosen district.");
                        }
                    }
                ],

                'ward_no' => [
                    'required',
                    'integer',
                    'max:50',
                ],
                'area_id' => ['required', 'exists:tenant.master_setups,id'],

                'house_no' => [
                    'nullable',
                    'string',
                    'max:50'
                ],

                'location_description' => 'nullable|string',
                'floor' => 'nullable|string|max:50',
                'wiring_person_id' => [
                    'nullable',
                    function ($attribute, $value, $fail) {
                        if ($value && !WiringPerson::where('id', $value)->whereNull('deleted_at')->exists()) {
                            $fail('Selected wiring person is invalid or has been deleted.');
                        }
                    }
                ],

                'customer_photo' => [
                    'nullable',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->hasFile('customer_photo')) {
                            $file = $request->file('customer_photo');

                            // Check if file is valid
                            if (!$file->isValid()) {
                                $fail('The customer photo must be a valid file.');
                            }

                            // Check MIME type
                            if (!in_array($file->extension(), ['jpg', 'jpeg', 'png'])) {
                                $fail('The customer photo must be a file of type: jpg, jpeg, png.');
                            }

                            // Check size (max 2 MB)
                            if ($file->getSize() > 2048 * 1024) {
                                $fail('The customer photo may not be greater than 2 MB.');
                            }
                        }
                    }
                ],

                'citizenship_front' => [
                    'nullable',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->hasFile('citizenship_front')) {
                            $file = $request->file('citizenship_front');
                            if (!$file->isValid())
                                $fail('The front side of citizenship must be a valid file.');
                            if (!in_array($file->extension(), ['jpg', 'jpeg', 'png']))
                                $fail('The front side of citizenship must be a file of type: jpg, jpeg, png.');
                            if ($file->getSize() > 2048 * 1024)
                                $fail('The front side of citizenship may not be greater than 2 MB.');
                        }
                    }
                ],

                'citizenship_back' => [
                    'nullable',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->hasFile('citizenship_back')) {
                            $file = $request->file('citizenship_back');
                            if (!$file->isValid())
                                $fail('The back side of citizenship must be a valid file.');
                            if (!in_array($file->extension(), ['jpg', 'jpeg', 'png']))
                                $fail('The back side of citizenship must be a file of type: jpg, jpeg, png.');
                            if ($file->getSize() > 2048 * 1024)
                                $fail('The back side of citizenship may not be greater than 2 MB.');
                        }
                    }
                ],
                'is_disable' => 'sometimes|boolean',

                'is_active' => 'sometimes|boolean',
            ]);


            $validated = $validator->validate();
            $data = $validated;

            foreach (['customer_photo', 'citizenship_front', 'citizenship_back'] as $fileField) {
                if ($request->hasFile($fileField) && $request->file($fileField)->isValid()) {
                    $folder = match ($fileField) {
                        'customer_photo' => 'uploads/customers',
                      
                        'citizenship_front', 'citizenship_back' => 'uploads/citizenships',
                    };
                    $data[$fileField] = $this->resizeImage($request->file($fileField), $folder);
                } else {
                    $data[$fileField] = $memberEntry->$fileField;
                }
            }

            $memberEntry->update($data);

            return response()->json([
                'message' => "Member entry {$memberEntry->customer_name_en} updated successfully",
                'data' => $memberEntry
            ], 200);

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();

            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => $allErrors
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the member entry !!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    private function resizeImage($image, $folder = 'uploads/customers', $maxWidth = 300, $maxHeight = 400)
    {
        $directory = storage_path('app/public/' . $folder . '/');

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        list($width, $height) = getimagesize($image);
        $ratio = $width / $height;

        if ($maxWidth / $maxHeight > $ratio) {
            $newWidth = (int) ($maxHeight * $ratio);
            $newHeight = $maxHeight;
        } else {
            $newWidth = $maxWidth;
            $newHeight = (int) ($maxWidth / $ratio);
        }

        $ext = strtolower($image->getClientOriginalExtension());
        $src = null;

        switch ($ext) {
            case 'jpeg':
            case 'jpg':
                $src = imagecreatefromjpeg($image);
                break;
            case 'png':
                $src = imagecreatefrompng($image);
                break;
            case 'gif':
                $src = imagecreatefromgif($image);
                break;
            default:
                throw new \Exception("Unsupported image type.");
        }

        $dst = imagecreatetruecolor($newWidth, $newHeight);

        if ($ext == 'png' || $ext == 'gif') {
            imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $filename = Str::uuid() . '.' . $ext;
        $path = $folder . '/' . $filename;

        switch ($ext) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($dst, $directory . $filename);
                break;
            case 'png':
                imagepng($dst, $directory . $filename);
                break;
            case 'gif':
                imagegif($dst, $directory . $filename);
                break;
        }

        imagedestroy($src);
        imagedestroy($dst);

        return url('storage/' . $path); // return full URL
    }








    public function deleteMemberEntry(Request $request, $id)
    {
        try {
         
            $entry = MemberEntry::findOrFail($id);

           
            $latestEntry = MemberEntry::latest()->first();

            
            if ($entry->id !== $latestEntry->id) {
                return response()->json([
                    'message' => 'Only the last created member entry can be deleted !',
                ], 422);
            }

        
            $hasMeterIssue = MeterIssue::where('member_entry_id', $entry->id)
                
                ->exists();

            if ($hasMeterIssue) {
                return response()->json([
                    'message' => 'Cannot delete this member because it has an active meter issue.',
                ], 422);
            }

          
            $entry->delete();

            return response()->json([
                'message' => 'Member entry deleted successfully',
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Member entry not found or already deleted',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the member entry',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function importMemberEntry(Request $request)
    {
        

        try {

            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls',
            ]);

            $import = new MemberEntryImport;

            DB::transaction(function () use ($import, $request) {
                Excel::import($import, $request->file('file'), null, \Maatwebsite\Excel\Excel::XLSX);
            });

            return response()->json([
                'message' => 'Excel imported successfully',
                'errors' => [],
                'imported' => $import->importedCount,
            ], 200);

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $firstErrorMessage = count($failures) > 0 ? $failures[0]->errors()[0] ?? 'Excel validation failed' : 'Excel validation failed';

            return response()->json([
                'message' => $firstErrorMessage,
                'errors' => ['excel_validation' => $failures],
            ], 422);

        } catch (ValidationException $e) {
            $allErrors = $e->errors();
            $firstErrorMessage = collect($allErrors)->flatten()->first();

            return response()->json([
                'message' => $firstErrorMessage ?: 'Validation failed',
                'errors' => $allErrors
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error importing Excel !',
                'errors' => ['exception' => [$e->getMessage()]],
            ], 500);
        }
    }


//  public function importMemberEntry(Request $request)
//     {
//         try {

//             $request->validate([
//                 'file' => 'required|mimes:xlsx,csv,xls|max:10240',
//             ]);

//             $import = new MemberEntryImport();

//             Excel::import($import, $request->file('file'));

//             return response()->json([
//                 'message' => 'Member entries imported successfully',
//                 'imported_count' => $import->importedCount
//             ], 200);

//         } catch (\Throwable $e) {

//             return response()->json([
//                 'message' => 'Import failed',
//                 'error' => $e->getMessage(),
//             ], 422);
//         }
//     }

  public function getImportFieldNames()
{
    try {
        $fields = [
            'customer_name_en',
            'customer_name_np',
            'citizenship_no',
            'gender',
            'occupation_id',
            'pan_no',
            'contact_no',
            'province_id',
            'district_id',
            'municipality_id',
            'ward_no',
            'area_id',
            'house_no',
            'location_description',
            'floor',
            'wiring_person_id',
            'father_or_husband_name',
            'grandfather_or_father_in_law_name',
            'house_owner_name',
            'is_disable',
            'is_active',
        ];

        return response()->json([
            'data' => $fields,
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'status' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
}
}
