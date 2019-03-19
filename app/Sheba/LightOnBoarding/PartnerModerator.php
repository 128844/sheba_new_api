<?php namespace App\Sheba\LightOnBoarding;

use App\Exceptions\InvalidModeratorException;
use App\Exceptions\ModeratorDistanceExceedException;
use App\Exceptions\NotFoundException;
use App\Models\Affiliate;
use App\Models\Partner;
use DB;

class PartnerModerator
{
    private $affiliate, $partner, $moderator;
    private $affiliationRewards;
    private $moderatorRole;
    private $distanceThreshold = 100;

    public function __construct($moderatorRole)
    {
        $this->moderatorRole = $moderatorRole;
        $this->affiliationRewards = new AffiliationRewards();
        $this->distanceThreshold = constants('MODERATOR_DISTANCE_THRESHOLD');
    }

    public function getPartner()
    {
        return $this->partner;
    }

    public function getAffiliate()
    {
        return $this->affiliate;
    }

    public function setModerator(Affiliate $affiliate)
    {
        $this->moderator = $affiliate;
        if (!$this->moderator->is_moderator) {
            throw new InvalidModeratorException();
        }
        return $this;
    }

    public function setPartner($partner_id)
    {
        $this->partner = Partner::find($partner_id);
        $this->affiliate = $this->partner->affiliate;
        if (empty($this->partner)) {
            throw  new NotFoundException('Partner Does not exists', 404);
        }
        if (empty($this->affiliate)) {
            throw new InvalidModeratorException('This partner does not have any affiliate');
        }
        return $this;
    }

    private function validateRequest($data)
    {
        if ($this->partner->moderation_status && $this->partner->moderation_status != 'pending') {
            throw  new InvalidModeratorException('This partner is already moderated');
        }
        if ($this->moderatorRole == 'moderator') {
            if ($this->moderator->id != $this->partner->moderator_id) {
                throw new InvalidModeratorException('You are not a moderator of this partner');
            }
            $latLng = ['lat' => $data['lat'], 'lng' => $data['lng']];
            $this->validateLocation($latLng, $this->partner);
        }
        return true;
    }

    public function accept($data)
    {
        try {
            if ($this->validateRequest($data)) {
                DB::beginTransaction();
                $this->setPartnerData('approved');
                $this->affiliationRewards->setAffiliate($this->partner->affiliate)
                    ->payAffiliate($this->partner);
                if ($this->moderatorRole == 'moderator') {
                    $this->affiliationRewards->setModerator($this->moderator)
                        ->payModerator($this->partner, 'accept');
                }
                DB::commit();
            }
        } catch (InvalidModeratorException $exception) {
            throw $exception;
        } catch (ModeratorDistanceExceedException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            DB::rollback();
            throw $exception;
        }
    }

    public function reject($data)
    {
        try {
            if ($this->validateRequest($data)) {
                DB::beginTransaction();
                if (!isset($data['reject_reason'])) {
                    $data['reject_reason'] = 'Not Set';
                }
                $this->setPartnerData('rejected', $data['reject_reason']);
                if ($this->moderatorRole == 'moderator') {
                    $this->affiliationRewards->setModerator($this->moderator)
                        ->payModerator($this->partner, 'reject');
                }
                DB::commit();
            }
        } catch (InvalidModeratorException $exception) {
            throw $exception;
        } catch (ModeratorDistanceExceedException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            DB::rollback();
            throw $exception;
        }
    }

    private function validateLocation($source, $partner)
    {
        if (!empty($partner->geo_informations)) {
            $geo_info = json_decode($partner->geo_informations, true);
            $partner_radius = $geo_info['radius'] * 1000; // From KM to Meter
            $dist = self::calculateDistance($source, $geo_info);
            if ($dist < $partner_radius && $dist <= $this->distanceThreshold) {
            } else {
                throw new ModeratorDistanceExceedException();
            }
        } else {
            throw new \Exception('Partners Geo Information is not set yet');
        }
    }

    private function setPartnerData($status, $reason = null)
    {
        $this->partner->moderation_status = $status;
        if ($status == 'accepted') {
            if ($this->moderatorRole == 'moderator') {
                $this->partner->affiliation_cost = $this->affiliationRewards->getTotalCost();
            } else if ($this->moderatorRole == 'admin') {
                $this->partner->affiliation_cost = $this->affiliationRewards->getAffiliationCost();
            }
        } else {
            $this->partner->moderation_log = $reason;
            if ($this->moderatorRole == 'moderator') {
                $this->partner->affiliation_cost = $this->affiliationRewards->getModerationCost();
            } else if ($this->moderatorRole == 'admin') {
                $this->partner->affiliation_cost = 0;
            }
        }
        $this->partner->save();
    }

    public static function calculateDistance($source, $dest)
    {
        return self::vincentyGreatCircleDistance(floatval($source['lat']), floatval($source['lng']), floatval($dest['lat']), floatval($dest['lng']));
    }

    /**
     * Calculates the great-circle distance between two points, with
     * the Vincenty formula.
     * @param float $latitudeFrom Latitude of start point in [deg decimal]
     * @param float $longitudeFrom Longitude of start point in [deg decimal]
     * @param float $latitudeTo Latitude of target point in [deg decimal]
     * @param float $longitudeTo Longitude of target point in [deg decimal]
     * @param float $earthRadius Mean earth radius in [m]
     * @return float Distance between points in [m] (same as earthRadius)
     */
    private static function vincentyGreatCircleDistance(
        $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000.0)
    {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
            pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

        $angle = atan2(sqrt($a), $b);
        return $angle * $earthRadius;
    }
}
