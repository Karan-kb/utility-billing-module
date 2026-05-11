<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    public $incrementing = false;   // <--- allow manual IDs
    protected $keyType = 'integer';

    protected $fillable = ['id', 'name_en', 'name_np'];

    public function districts()
    {
        return $this->hasMany(District::class);
    }
}