<?php namespace App\Transformers\Business;

use App\Models\BusinessDepartment;
use App\Models\BusinessRole;
use App\Models\Member;
use App\Models\Profile;
use App\Sheba\Business\CoWorker\ProfileInformation\SocialLink;
use Sheba\Dal\BusinessHoliday\Contract as BusinessHolidayRepoInterface;
use Sheba\Dal\BusinessOffice\Contract as BusinessOffice;
use Sheba\Dal\Attendance\Contract as AttendanceRepoInterface;
use Sheba\Dal\BusinessWeekendSettings\BusinessWeekendSettingsRepo;
use Sheba\Dal\LeaveType\Contract as LeaveTypesRepoInterface;
use League\Fractal\TransformerAbstract;
use App\Transformers\CustomSerializer;
use League\Fractal\Resource\Item;
use App\Models\BusinessMember;
use Sheba\Dal\ShiftAssignment\ShiftAssignment;
use Sheba\Helpers\TimeFrame;
use League\Fractal\Manager;
use Carbon\Carbon;

class MySubordinateDetailsTransformer extends TransformerAbstract
{
    private $year;
    private $month;

    public function __construct($year, $month)
    {
        $this->year = $year;
        $this->month = $month;
    }

    public function transform(BusinessMember $business_member)
    {
        return [
            'profile' => $this->getEmployeeProfile($business_member),
            'attendance_summary' => $this->getAttendanceSummary($business_member),
            'leave_summary' => $this->getLeaveSummary($business_member)
        ];
    }
    /**
     * @param BusinessMember $business_member
     * @return array
     */
    private function getEmployeeProfile(BusinessMember $business_member)
    {
        /** @var Member $member */
        $member = $business_member->member;
        /** @var Profile $profile */
        $profile = $member->profile;

        /** @var BusinessRole $role */
        $role = $business_member->role;
        /** @var BusinessDepartment $department */
        $department = $role ? $role->businessDepartment : null;

        return [
            'id' => $profile->id,
            'name' => $profile->name ?: null,
            'pro_pic' => $profile->pro_pic,
            'phone' => $business_member->mobile,
            'dob' => $profile->dob ? Carbon::parse($profile->dob)->format('d F, Y') : null,
            'blood_group' => $profile->blood_group,
            'social_links' => (new SocialLink($member))->get(),
            'designation' => $role ? $role->name : null,
            'department' => $department ? $department->name : null,
        ];
    }

    public function getAttendanceSummary($business_member)
    {
        $business_holiday_repo = app(BusinessHolidayRepoInterface::class);
        $business_weekend_repo = app(BusinessWeekendSettingsRepo::class);
        $attendance_repo = app(AttendanceRepoInterface::class);
        $business_office_repo = app(BusinessOffice::class);
        $time_frame = app(TimeFrame::class);

        $time_frame = $time_frame->forAMonth($this->month, $this->year);
        $business_member_leave = $business_member->leaves()->accepted()->between($time_frame)->get();
        $attendances = $attendance_repo->getAllAttendanceByBusinessMemberFilteredWithYearMonth($business_member, $time_frame);

        $business_holiday = $business_holiday_repo->getAllByBusiness($business_member->business);
        $business_weekend = $business_weekend_repo->getAllByBusiness($business_member->business);
        $dayWiseShifts = $this->loadShifts($business_member, $time_frame);

        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $resource = new Item($attendances, new AttendanceTransformer($time_frame, $business_holiday, $business_weekend, $business_member_leave, $business_office_repo, $dayWiseShifts));
        $attendances_data = $manager->createData($resource)->toArray()['data'];

        return $attendances_data['statistics'];
    }

    public function getLeaveSummary(BusinessMember $business_member)
    {
        $leave_types_repo = app(LeaveTypesRepoInterface::class);

        $leave_types = $leave_types_repo->getAllLeaveTypesByBusinessMember($business_member);

        foreach ($leave_types as $leave_type) {
            $leaves_taken = $business_member->getCountOfUsedLeaveDaysByTypeOnAFiscalYear($leave_type->id);
            $leave_type->available_days = $leave_type->total_days - $leaves_taken;
        }

        return $leave_types;
    }

    private function loadShifts($business_member, $time_frame)
    {
        return $business_member->shifts()
            ->selectTypes()
            ->within($time_frame)
            ->selectBusinessMember()
            ->selectDate()
            ->get()
            ->toAssocFromKey(function (ShiftAssignment $assignment) {
                return $assignment->getDate()->toDateString();
            });
    }
}
