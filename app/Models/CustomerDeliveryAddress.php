<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Sheba\Location\Geo;

class CustomerDeliveryAddress extends Model
{
    use SoftDeletes;

    protected $table = 'customer_delivery_addresses';
    protected $fillable = ['name', 'mobile', 'address', 'created_by', 'created_by_name'];
    protected $dates = ['deleted_at'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function getGeoAttribute()
    {
        return $this->geo_informations ? json_decode($this->geo_informations) : null;
    }

    public function getGeo()
    {
        $geo = $this->getGeoAttribute();
        return new Geo($geo->lat, $geo->lng);
    }
}
