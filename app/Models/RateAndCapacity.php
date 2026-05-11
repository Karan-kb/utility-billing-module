<?php

namespace App\Models;
use App\Models\TenantModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RateAndCapacity extends BaseModel
{
    use SoftDeletes;
    protected $connection = 'tenant';

    use HasFactory;

    protected $table = 'rate_and_capacities';


    protected static $useFiscalYear = false;

    protected $fillable = [
        'unit_from',
        'unit_to',
        'phase_id',
        'capacity_id',
        'tariff_setup_id',
        'purpose_id',
        'rate_per_unit',
        'minimum_demand',
        'subsidy_charge',
        'service_charge',
        'other_charge',
        'is_lumpsum',
    ];

    protected $casts = [
        'unit_from' => 'integer',
        'unit_to' => 'integer',
        'phase_id' => 'integer',
        'capacity_id' => 'integer',
        'tariff_setup_id ' => 'integer',
        'purpose_id' => 'integer',
        'rate_per_unit' => 'decimal:2',
        'minimum_demand' => 'decimal:2',
        'subsidy_charge' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'other_charge' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function phaseRecord()
    {
        return $this->belongsTo(MasterSetup::class, 'phase');
    }

    public function capacityRecord()
    {
        return $this->belongsTo(MasterSetup::class, 'capacity');
    }

    public function purposeRecord()
    {
        return $this->belongsTo(MasterSetup::class, 'purpose');
    }
}
