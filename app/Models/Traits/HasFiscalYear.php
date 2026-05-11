<?php

namespace App\Models\Traits;

use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Model;

trait HasFiscalYear
{
    public static function bootHasFiscalYear()
    {
        static::creating(function (Model $model) {

            if (method_exists($model, 'shouldUseFiscalYear') && !$model->shouldUseFiscalYear()) {
                return;
            }

            // If already set, do nothing
            if (!is_null($model->fiscal_year_id ?? null)) {
                return;
            }

            // Use same DB connection as the model (tenant-safe)
            $connection = $model->getConnection()->getName();

            $fiscalYearId = FiscalYear::on($connection)
                ->where('status', 1)
                ->value('id');

            if ($fiscalYearId) {
                // No fillable required
                $model->setAttribute('fiscal_year_id', $fiscalYearId);
            }
        });
    }
}
