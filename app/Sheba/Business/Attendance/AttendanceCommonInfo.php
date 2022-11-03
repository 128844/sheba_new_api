<?php namespace Sheba\Business\Attendance;

use Sheba\Map\Client\BarikoiClient;
use App\Models\Business;
use Sheba\Location\Geo;
use Throwable;

class AttendanceCommonInfo
{

    private $lat;
    private $lng;

    public function setLat($lat)
    {
        $this->lat = $lat;
        return $this;
    }


    public function setLng($lng)
    {
        $this->lng = $lng;
        return $this;
    }

    public function getAddress()
    {
        try {
            return (new BarikoiClient)->getAddressFromGeo($this->getGeo())->getAddress();
        } catch (\Throwable $exception) {
            app('sentry')->captureException($exception);
            return null;
        }
    }

    public function isInWifiArea(Business $business)
    {
        return in_array($this->getIp(), $business->offices->pluck('ip')->toArray());
    }

    public function whichOffice(Business $business)
    {
        $office = $business->offices->where('ip', $this->getIp())->first();
        return $office->name;
    }

    private function getGeo()
    {
        if (!$this->lat || !$this->lng) return null;
        return (new Geo())->setLat($this->lat)->setLng($this->lng);
    }

    private function getIp()
    {
        $ip_methods = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($ip_methods as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip); //just to be safe
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return request()->ip();
    }


}
