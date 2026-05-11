<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Imports\OpeningMahasulImport;
use App\Services\CustomerTransactionService;
use App\Services\MeterIssueResolverService; // ← new service
use Maatwebsite\Excel\Facades\Excel;

class OpeningMahasulImportController extends Controller
{
    public function upload(
        Request $request,
        CustomerTransactionService $CustomerTransactionService,
        MeterIssueResolverService $meterIssueResolver // ← new service
    )
    {
       $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'date_in_bs' => 'nullable|string',
        ]);

        try {
           $import = new OpeningMahasulImport(
                $CustomerTransactionService,
                $meterIssueResolver, // ← pass new service
                false,
                $request->date_in_bs
            );

            Excel::import($import, $request->file('file'));

            return response()->json([
                'status' => 'success',
                'message' => 'Meter readings imported successfully.',
                'skipped' => $import->skippedMessages
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    public function getOpeningMahasulImportFieldNames()
    {
        try {
            $fields = [
                'member_no',        // ← changed from meter_issue_id
                'unit_amount',
                'fine_amount',
                'demand_charge',
                'subsidy_charge',
                'service_charge',
                'other_charge',
                'total_charge',
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