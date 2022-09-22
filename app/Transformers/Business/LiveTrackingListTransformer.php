<?php namespace App\Transformers\Business;

use League\Fractal\TransformerAbstract;

class LiveTrackingListTransformer extends TransformerAbstract
{
    /** @var array $businessMembersWithProfile */
    private $businessMembersWithProfile;

    public function __construct($business_members_with_profile)
    {
        $this->businessMembersWithProfile = $business_members_with_profile;
    }

    /**
     * @param $tracking_locations
     * @return array
     */
    public function transform($tracking_locations): array
    {
        $location = $tracking_locations->location;
        return [
            'employee' => $this->businessMembersWithProfile[$tracking_locations->business_member_id],
            'business_member_id' => $tracking_locations->business_member_id,
            'business_id' => $tracking_locations->business_id,
            'last_activity_raw' => $tracking_locations->created_at,
            'last_activity' => $tracking_locations->created_at->format('h:i A, jS F'),
            'last_location_lat' => $location ? $location->lat : null,
            'last_location_lng' => $location ? $location->lng : null,
            'last_location_address' => $location ? $location->address : null,
        ];
    }
}
