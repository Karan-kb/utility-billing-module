<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MeterIssue;
use App\Helpers\Helper;
use App\Models\MemberEntry;
use App\Models\MeterReadingEntry;
use Carbon\Carbon;

class PresentLedgerController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Validate input
            $request->validate([
                'month' => 'required|integer|min:1|max:12',
                'type' => 'nullable|string|in:present,absent', // type can be 'present' or 'absent'
            ]);

            $month = $request->month;
            $type = $request->type ?? 'absent'; // default to 'absent' if not provided
            $currentFiscalYear = Helper::getActiveFiscalYearId();

            // Base query
            $query = MemberEntry::query();

            // Conditional whereHas / whereDoesntHave based on type
            if ($type === 'present') {
                // Members who have taken reading
                $query->whereHas('meterIssues.meterReadingEntries', function ($q) use ($currentFiscalYear, $month) {
                    $q->where('fiscal_year_id', $currentFiscalYear)
                        ->where('reading_month_in_bs', $month)
                        ->whereNull('deleted_at');
                });
            } else {
                // Members who have NOT taken reading
                $query->whereDoesntHave('meterIssues.meterReadingEntries', function ($q) use ($currentFiscalYear, $month) {
                    $q->where('fiscal_year_id', $currentFiscalYear)
                        ->where('reading_month_in_bs', $month)
                        ->whereNull('deleted_at');
                });
            }

            // Eager load meters and readings
            $members = $query->with([
                'meterIssues' => function ($q) use ($currentFiscalYear, $month, $type) {
                    $q->select('id', 'member_entry_id', 'meter_no', 'issue_date_ad')
                        ->with([
                            'meterReadingEntries' => function ($sub) use ($currentFiscalYear, $month, $type) {
                                $sub->where('fiscal_year_id', $currentFiscalYear)
                                    ->whereNull('deleted_at');

                                if ($type === 'absent') {
                                    // Exclude current month readings for absent members
                                    $sub->where('reading_month_in_bs', '!=', $month);
                                } else {
                                    // Include only current month readings for present members
                                    $sub->where('reading_month_in_bs', $month);
                                }

                                $sub->orderByDesc('reading_date_in_ad')
                                    ->select('id', 'meter_issue_id', 'reading_date_in_ad', 'reading_date_in_bs', 'fine_amount', 'total_charge');
                            }
                        ]);
                }
            ])
                ->paginate(10);

            // Map to API response
            $data = $members->getCollection()->map(function ($member) use ($type) {
                $meterIssue = $member->meterIssues->first();
                $latestReading = $meterIssue?->meterReadingEntries->first();

                return [
                    'member_id' => $member->id,
                    'member_no' => $member->member_no,
                    'customer_name_en' => $member->customer_name_en,
                    'customer_name_np' => $member->customer_name_np,
                    'meter_no' => $meterIssue?->meter_no,
                    'fine_amount' => $latestReading?->fine_amount,
                    'total_charge' => $latestReading?->total_charge,
                    'last_meter_reading_date_in_ad' => $latestReading
                        ? Carbon::parse($latestReading->reading_date_in_ad)->toDateString()
                        : null,
                    'last_meter_reading_date_in_bs' => $latestReading
                        ? Carbon::parse($latestReading->reading_date_in_bs)->toDateString()
                        : null,
                    'meter_issue_date_ad' => $meterIssue?->issue_date_ad,
                ];
            });

            return response()->json([
                'message' => ucfirst($type) . ' Report fetched successfully!',
                'data' => $data,
                'pagination' => [
                    'current_page' => $members->currentPage(),
                    'last_page' => $members->lastPage(),
                    'per_page' => $members->perPage(),
                    'total' => $members->total(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred!',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
