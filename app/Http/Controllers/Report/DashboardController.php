<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;


use Doctrine\DBAL\Query\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

use Illuminate\Http\Request;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }
    public function dashboardSummary(DashboardService $dashboardService)
{
    try {
        return response()->json([
            'success' => true,
            'data' => $dashboardService->getSummary()
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
