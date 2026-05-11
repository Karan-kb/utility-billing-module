<?php
namespace App\Services;

use App\Models\MeterIssue;
use App\Models\MeterReadingEntry;
use App\Models\VoucherSummaryDetail;
use App\Models\AccountHead;
use Illuminate\Support\Facades\DB;
use App\Services\CustomerDueService;
use App\Helpers\TenantRuntimeHelper;
use App\Models\ActivityLog;

class DashboardService
{
    protected $dueService;

    public function __construct(CustomerDueService $dueService)
    {
        $this->dueService = $dueService;
    }

  
    
public function getSummary(): array
{
    $data = [
        'active_consumers'   => $this->getActiveConsumers(),
        'total_consumption'  => $this->getTotalConsumption(),
        'billing_amount'     => $this->getBillingAmount(),
        'pending_payments'   => $this->getPendingPayments(),
         'today_billing_amount' => $this->getTodayBillingAmount(), 
           'recent_activities'  => $this->getRecentActivities(), 
    ];

    if (TenantRuntimeHelper::isRuntimeBidut()) {
        $data['purpose_distribution'] = $this->getPurposeDistribution();
    }

    return $data;
}


   
    protected function getActiveConsumers(): int
    {
        return MeterIssue::where('is_active', 1)->count();
    }


    protected function getTotalConsumption(): float
    {
        return (float) MeterReadingEntry::sum('total_unit');
    }


    protected function getBillingAmount(): float
    {
        $bankHeadIds = AccountHead::where('account_group_id', 10)->pluck('id');

        $accountHeadIds = $bankHeadIds->push(1); // include cash

        $rows = VoucherSummaryDetail::query()
            ->select(
                'account_head_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit')
            )
            ->join('voucher_summaries as vs', 'voucher_summary_details.voucher_summary_id', '=', 'vs.id')
            ->where('vs.status', '!=', 3)
            ->whereNotIn('vs.reference_type', [10, 12, 13, 14, 15])
            ->whereIn('account_head_id', $accountHeadIds)
            ->groupBy('account_head_id')
            ->get();

        return $rows->reduce(function ($carry, $row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;

            return $carry + abs($debit - $credit);
        }, 0);
    }


   
    protected function getPendingPayments(): float
    {
        $meterReadings = MeterReadingEntry::with('meterIssue.memberEntry:id')
            ->whereIn('status', [0, 1, 2])
            ->get()
            ->groupBy(fn($row) => $row->meterIssue->memberEntry->id);

        $total = 0;

        foreach ($meterReadings as $memberId => $readings) {
            $meterIssueId = $readings->first()->meter_issue_id;

            $due = $this->dueService
                ->getPreviousTotalDue($memberId, $meterIssueId);

            if ($due > 0) {
                $total += $due;
            }
        }

        return round($total, 2);
    }
    protected function getPurposeDistribution(): array
{
    $total = MeterIssue::where('is_active', 1)->count();

    if ($total == 0) {
        return [];
    }

    $rows = MeterIssue::query()
        ->select(
            'meter_issues.purpose_id',
            'ms.name_en',
            'ms.name_np',
            DB::raw('COUNT(*) as total')
        )
        ->leftJoin('master_setups as ms', function ($join) {
            $join->on('meter_issues.purpose_id', '=', 'ms.id')
                 ->whereNull('ms.deleted_at'); 
        })
        ->where('meter_issues.is_active', 1)
        ->groupBy('meter_issues.purpose_id', 'ms.name_en', 'ms.name_np')
        ->get();

    return $rows->map(function ($row) use ($total) {
        return [
            // 'purpose_id' => $row->purpose_id,
            'name_en'    => $row->name_en,
            // 'name_np'    => $row->name_np,
            // 'count'      => (int) $row->total,
            'percentage' => round(($row->total / $total) * 100, 2),
        ];
    })->values()->toArray();
}

protected function getTodayBillingAmount(): float
{
    $today = now()->toDateString();

    $bankHeadIds = AccountHead::where('account_group_id', 10)
        ->pluck('id');

    $accountHeadIds = $bankHeadIds->push(1); 

    $rows = VoucherSummaryDetail::query()
        ->select(
            'account_head_id',
            DB::raw('SUM(debit) as total_debit'),
            DB::raw('SUM(credit) as total_credit')
        )
        ->join('voucher_summaries as vs', 'voucher_summary_details.voucher_summary_id', '=', 'vs.id')
        ->where('vs.status', '!=', 3)
        ->whereNotIn('vs.reference_type', [10, 12, 13, 14, 15])
        ->whereDate('vs.date', $today) 
        ->whereIn('account_head_id', $accountHeadIds)
        ->groupBy('account_head_id')
        ->get();

    return $rows->reduce(function ($carry, $row) {
        $debit = (float) $row->total_debit;
        $credit = (float) $row->total_credit;

        return $carry + abs($debit - $credit);
    }, 0);
}
protected function getRecentActivities(): array
{
    return ActivityLog::query()
        ->with('user:id,name') 
        ->whereIn('module_type', [1,2,3,4,6,8,9,11,12,14,15])
        ->latest()
        ->limit(5)
        ->get()
        ->map(function ($log) {
            return [
                'id'           => $log->id,
                'user_name'    => $log->user?->name, 
                'action'       => $log->action,
                //'module_type'  => $log->module_type,
                //'module_id'    => $log->module_id,
                'created_at'   => $log->created_at,
            ];
        })
        ->toArray();
}
}