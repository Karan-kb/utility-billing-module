<?php

namespace App\Observers;

use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;

class BaseObserver
{
    protected $moduleType;

    public function __construct($moduleType)
    {
        $this->moduleType = $moduleType;
    }

    public function created($model)
    {
        $this->log('created', $model);
    }

    public function updated($model)
    {
        if ($model->wasChanged('deleted_at')) {
            return;
        }

        $ignored = ['updated_at', 'updated_by', 'deleted_by'];

        $changes = array_diff_key(
            $model->getChanges(),
            array_flip($ignored)
        );

        if (empty($changes)) {
            return;
        }

        $this->log('updated', $model);
    }

    public function deleting($model)
    {
        $this->log('deleted', $model);
    }

    protected function log($action, $model)
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => "{$action}_" . strtolower(class_basename($model)),
            'module_type' => $this->moduleType,
            'module_id' => $model->id,
            'old_data' => $action === 'created' ? null : json_encode($model->getOriginal()),
            'new_data' => $action === 'deleted' ? null : $model->toJson(),
            'ip_address' => request()->ip(),
        ]);
    }
}
