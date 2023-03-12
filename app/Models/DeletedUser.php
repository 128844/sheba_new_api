<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeletedUser extends Model
{
    protected $guarded = ['id'];

    public function profile()
    {
        return $this->hasOne('Profile');
    }
}