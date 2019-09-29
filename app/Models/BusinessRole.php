<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BusinessRole extends Model
{
    protected $guarded = ['id',];
    protected $table = 'business_roles';

    public function businessDepartment()
    {
        return $this->belongsTo(BusinessDepartment::class);
    }
}