<?php
namespace App\Http\Controllers\Import;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Imports\OpeningAdvanceImport;
use App\Services\CustomerTransactionService;
use App\Services\MemberResolverService;
use App\Services\MeterIssueResolverService;
use Maatwebsite\Excel\Facades\Excel;

class OpeningAdvanceImportController extends Controller
{
    public function upload(
    Request $request,
    CustomerTransactionService $customerTransactionService,
    MeterIssueResolverService $meterIssueResolver 
)
{
    $request->validate([
        'file' => 'required|file|mimes:xlsx,xls,csv',
        'date_in_bs' => 'nullable|date'
    ]);

    try {
        $import = new OpeningAdvanceImport(
            $customerTransactionService,
            $meterIssueResolver, 
            $request->date_in_bs
        );

        Excel::import($import, $request->file('file'));

        return response()->json([
            'status' => 'success',
            'message' => 'Opening advance payments imported successfully.',
            'skipped' => $import->skippedMessages
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 400);
    }
}
    public function getOpeningAdvanceImportFieldNames()
{
    try {
        $fields = [
            'member_no',
            'amount',
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