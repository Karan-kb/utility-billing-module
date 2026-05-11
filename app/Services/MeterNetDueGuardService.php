<?php

namespace App\Services;

use App\Services\FindTotalDueAmountServiceAndFindAdvancePayment;
use App\Models\MeterReadingEntry;

class MeterNetDueGuardService
{
    protected FindTotalDueAmountServiceAndFindAdvancePayment $dueService;

    public function __construct(FindTotalDueAmountServiceAndFindAdvancePayment $dueService)
    {
        $this->dueService = $dueService;
    }

    /**
     * Returns net due amount (total due - advance)
     */
    public function getNetDue(MeterReadingEntry $entry)
    {
        $totalDue = $this->dueService->getTotalDueAmount($entry->meter_issue_id);
        $advance = $this->dueService->getTotalAdvancePayment($entry->meter_issue_id);

        return max(0, $totalDue - $advance);
    }

    /**
     * Returns true if fines/blacklist should stop
     */
    public function shouldStop(MeterReadingEntry $entry): bool
    {
        return $this->getNetDue($entry) <= 0;
    }
}
