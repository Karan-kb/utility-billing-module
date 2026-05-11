<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    public $incrementing = false;
    protected $keyType = 'integer';

    protected $fillable = ['id', 'province_id', 'name_en', 'name_np'];

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function municipalities()
    {
        return $this->hasMany(Municipality::class);
    }
}
