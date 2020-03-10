<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Sheba\Dal\TripRequestApprovalFlow\Model as TripRequestApprovalFlow;

class BusinessDepartment extends Model
{
    protected $guarded = ['id',];
    protected $table = 'business_departments';

    public function businessRoles()
    {
        return $this->hasMany(BusinessRole::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function tripRequestFlow()
    {
        return $this->hasOne(TripRequestApprovalFlow::class);
    }
}