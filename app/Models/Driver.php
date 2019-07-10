<?php namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $guarded = ['id'];


    public function profile()
    {
        return $this->hasOne(Profile::class, 'driver_id');
    }

    public function vehicle()
    {
        return $this->hasOne(Vehicle::class, 'current_driver_id');
    }

    public function businessTrip()
    {
        return $this->hasOne(BusinessTrip::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }


    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function hiredBy()
    {
        return $this->hasMany(HiredDriver::class)->where('end', null)->orWhere('end', '>=', Carbon::now());
    }

    public function scopeWhoseOwnerIsBusiness($query, $business_id = null)
    {
        $query = $query->where('owner_type', "App\\Models\\Business");
        if (!$business_id) $query = $query->where('owner_id', (int)$business_id);
        return $query;
    }

    public function scopeWhoseOwnerIsNotBusiness($query)
    {
        return $query->where('owner_type', '<>', "App\\Models\\Business");
    }


}