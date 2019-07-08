<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $guarded = ['id'];

    public function business()
    {
        return $this->morphTo();
    }

    public function basicInformation()
    {
        return $this->hasOne(VehicleBasicInformation::class);
    }

    public function basicInformations()
    {
        return $this->hasOne(VehicleBasicInformation::class);
    }

    public function registrationInformations()
    {
        return $this->hasOne(VehicleRegistrationInformation::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'current_driver_id');
    }

    public function businessDepartment()
    {
        return $this->belongsTo(BusinessDepartment::class, 'business_department_id');
    }

    public function businessTrip()
    {
        return $this->hasOne(BusinessTrip::class);
    }

    public function inspections()
    {
        return $this->hasMany(Inspection::class);
    }

    public function fuelLogs()
    {
        return $this->hasMany(FuelLog::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function owner()
    {
        return $this->morphTo();
    }

}
