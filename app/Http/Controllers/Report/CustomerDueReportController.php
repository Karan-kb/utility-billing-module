<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MeterIssue;
use App\Models\MeterReadingEntry;
use App\Helpers\Helper;
use Carbon\Carbon;
use App\Services\CustomerChargeService;
use App\Services\CustomerDueService;

class CustomerDueReportController extends Controller
{
    protected $chargeService;
    protected $dueService;

    public function __construct(
        CustomerChargeService $chargeService,
        CustomerDueService $dueService
    ) {
        $this->chargeService = $chargeService;
        $this->dueService = $dueService;
    }

    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'member_no' => 'nullable|integer',
                'fiscal_year_id' => 'nullable|integer',
                'month' => 'nullable|string',
            ]);

            $meterQuery = MeterReadingEntry::with('meterIssue.memberEntry:id,member_no,customer_name_en')
                ->whereIn('status', [0, 1, 2]);

            // Fiscal Year
            if (!empty($validated['fiscal_year_id'])) {
                $meterQuery->where('fiscal_year_id', $validated['fiscal_year_id']);
            }

            // Month
            if (!empty($validated['month'])) {
                if (empty($validated['fiscal_year_id'])) {
                    $fiscalYearId = Helper::getActiveFiscalYearId();
                    if ($fiscalYearId) {
                        $meterQuery->where('fiscal_year_id', $fiscalYearId);
                    }
                }
                $meterQuery->where('reading_month_in_bs', $validated['month']);
            }

            // Member
            if (!empty($validated['member_no'])) {
                $meterQuery->whereHas('meterIssue.memberEntry', function ($q) use ($validated) {
                    $q->where('member_no', $validated['member_no']);
                });
            }

            $meterReadings = $meterQuery->get();

            if ($meterReadings->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No data found'
                ]);
            }

            // Group by member
            $memberMeter = $meterReadings->groupBy(function ($row) {
                return $row->meterIssue->memberEntry->id;
            });

            $data = $memberMeter->map(function ($readings, $memberId) {

                $memberEntry = $readings->first()->meterIssue->memberEntry;
                $meterIssueId = $readings->first()->meter_issue_id;

                // Get all charges (includes type 1,2,5)
                  $charges = $this->chargeService->getTotalCharges($memberId, $meterIssueId);

                    $unitAmounts    = round($charges[6] ?? 0, 2);
                    $demandCharge   = round($charges[1] ?? 0, 2);
                    $serviceCharge  = round($charges[2] ?? 0, 2);
                    $fineCharge     = round($charges[3] ?? 0, 2);
                    $subsidyCharge  = round($charges[4] ?? 0, 2);
                    $otherCharge    = round($charges[5] ?? 0, 2);

                    $netDue = round($this->dueService->getPreviousTotalDue($memberId, $meterIssueId), 2);

                if ($netDue <= 0) {
                    return null;
                }

                // Due Days
                $lastUnpaidReading = $readings
                    ->where('status', '!=', 2)
                    ->sortByDesc('reading_date_in_ad')
                    ->first();

                $dueDate = $lastUnpaidReading?->reading_date_in_ad;

                $dueDays = $dueDate
                    ? max(0, (int) ceil(Carbon::parse($dueDate)->diffInDays(now(), false)))
                    : 0;
                    
                return [
                    'member_no'      => $memberEntry->member_no,
                    'member_name'    => $memberEntry->customer_name_en ?? 'N/A',
                    'meter_issue_id' => MeterIssue::where('id', $meterIssueId)->value('meter_no'),
                    'unit_amounts'   => $unitAmounts,
                    'demand_charge'  => $demandCharge,
                    'service_charge' => $serviceCharge,
                    'fine_charge'    => $fineCharge,
                    'subsidy_charge' => $subsidyCharge,
                    'other_charge'   => $otherCharge,
                    'net_due'        => $netDue,
                    'due_days'       => $dueDays,
                ];
            })
            ->filter()
            ->values();
                                     $totals = [
                                    'total_unit_amounts'   => round($data->sum('unit_amounts'), 2),
                                    'total_demand_charge'  => round($data->sum('demand_charge'), 2),
                                    'total_service_charge' => round($data->sum('service_charge'), 2),
                                    'total_fine_charge'    => round($data->sum('fine_charge'), 2),
                                    'total_subsidy_charge' => round($data->sum('subsidy_charge'), 2),
                                    'total_other_charge'   => round($data->sum('other_charge'), 2),
                                    'total_net_due'        => round($data->sum('net_due'), 2),
                                ];

                        return response()->json([
                            'success' => true,
                            'data' => $data,
                            'totals' => $totals,
                        ]);


        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
}