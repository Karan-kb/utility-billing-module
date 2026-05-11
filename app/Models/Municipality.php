<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{
    public $incrementing = false;
    protected $keyType = 'integer';

    protected $fillable = ['id', 'district_id', 'name_en', 'name_np'];

    public function district()
    {
        return $this->belongsTo(District::class);
    }
}
