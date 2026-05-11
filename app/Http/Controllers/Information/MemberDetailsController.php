<?php

namespace App\Http\Controllers\Information;

use App\Models\AdvancePayment;
use App\Models\MemberEntry;
use App\Models\MasterSetup;
use App\Models\MeterInsurance;
use App\Models\MeterDepositTransaction;
use App\Models\MeterIssue;
use App\Models\NameTransferEntry;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class MemberDetailsController extends Controller
{
    public function show(Request $request)
    {

        try {
            $id = $request->input('id');

            if (!$id) {
                return response()->json([
                    'message' => 'Member Number is required'
                ], 422);
            }

            $customer_id = MemberEntry::where('id', $id)->value('id');
            $meterIssueId = MeterIssue::where('member_entry_id', $customer_id)->value('id');

            $member = MemberEntry::with([
                'meterIssues.transformer',
                'meterIssues.demandPhase',
                'meterIssues.demandCapacity',
                'meterIssues.purposeType',
                'meterIssues.readingArea',
            ])->where('id', $customer_id)->first();

            if (!$member) {
                return response()->json([
                    'message' => 'Member not found',
                    'data' => null
                ], 404);
            }

            $provinces_en = json_decode(file_get_contents(base_path('vendor/sagautam5/local-states-nepal/dataset/alldataset/en.json')));
            $provinces_np = json_decode(file_get_contents(base_path('vendor/sagautam5/local-states-nepal/dataset/alldataset/np.json')));

            $province_en = collect($provinces_en)->firstWhere('id', $member->province_id);
            $province_np = collect($provinces_np)->firstWhere('id', $member->province_id);

            $district_en = $province_en ? collect($province_en->districts ?? [])->firstWhere('id', $member->district_id) : null;
            $district_np = $province_np ? collect($province_np->districts ?? [])->firstWhere('id', $member->district_id) : null;

            $municipality_en = $district_en ? collect($district_en->municipalities ?? [])->firstWhere('id', $member->municipality_id) : null;
            $municipality_np = $district_np ? collect($district_np->municipalities ?? [])->firstWhere('id', $member->municipality_id) : null;

            $area = MasterSetup::withoutTrashed()->where('master_setup_type_id', 6)->where('id', $member->area_id)->first();
            $occupation = MasterSetup::withoutTrashed()->where('master_setup_type_id', 7)->where('id', $member->occupation_id)->first();

            $toKeyValue = fn($array) => array_map(
                fn($key, $value) => ['key' => $key, 'value' => $value],
                array_keys($array),
                array_values($array)
            );

            $memberDataArray = $member->toArray();
            $memberDataArray['province'] = $province_en->name ?? null;
            $memberDataArray['province_np'] = $province_np->name ?? null;
            $memberDataArray['district'] = $district_en->name ?? null;
            $memberDataArray['district_np'] = $district_np->name ?? null;
            $memberDataArray['municipality'] = $municipality_en->name ?? null;
            $memberDataArray['municipality_np'] = $municipality_np->name ?? null;
            $memberDataArray['area'] = $area?->name_en ?? null;
            $memberDataArray['area_np'] = $area?->name_np ?? null;
            $memberDataArray['occupation'] = $occupation?->name_en ?? null;
            $memberDataArray['occupation_np'] = $occupation?->name_np ?? null;

            unset(
                $memberDataArray['province_id'],
                $memberDataArray['district_id'],
                $memberDataArray['municipality_id'],
                $memberDataArray['area_id'],
                $memberDataArray['occupation_id']
            );

            $memberData = $toKeyValue(array_filter($memberDataArray, fn($v) => !is_null($v)));

            // Meter issues
            $meterIssues = $member->meterIssues()->orderByDesc('created_at')->get()->map(function ($item) use ($toKeyValue) {
                $itemArray = $item->toArray();

                $itemArray['demand_phase'] = $item->demandPhase?->name_en ?? null;
                $itemArray['demand_capacity'] = $item->demandCapacity?->name_en ?? null;
                $itemArray['purpose_type'] = $item->purposeType?->name_en ?? null;
                $itemArray['transformer'] = $item->transformer?->name_en ?? null;
                $itemArray['reading_area'] = $item->readingArea?->name_en ?? null;

                return $toKeyValue(array_filter($itemArray, fn($v) => !is_null($v)));
            });

            $nameTransfers = NameTransferEntry::withoutTrashed()->where('previous_member_entry_id', $member->customer_id)
                ->orWhere('new_member_entry_id', $member->customer_id)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($item) => $toKeyValue(array_filter($item->toArray(), fn($v) => !is_null($v))));

            $meterInsurance = MeterInsurance::withoutTrashed()->where('meter_issue_id', $meterIssueId)
                ->orderByDesc('date_in_ad')
                ->get()
                ->map(fn($item) => $toKeyValue(array_filter($item->toArray(), fn($v) => !is_null($v))));

            $advancePayments = AdvancePayment::withoutTrashed()
            ->where('type', 0)
            ->where('meter_issue_id', $meterIssueId)
                ->orderByDesc('date_in_ad')
                ->get()
                ->map(fn($item) => $toKeyValue(array_filter($item->toArray(), fn($v) => !is_null($v))));

            $responseData = ['member' => $memberData];

            if ($meterIssues->isNotEmpty())
                $responseData['meter_issues'] = $meterIssues;
            if ($nameTransfers->isNotEmpty())
                $responseData['name_transfer_history'] = $nameTransfers;
            if ($meterInsurance->isNotEmpty())
                $responseData['meter_insurance'] = $meterInsurance;
            if ($advancePayments->isNotEmpty())
                $responseData['advance_payments'] = $advancePayments;

            return response()->json([
                'message' => 'Member details retrieved successfully',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving member details',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function meterDepositDetails(Request $request)
    {
        try {
            $member_no = $request->input('member_no');

            if (!$member_no) {
                return response()->json([
                    'message' => 'Member Number is required'
                ], 422);
            }

            $customer_id = MemberEntry::where('member_no', $member_no)->value('id');

            $meterDepositTransactions = MeterDepositTransaction::with('meterIssue')
                ->whereHas('meterIssue', function ($query) use ($customer_id) {
                    $query->where('member_entry_id', $customer_id);
                })
                ->orderByDesc('date_in_ad')
                ->get()
                ->map(fn($item) => $item->toArray());

            return response()->json([
                'message' => 'Meter Deposit Transactions retrieved successfully',
                'data' => $meterDepositTransactions
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Member not found',
                'error' => $e->getMessage()
            ], 404);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database query error',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving meter deposit transactions',
                'error' => $e->getMessage()
            ], 500);
        }

    }
}
