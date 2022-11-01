<?php namespace App\Transformers\Business;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Member;
use App\Models\Profile;
use App\Sheba\Business\Attendance\MonthlyStat;
use Carbon\Carbon;
use Illuminate\Http\Request;
use League\Fractal\TransformerAbstract;
use Sheba\Dal\Attendance\Contract as AttendanceRepoInterface;
use Sheba\Helpers\TimeFrame;

class AttendanceMonthlyListTransformer extends TransformerAbstract
{
    const FIRST_DAY_OF_MONTH = 1;

    /**  @var TimeFrame $timeFrame */
    private $timeFrame;
    private $startDate;
    private $endDate;
    private $businessHolidays;
    private $weekendSettings;
    /**  @var Business $business */
    private $business;
    /**  @var AttendanceRepoInterface $attendanceRepo */
    private $attendanceRepo;


    public function __construct()
    {
        $this->attendanceRepo = app(AttendanceRepoInterface::class);
    }

    public function setBusiness(Business $business)
    {
        $this->business = $business;
        return $this;
    }

    public function setBusinessHolidays($business_holidays)
    {
        $this->businessHolidays = $business_holidays;
        return $this;
    }

    public function setBusinessWeekendSettings($weekend_settings)
    {
        $this->weekendSettings = $weekend_settings;
        return $this;
    }

    public function setStartDate($start_date)
    {
        $this->startDate = $start_date;
        return $this;
    }

    public function getStartDate()
    {
        return $this->startDate;
    }

    public function setEndDate($end_date)
    {
        $this->endDate = $end_date;
        return $this;
    }

    public function getEndDate()
    {
        return $this->endDate;
    }

    public function getDateRange()
    {
        if (!$this->getStartDate() || !$this->getEndDate()) {
            $this->startDate = Carbon::now()->startOfMonth()->toDateString();
            $this->endDate = Carbon::now()->endOfMonth()->toDateString();
        }
        return (new TimeFrame())->forDateRange($this->startDate, $this->endDate);
    }

    public function transform(BusinessMember $business_member)
    {
        $department = $business_member->department();
        /** @var Member $member */
        $member = $business_member->member;
        /** @var Profile $profile */
        $profile = $member->profile;


        $this->startDate = $this->getStartDate();
        $this->endDate = $this->getEndDate();

        $business_member_joining_date = $business_member->join_date;
        $joining_prorated = null;
        if ($this->checkJoiningDate($business_member_joining_date)) {
            $joining_prorated = 1;
            $this->endDate = $business_member_joining_date;
        }

        $this->timeFrame = $this->getDateRange();

        $business_member_leave = $business_member->leaves()->accepted()->startDateBetween($this->timeFrame)->endDateBetween($this->timeFrame)->get();
        $attendances = $this->attendanceRepo->getAllAttendanceByBusinessMemberFilteredWithYearMonth($business_member, $this->timeFrame);

        $shifts_counts = 0;
        if ($business_member->isShiftEnable())
            $shifts_counts = $business_member->shifts()->where('is_general', 0)->whereBetween('date', $this->timeFrame->getArray())->count();


        $employee_attendance = (new MonthlyStat($this->timeFrame, $this->business,
            $this->weekendSettings, $business_member_leave, false, $business_member->isShiftEnable()))
            ->setBusinessHolidays($this->businessHolidays)->transform($attendances, $shifts_counts);

        return [
            'business_member_id' => $business_member->id,
            'employee_id' => $business_member->employee_id ?: 'N/A',
            'email' => $profile->email,
            'status' => $business_member->status,
            'member' => [
                'name' => $profile->name,
            ],
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
            ],
            'attendance' => $employee_attendance['statistics'],
            'joining_prorated' => $joining_prorated ? 'Yes' : 'No'
        ];

    }

    /**
     * @param $business_member_joining_date
     * @return bool
     */
    private function checkJoiningDate($business_member_joining_date)
    {
        if (!$business_member_joining_date) return false;
        if ($business_member_joining_date->format('d') == self::FIRST_DAY_OF_MONTH) return false;
        return $business_member_joining_date->format('Y-m-d') >= $this->startDate && $business_member_joining_date->format('Y-m-d') <= $this->endDate;
    }
}