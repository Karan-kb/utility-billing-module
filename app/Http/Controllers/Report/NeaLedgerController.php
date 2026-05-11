<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Helpers\LedgerHelper;

class NeaLedgerController extends Controller
{
    /**
     * Get NEA Ledger
     */
    public function index(Request $request)
    {
        try {
            $transformerId = $request->get('transformer_id');

            $data = LedgerHelper::getNeaLedger($transformerId);

            return response()->json([
                'status'  => true,
                'message' => 'NEA Ledger fetched successfully',
                'data'    => $data
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'status'  => false,
                'message' => 'Failed to fetch NEA Ledger',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}