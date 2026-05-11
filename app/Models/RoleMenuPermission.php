<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleMenuPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'menu_id',
        'has_view_access',
        'has_create_access',
        'has_update_access',
        'has_delete_access',
    ];


    public function role() {
        return $this->belongsTo(Role::class);
    }

    public function menu() {
        return $this->belongsTo(Menu::class);
    }
}
