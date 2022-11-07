<?php namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\BusinessMember;
use App\Models\BusinessRole;
use App\Sheba\Business\LiveTracking\DateDropDown;
use Illuminate\Support\Arr;
use Sheba\Business\LiveTracking\LiveTrackingInsertJob;
use Sheba\Dal\TrackingLocation\TrackingLocation;
use App\Sheba\Business\BusinessBasicInformation;
use App\Sheba\Business\CoWorker\ManagerSubordinateEmployeeList;
use App\Transformers\CustomSerializer;
use App\Transformers\Employee\LiveTrackingLocationList;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use Sheba\Business\CoWorker\Filter\CoWorkerInfoFilter;
use Sheba\Dal\TrackingLocation\TrackingLocationRepository;
use Sheba\Location\Geo;
use Sheba\Map\Client\BarikoiClient;
use Sheba\ModificationFields;
use Throwable;

class TrackingController extends Controller
{
    use BusinessBasicInformation, ModificationFields;

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function insertLocation(Request $request)
    {
        $business_member = $request->business_member;
        $business = $this->getBusiness($request);
        $manager_member = $this->getMember($request);
        $this->setModifier($manager_member);

        $locations = $request->locations;

        dispatch(new LiveTrackingInsertJob($locations, $business->id, $business_member->id));

        return api_response($request, null, 200);
    }

    /**
     * @param $business_member_id
     * @param Request $request
     * @return JsonResponse
     */
    public function trackingLocationDetails($business_member_id, Request $request)
    {
        /** @var BusinessMember $business_member */
        $business_member = BusinessMember::find((int)$business_member_id);

        if (!$business_member) return api_response($request, null, 404);

        if (!$request->date) return api_response($request, null, 404);
        $tracking_locations = $business_member->liveLocationFilterByDate($request->date)->get();

        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $resource = new Collection($tracking_locations, new LiveTrackingLocationList());
        $tracking_locations = $manager->createData($resource)->toArray()['data'];

        return api_response($request, null, 200, ['tracking_locations' => $tracking_locations]);
    }

    /**
     * @param  Request  $request
     * @param  TrackingLocationRepository  $tracking_location_repository
     * @param  ManagerSubordinateEmployeeList  $manager_subordinate_employee_list
     * @return JsonResponse
     */
    public function getManagerSubordinateList(
        Request $request,
        TrackingLocationRepository $tracking_location_repository,
        ManagerSubordinateEmployeeList $manager_subordinate_employee_list
    ): JsonResponse
    {
        $business = $request->business;
        $business_member = $request->business_member;

        $managers = [];
        $manager_subordinate_employee_list->setBusiness($business)->getManager($business_member->id, $managers, $business_member->id);
        $managers_subordinate_ids = array_keys($managers);

        $business_members = BusinessMember::with([
            'member' => function ($q) {
                $q->select('members.id', 'profile_id')->with([
                    'profile' => function ($q) {
                        $q->select('profiles.id', 'name', 'mobile', 'email', 'pro_pic', 'dob', 'address', 'nationality', 'nid_no', 'tin_no');
                    }
                ]);
            }, 'role' => function ($q) {
                $q->select('business_roles.id', 'business_department_id', 'name')->with([
                    'businessDepartment' => function ($q) {
                        $q->select('business_departments.id', 'business_id', 'name');
                    }
                ]);
            }
        ])->whereIn('business_member.id', $managers_subordinate_ids);

        if ($request->has('department')) {
            $business_members = $business_members->whereHas('role', function ($q) use ($request) {
                $q->whereHas('businessDepartment', function ($q) use ($request) {
                    $q->whereIn('business_departments.id', json_decode($request->department));
                });
            });
        }

        $data = [];
        $business_member_ids = $business_members->pluck('id')->toArray();
        $business_members_last_location = [];

        $tracking_location_repository->getBusinessMembersLastLocationByBusinessForLastNDaysByRaw($business->id, $business_member_ids, 7)
            ->each(function ($last_location) use (&$business_members_last_location) {
                $business_members_last_location[$last_location->business_member_id] = collect([
                    "business_member_id" => $last_location->business_member_id,
                    "business_id"        => $last_location->business_id,
                    "location"           => $last_location->location,
                    "time"               => $last_location->time,
                    "created_at"         => $last_location->created_at
                ]);
            });

        foreach ($business_members->get() as $business_member) {
            if (!array_key_exists($business_member->id, $business_members_last_location)) continue;
            $tracking_location = $business_members_last_location[$business_member->id];
            $location = $tracking_location['location'];

            $profile = $business_member->member->profile;
            /** @var BusinessRole $role */
            $role = $business_member->role;
            $data[] = [
                'business_member_id' => $business_member->id,
                'employee_id' => $business_member->employee_id,
                'business_id' => $tracking_location['business_id'],
                'department_id' => $role ? $role->businessDepartment->id : null,
                'department' => $role ? $role->businessDepartment->name : null,
                'designation' => $role ? $role->name : null,
                'profile' => [
                    'id' => $profile->id,
                    'name' => $profile->name ?: null,
                    'pro_pic' => $profile->pro_pic
                ],
                'time' => Carbon::parse($tracking_location['time'])->format('h:i a'),
                'location' => $location ? [
                    'lat' => $location->lat,
                    'lng' => $location->lng,
                    'address' => $location->address,
                ] : null,
                'last_activity_raw' => $tracking_location['created_at']
            ];
        }

        if ($request->has('activity') && $request->activity != "null") $data = $this->getEmployeeOfNoActivityForCertainHour($data, $request->activity);
        if ($request->has('search')) $data = $this->searchEmployee($data, $request);

        $data = collect($data)->values();
        return api_response($request, null, 200, ['employee_list' => $data]);
    }

    /**
     * @param Request $request
     * @param DateDropDown $date_drop_down
     * @return JsonResponse
     */
    public function lastTrackedDate(Request $request, DateDropDown $date_drop_down)
    {
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;

        $last_tracked_location = $business_member->liveLocationFilterByDate()->first();
        if (!$last_tracked_location) return api_response($request, null, 404);

        list($last_tracked_date, $date_dropdown) = $date_drop_down->getDateDropDown($last_tracked_location);
        return api_response($request, null, 200, ['last_tracked' => $last_tracked_date, 'date_dropdown' => $date_dropdown]);
    }

    /**
     * @param $tracking_locations
     * @param $activity
     * @return \Illuminate\Support\Collection
     */
    private function getEmployeeOfNoActivityForCertainHour($tracking_locations, $activity)
    {
        $from_time = Carbon::now()->subMinutes($activity);
        return collect($tracking_locations)->filter(function ($tracking_location) use ($from_time) {
            return $tracking_location['last_activity_raw'] <= $from_time;
        });
    }

    /**
     * @param $all_data
     * @param Request $request
     * @return \Illuminate\Support\Collection
     */
    private function searchEmployee($all_data, Request $request)
    {
        return collect($all_data)->filter(function ($data) use ($request) {
            return str_contains(strtoupper($data['profile']['name']), strtoupper($request->search));
        });
    }
}
