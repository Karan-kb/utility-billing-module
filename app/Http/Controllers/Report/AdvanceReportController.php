<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AdvanceReportService;
use App\Models\MemberEntry;

class AdvanceReportController extends Controller
{
    protected $service;

    public function __construct(AdvanceReportService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'member_no' => 'nullable|integer',
            ]);

            $query = $this->service->getAllAdvanceGrouped();

            // Optional filter by member_no
            if (!empty($validated['member_no'])) {
                $query->whereHas('memberEntry', function ($q) use ($validated) {
                    $q->where('member_no', $validated['member_no']);
                });
            }

            $results = $query->get();

            if ($results->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No advance found'
                ]);
            }

            $data = $results->map(function ($row) {

                $advance = $this->service->calculateAdvance(
                    $row->total_cr,
                    $row->total_dr
                );

                if ($advance <= 0) {
                    return null;
                }

                $member = MemberEntry::select('member_no', 'customer_name_en')
                    ->find($row->member_entry_id);

                return [
                    'member_no'      => $member->member_no ?? 'N/A',
                    'customer_name_en'    => $member->customer_name_en ?? 'N/A',
                    'amount' => $advance,
                ];
            })
            ->filter()
            ->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'totals' => [
                    'total_advance_amount' => round($data->sum('advance_amount'), 2),
                ]
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