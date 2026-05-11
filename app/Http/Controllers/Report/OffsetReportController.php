<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\MemberEntry;
use Doctrine\DBAL\Query;
use App\Models\MeterReadingEntry;
use Doctrine\DBAL\Query\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OffsetReportController extends Controller
{
    public function index(Request $request)
    {
        try {

            $request->validate([
                'month' => 'required|date_format:Y-m-d',
            ]);

            $carbonDate = Carbon::createFromFormat('Y-m-d', $request->date);

            $members = MemberEntry::whereHas('meterIssues.meterReadingEntries', function ($q) use ($carbonDate) {
                $q->whereBetween('reading_date_in_ad', [
                    $carbonDate->copy()->startOfMonth()->toDateString(),
                    $carbonDate->copy()->endOfMonth()->toDateString(),
                ]);
            })
                ->distinct()
                ->get();

            return response()->json([
                'message' => 'Offset Report fetched successfully.',
                'data' => $members
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An unexpected error occurred !',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
