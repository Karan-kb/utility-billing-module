<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;

use App\Helpers\LedgerHelper;
use Illuminate\Http\Request;

class MemberLedgerController extends Controller
{
    public function showLedger(Request $request)
    {
        $identifier = $request->query('search');   

        if (empty($identifier)) {
            return response()->json(['message' => 'Search parameter is required (member_no or customer name)'], 422);
        }

        $result = LedgerHelper::getMemberLedger($identifier);

        return response()->json($result);
    }
}