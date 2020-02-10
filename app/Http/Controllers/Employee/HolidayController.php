<?php

namespace App\Http\Controllers\Employee;

use App\Models\BusinessMember;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Sheba\Dal\BusinessHoliday\Contract as BusinessHolidayRepoInterface;
use Sheba\Dal\BusinessWeekend\Contract as BusinessWeekendRepoInterface;

class HolidayController extends Controller
{
    public function getHolidays(Request $request, BusinessHolidayRepoInterface $business_holiday_repo,BusinessWeekendRepoInterface $business_weekend_repo)
    {
        $business_member = $this->getBusinessMember($request);
        if (!$business_member) return api_response($request, null, 404);

        $business_holidays = $business_holiday_repo->getAllByBusiness($business_member->business);
        $weekend = $business_weekend_repo->weekendDates($business_member->business);
        return api_response($request, null,200, ['holidays' => $business_holidays, 'weekends' => $weekend]);
    }

    private function getBusinessMember(Request $request)
    {
        $auth_info = $request->auth_info;
        $business_member = $auth_info['business_member'];
        if (!isset($business_member['id'])) return null;
        return BusinessMember::find($business_member['id']);
    }
}
