<?php namespace App\Sheba\Business\Attendance;


use Carbon\Carbon;
use Carbon\CarbonPeriod;

trait AttendanceBasicInfo
{
    public function isLeave(Carbon $date, array $leaves)
    {
        return in_array($date->format('Y-m-d'), $leaves);
    }

    public function formatLeaveAsDateArray($business_member_leave)
    {
        $business_member_leaves_date = [];
        $business_member_leaves_date_with_half_and_full_day = [];
        $business_member_leave->each(function ($leave) use (&$business_member_leaves_date, &$business_member_leaves_date_with_half_and_full_day) {
            $leave_period = $this->createPeriodByTime($leave->start_date, $leave->end_date);
            foreach ($leave_period as $date) {
                array_push($business_member_leaves_date, $date->toDateString());
                $business_member_leaves_date_with_half_and_full_day[$date->toDateString()] = [
                    'is_half_day_leave' => $leave->is_half_day,
                    'which_half_day' => $leave->half_day_configuration,
                ];
            }
        });

        return [array_unique($business_member_leaves_date), $business_member_leaves_date_with_half_and_full_day];
    }

    public function isWeekendHoliday($date, $weekend_day, $dates_of_holidays_formatted)
    {
        return $this->isWeekend($date, $weekend_day) || $this->isHoliday($date, $dates_of_holidays_formatted);
    }

    public function isWeekend(Carbon $date, $weekend_day)
    {
        return in_array(strtolower($date->format('l')), $weekend_day);
    }

    public function isHoliday(Carbon $date, $holidays)
    {
        return in_array($date->format('Y-m-d'), $holidays);
    }

    public function getHolidaysFormatted($business_holiday)
    {
        $data = [];
        foreach ($business_holiday as $holiday) {
            $start_date = Carbon::parse($holiday->start_date);
            $end_date = Carbon::parse($holiday->end_date);
            for ($d = $start_date; $d->lte($end_date); $d->addDay()) {
                $data[] = $d->format('Y-m-d');
            }
        }
        return $data;
    }

    public function getFormattedWeekendsString($weekends)
    {
        sort($weekends);
        return implode(', ', array_map('ucfirst', $weekends));
    }

    public function createPeriodByTime($start_date, $end_date)
    {
        return CarbonPeriod::create($start_date, $end_date);
    }
}
