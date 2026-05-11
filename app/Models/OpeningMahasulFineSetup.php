<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpeningMahasulFineSetup extends Model
{
        protected $connection = 'tenant';

    protected $table = 'opening_mahasul_fine_setups';

    protected $fillable = ['is_fine_applied'];
}