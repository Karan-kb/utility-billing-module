<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\MeterReadingEntry;
use App\Models\NEAPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NeaLeakageReportController extends Controller
{
 public function index(Request $request): JsonResponse
{
    // if (!$request->user()->hasOrganizationPermission('view nea leakage report')) {
    //     return response()->json(['message' => 'Unauthorized'], 403);
    // }

    try {
        $validated = $request->validate([
            'month' => 'nullable|string|max:10',
            'transformer_id' => 'nullable|integer|exists:tenant.master_setups,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        // Supply query
        $supplyQuery = NEAPurchase::on('tenant')
            ->select(
                'nea_purchases.transformer_id',
                'nea_purchases.month',
                DB::raw('SUM(total_units) as supplied_units'),
                'master_setups.name_en as transformer_name_en',
                'master_setups.name_np as transformer_name_np'
            )
            ->whereNull('nea_purchases.deleted_at')
            ->join('master_setups', 'master_setups.id', '=', 'nea_purchases.transformer_id')
            ->groupBy(
                'nea_purchases.transformer_id',
                'nea_purchases.month',
                'master_setups.name_en',
                'master_setups.name_np'
            );

        if ($request->filled('month')) {
            $supplyQuery->where('nea_purchases.month', $validated['month']);
        }

        if ($request->filled('transformer_id')) {
            $supplyQuery->where('nea_purchases.transformer_id', $validated['transformer_id']);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $supplyQuery->whereBetween('date_in_ad', [
                $validated['date_from'],
                $validated['date_to']
            ]);
        }

        $supplies = $supplyQuery->get()->keyBy(function ($row) {
            return $row->transformer_id . '-' . $row->month;
        });

        // Billing query
        $billingQuery = MeterReadingEntry::on('tenant')
            ->select(
                'meter_issues.transformer_id',
                'meter_reading_entries.reading_month_in_bs as reading_month',
                DB::raw('SUM(meter_reading_entries.total_unit) as billed_units'),
                'master_setups.name_en as transformer_name_en',
                'master_setups.name_np as transformer_name_np'
            )
            ->join('meter_issues', function ($join) {
                $join->on('meter_issues.id', '=', 'meter_reading_entries.meter_issue_id');
            })
            ->join('master_setups', 'master_setups.id', '=', 'meter_issues.transformer_id')
            ->whereNull('meter_reading_entries.deleted_at')
            ->where('meter_reading_entries.entry_type', 1) // ✅ important
            ->groupBy(
                'meter_issues.transformer_id',
                'meter_reading_entries.reading_month_in_bs',
                'master_setups.name_en',
                'master_setups.name_np'
            );

        if ($request->filled('month')) {
            $billingQuery->where('meter_reading_entries.reading_month_in_bs', $validated['month']);
        }

        if ($request->filled('transformer_id')) {
            $billingQuery->where('meter_issues.transformer_id', $validated['transformer_id']);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $billingQuery->whereBetween('meter_reading_entries.reading_date_in_ad', [
                $validated['date_from'],
                $validated['date_to']
            ]);
        }

        $billings = $billingQuery->get()->keyBy(function ($row) {
            return $row->transformer_id . '-' . $row->reading_month;
        });

        // ✅ Merge supply + billing keys
        $allKeys = collect($supplies->keys())
            ->merge($billings->keys())
            ->unique();

        $report = [];

        foreach ($allKeys as $key) {

            $supply = $supplies[$key] ?? null;
            $billing = $billings[$key] ?? null;

            $supplyUnits = $supply->supplied_units ?? 0;
            $billedUnits = $billing->billed_units ?? 0;

            $transformer_id = $supply->transformer_id ?? $billing->transformer_id ?? null;
            $month = $supply->month ?? $billing->reading_month ?? null;

            $transformer_name_en = $supply->transformer_name_en ?? $billing->transformer_name_en ?? null;
            $transformer_name_np = $supply->transformer_name_np ?? $billing->transformer_name_np ?? null;

            $leakageUnits = $supplyUnits - $billedUnits;
            $leakagePercent = $supplyUnits > 0
                ? round(($leakageUnits / $supplyUnits) * 100, 2)
                : 0;

            $report[] = [
                'transformer_id' => $transformer_id,
                'transformer_name_en' => $transformer_name_en,
                'transformer_name_np' => $transformer_name_np,
                'month' => $month,
                'purchased_units' => $supplyUnits,
                'billed_units' => $billedUnits,
                'leakage_units' => $leakageUnits,
                'leakage_percent' => $leakagePercent,
            ];
        }

        return response()->json([
            'message' => 'NEA leakage report retrieved successfully',
            'data' => array_values($report)
        ], 200);

    } catch (ValidationException $e) {
        $allErrors = $e->errors();
        return response()->json([
            'message' => collect($allErrors)->flatten()->first(),
            'errors' => $allErrors
        ], 422);
    } catch (\Exception $e) {

        return response()->json([
            'message' => 'An error occurred while retrieving NEA leakage report',
            'error' => $e->getMessage(),
        ], 500);
    }
}

   public function indexWithPagination(Request $request): JsonResponse
{
    try {
        $validated = $request->validate([
            'month' => 'nullable|string|max:10',
            'transformer_id' => 'nullable|integer|exists:tenant.master_setups,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:1000'
        ]);

        $perPage = $validated['per_page'] ?? 10;

        $supplyQuery = NEAPurchase::on('tenant')
            ->select(
                'nea_purchases.transformer_id',
                'nea_purchases.month',
                DB::raw('SUM(total_units) as supplied_units'),
                'master_setups.name_en as transformer_name_en',
                'master_setups.name_np as transformer_name_np'
            )
            ->whereNull('nea_purchases.deleted_at')
            ->join('master_setups', 'master_setups.id', '=', 'nea_purchases.transformer_id')
            ->groupBy(
                'nea_purchases.transformer_id',
                'nea_purchases.month',
                'master_setups.name_en',
                'master_setups.name_np'
            );

        if ($request->filled('month')) {
            $supplyQuery->where('nea_purchases.month', $validated['month']);
        }

        if ($request->filled('transformer_id')) {
            $supplyQuery->where('nea_purchases.transformer_id', $validated['transformer_id']);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $supplyQuery->whereBetween('date_in_ad', [
                $validated['date_from'],
                $validated['date_to']
            ]);
        }

        $supplies = $supplyQuery->paginate($perPage);

        $billingQuery = MeterReadingEntry::on('tenant')
            ->select(
                'meter_issues.transformer_id',
                'meter_reading_entries.reading_month_in_bs as reading_month',
                DB::raw('SUM(meter_reading_entries.total_unit) as billed_units'),
                'master_setups.name_en as transformer_name_en',
                'master_setups.name_np as transformer_name_np'
            )
            ->join('meter_issues', function ($join) {
                $join->on('meter_issues.id', '=', 'meter_reading_entries.meter_issue_id');
            })
            ->join('master_setups', 'master_setups.id', '=', 'meter_issues.transformer_id')
            ->whereNull('meter_reading_entries.deleted_at')
            ->where('meter_reading_entries.entry_type', 1)
            ->groupBy(
                'meter_issues.transformer_id',
                'meter_reading_entries.reading_month_in_bs',
                'master_setups.name_en',
                'master_setups.name_np'
            );

        if ($request->filled('month')) {
            $billingQuery->where('meter_reading_entries.reading_month_in_bs', $validated['month']);
        }

        if ($request->filled('transformer_id')) {
            $billingQuery->where('meter_issues.transformer_id', $validated['transformer_id']);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $billingQuery->whereBetween('meter_reading_entries.reading_date_in_ad', [
                $validated['date_from'],
                $validated['date_to']
            ]);
        }

        $billings = $billingQuery->get()->keyBy(function ($row) {
            return $row->transformer_id . '-' . $row->reading_month;
        });

        $report = [];

        foreach ($supplies as $supply) {
            $key = $supply->transformer_id . '-' . $supply->month;

            $billedUnits = $billings[$key]->billed_units ?? 0;
            $supplyUnits = $supply->supplied_units ?? 0;

            $leakageUnits = $supplyUnits - $billedUnits;
            $leakagePercent = $supplyUnits > 0
                ? round(($leakageUnits / $supplyUnits) * 100, 2)
                : 0;

            $report[] = [
                'transformer_id' => $supply->transformer_id,
                'transformer_name_en' => $supply->transformer_name_en,
                'transformer_name_np' => $supply->transformer_name_np,
                'month' => $supply->month,
                'purchased_units' => $supplyUnits,
                'billed_units' => $billedUnits,
                'leakage_units' => $leakageUnits,
                'leakage_percent' => $leakagePercent,
            ];
        }

        $paginated = $supplies->toArray();
        $paginated['data'] = $report;

        return response()->json([
            'message' => 'NEA leakage report retrieved successfully',
            'data' => $paginated
        ], 200);

    } catch (ValidationException $e) {
        $allErrors = $e->errors();
        return response()->json([
            'message' => collect($allErrors)->flatten()->first(),
            'errors' => $allErrors
        ], 422);
    } catch (\Exception $e) {

        return response()->json([
            'message' => 'An error occurred while retrieving NEA leakage report',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}
