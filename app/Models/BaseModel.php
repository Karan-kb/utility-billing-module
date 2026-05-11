<?php

namespace App\Models;

use App\Models\Traits\HasFiscalYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\Blameable;

class BaseModel extends Model
{
    use Blameable, HasFiscalYear;


    protected static $useFiscalYear = true;

    public static function shouldUseFiscalYear(): bool
    {
        return static::$useFiscalYear ?? true;
    }
}
