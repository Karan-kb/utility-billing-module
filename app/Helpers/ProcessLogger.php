<?php
namespace App\Helpers;

use App\Models\SystemProcessLog;

class ProcessLogger
{
    public static function log(
        string $service,
        string $action,
        array $data = [],
        string $level = 'info',
        ?string $stage = null,
        array $context = []
    ) {
        SystemProcessLog::create([
            'member_entry_id' => $context['member_entry_id'] ?? null,
            'meter_reading_entry_id' => $context['entry_id'] ?? null,
            'receipt_id' => $context['receipt_id'] ?? null,
            'service' => $service,
            'stage' => $context['stage'] ?? null,
            'action' => $action,
            'data' => $data,
            'level' => $level,
        ]);
    }
}