<?php

namespace Sheba\Business\LiveTracking;

use App\Jobs\Job;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Sheba\Dal\TrackingLocation\TrackingLocation;
use Sheba\Location\Geo;
use Sheba\Map\Client\BarikoiClient;
use Throwable;

class LiveTrackingInsertJob extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    private $locations;
    private $business;
    private $businessMember;

    public function __construct($locations, $business, $business_member)
    {
        $this->locations = $locations;
        $this->business = $business;
        $this->businessMember = $business_member;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->attempts() > 1) return;

        $data = [];
        foreach ($this->locations as $location) {
            $geo = $this->getGeo($location);
            $date_time = $this->timeFormat($location['timestamp']);
            $data[] = [
                'business_id' => $this->business,
                'business_member_id' => $this->businessMember,
                'location' => $geo ? json_encode([
                    'lat' => $geo->getLat(),
                    'lng' => $geo->getLng(),
                    'address' => $this->getAddress($geo)
                ]) : null,
                'log' => $location['log'],
                'date' => $date_time->toDateString(),
                'time' => $date_time->toTimeString(),
                'created_at' => $date_time->toDateTimeString()
            ];
        }

        TrackingLocation::insert($data);
    }


    /**
     * @return string
     */
    private function getAddress($geo)
    {
        try {
            return (new BarikoiClient)->getAddressFromGeo($geo)->getAddress();
        } catch (Throwable $exception) {
            return "";
        }
    }

    /**
     * @param $location
     * @return Geo|null
     */
    private function getGeo($location)
    {
        if ($this->isLatAvailable($location) && $this->isLngAvailable($location)) {
            $geo = new Geo();
            return $geo->setLat($location['lat'])->setLng($location['lng']);
        }
        return null;
    }

    /**
     * @param $location
     * @return bool
     */
    private function isLatAvailable($location)
    {
        if (isset($location['lat']) && !$this->isNull($location['lat'])) return true;
        return false;
    }

    /**
     * @param $location
     * @return bool
     */
    private function isLngAvailable($location)
    {
        if (isset($location['lng']) && !$this->isNull($location['lng'])) return true;
        return false;
    }

    /**
     * @param $data
     * @return bool
     */
    private function isNull($data)
    {
        if ($data == " ") return true;
        if ($data == "") return true;
        if ($data == 'null') return true;
        if ($data == null) return true;
        return false;
    }

    /**
     * @param $timestamp
     * @return Carbon
     */
    private function timeFormat($timestamp)
    {
        $seconds = $timestamp / 1000;
        return Carbon::createFromTimestamp($seconds);
    }
}
