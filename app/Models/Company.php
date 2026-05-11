<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    protected $connection = 'mysql';

    use HasFactory, SoftDeletes;

    protected $table = 'companies';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'licence_issue_date',
        'license_expiry_date',
        'reg_number',
        'pan_number',
        'full_address',
        'email_address',
        'website',
        'province_id',
        'district_id',
        'municipality_id',
        'ward_no',
        'contact_number',
        'contact_person',
        'contact_person_position',
        'license_number',
        'activation_key',
        'url_link',
        'user_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'name' => 'string',
        'reg_number' => 'string',
        'pan_number' => 'string',
        'full_address' => 'string',
        'email_address' => 'string',
        'website' => 'string',
        'contact_number' => 'string',
        'contact_person' => 'string',
        'contact_person_position' => 'string',
        'license_number' => 'string',
        'activation_key' => 'string',
        'url_link' => 'string',
        'licence_issue_date' => 'date',
        'license_expiry_date' => 'date',
        'province_id' => 'integer',
        'district_id' => 'integer',
        'municipality_id' => 'integer',
        'ward_no' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',

    ];

    /**
     * Get the admin user associated with the company.
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function roles()
    {
        return $this->hasMany(\App\Models\Role::class, 'company_id', 'id');
    }

}