<?php namespace App\Sheba\Business\Attendance;

use App\Sheba\Business\Attendance\HalfDaySetting\HalfDayType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Sheba\Business\Attendance\AttendanceShiftFormatter;
use Sheba\Business\Attendance\CheckWeekend;
use Sheba\Dal\Attendance\Model as Attendance;
use Sheba\Dal\Attendance\Statuses;
use Sheba\Dal\AttendanceActionLog\Actions;
use Sheba\Dal\BusinessOffice\Contract as BusinessOffice;
use Sheba\Dal\BusinessWeekendSettings\BusinessWeekendSettingsRepo;
use Sheba\Helpers\TimeFrame;

class MonthlyStat
{
    /** @var TimeFrame $timeFrame */
    private $timeFrame;
    private $businessHoliday;
    private $businessWeekendSettings;
    private $forOneEmployee;
    private $businessMemberLeave;
    private $business;
    /*** @var BusinessOffice */
    private $businessOfficeRepo;
    private $isShiftOn;
    private $holidayBreakdown = [];
    private $dayWiseShifts;

    /**
     * @param TimeFrame $time_frame
     * @param $business
     * @param $weekend_settings
     * @param $business_member_leave
     * @param bool $for_one_employee
     * @param bool $is_shift_on
     */
    public function __construct(TimeFrame $time_frame, $business, $weekend_settings, $business_member_leave, $for_one_employee = true,
                                          $is_shift_on = false)
    {
        $this->timeFrame = $time_frame;
        $this->business = $business;
        $this->businessWeekendSettings = $weekend_settings;
        $this->businessMemberLeave = $business_member_leave;
        $this->forOneEmployee = $for_one_employee;
        $this->businessOfficeRepo = app(BusinessOffice::class);
        $this->isShiftOn = $is_shift_on;
    }

    /**
     * @param $business_holidays
     * @return $this
     */
    public function setBusinessHolidays($business_holidays)
    {
        $this->businessHoliday = $business_holidays;
        $this->getBreakdownHolidays();
        return $this;
    }

    /**
     * @return array
     */
    private function getBreakdownHolidays()
    {
        foreach ($this->businessHoliday as $holiday) {
            $start_date = Carbon::parse($holiday->start_date);
            $end_date = Carbon::parse($holiday->end_date);
            for ($d = $start_date; $d->lte($end_date); $d->addDay()) {
                $this->holidayBreakdown[] = $d->format('Y-m-d');
            }
        }

        return $this->holidayBreakdown;
    }

    /**
     * @param $attendances
     * @param $business_member_shifts
     * @return array[]
     */
    public function transform($attendances, $business_member_shifts = null)
    {
        $this->dayWiseShifts = $business_member_shifts;
        $check_weekend = new CheckWeekend();
        list($leaves, $leaves_date_with_half_and_full_day) = $this->formatLeaveAsDateArray();
        $leave_days = $late_days = $absent_days = [];

        $dates_of_holidays_formatted = $this->holidayBreakdown;
        $period = CarbonPeriod::create($this->timeFrame->start, $this->timeFrame->end);

        $statistics = [
            'working_days' => $period->count(),
            Statuses::ON_TIME => 0,
            Statuses::LATE => 0,
            Statuses::LEFT_EARLY => 0,
            Statuses::ABSENT => 0,
            Statuses::LEFT_TIMELY => 0,
            'on_leave' => 0,
            'full_day_leave' => 0,
            'half_day_leave' => 0,
            'present' => 0,
            'left_early_note' => 0,
            'total_hours' => 0,
            'overtime_in_minutes' => 0,
            'overtime' => 0,
            'remote_checkin' => 0,
            'office_checkin' => 0,
            'remote_checkout' => 0,
            'office_checkout' => 0,
            'total_checkout_miss' => 0
        ];

        $daily_breakdown = [];
        foreach ($period as $date) {
            $breakdown_data = [
                'date' => null,
                'weekend_or_holiday_tag' => null,
                'show_attendance' => 0,
                'attendance' => null,
                'is_absent' => 0,
            ];
            $shift = $this->getShiftOnDate($date);
            $is_unassigned = $shift && $shift->isUnassigned() ? 1 : 0;
            $is_general = $shift && $shift->isGeneral() ? 1 : 0;
            if ($shift) {
                if ($shift->isUnassigned()) $statistics['working_days']--;
            }

            $weekend_day = $check_weekend->getWeekendDays($date, $this->businessWeekendSettings);
            $is_weekend_or_holiday = $this->isWeekendHoliday($date, $weekend_day, $dates_of_holidays_formatted);
            $is_on_leave = $this->isLeave($date, $leaves);
            if ($is_weekend_or_holiday || $is_on_leave) {
                if ($this->forOneEmployee && !$shift || $this->forOneEmployee && !$this->isShiftOn || $this->forOneEmployee && $this->isShiftOn && $is_general) $breakdown_data['weekend_or_holiday_tag'] = $this->isWeekendHolidayLeaveTag($date, $leaves_date_with_half_and_full_day, $dates_of_holidays_formatted);
                if ($breakdown_data['weekend_or_holiday_tag'] === 'holiday') {
                    $breakdown_data['holiday_name'] = $this->getHolidayName($date);
                }
            }
            if ($is_weekend_or_holiday && (!$shift || $is_general)) {
                if (!$this->isHalfDayLeave($date, $leaves_date_with_half_and_full_day)) $statistics['working_days']--;
            }
            // leave calculation
            if ($is_on_leave) {
                if ($this->isFullDayLeave($date, $leaves_date_with_half_and_full_day)) {
                    $statistics['full_day_leave']++;
                    $breakdown_data['leave_type'] = $this->getLeaveType($date, $leaves_date_with_half_and_full_day);
                    $breakdown_data['weekend_or_holiday_tag'] = "full_day";
                }
                if ($this->isHalfDayLeave($date, $leaves_date_with_half_and_full_day)) $statistics['half_day_leave'] += 0.5;
                if (!$this->isHalfDayLeave($date, $leaves_date_with_half_and_full_day)) $statistics['working_days']--;
                $leave_days[] = $date->toDateString();
            }

            /** @var Attendance $attendance */
            $attendance = $attendances->where('date', $date->toDateString())->first();

            if ($attendance) {
                $overtime_in_minutes = (int)$attendance->overtime_in_minutes;
                $attendance_checkin_action = $attendance->checkinAction();
                $attendance_checkout_action = $attendance->checkoutAction();
                $is_in_wifi = $attendance_checkin_action->is_in_wifi;
                $is_geo = $attendance_checkin_action->is_geo_location;
                $business_office = $is_in_wifi || $is_geo ? $this->businessOfficeRepo->findWithTrashed($attendance_checkin_action->business_office_id) : null;

                $checkout_is_in_wifi = $attendance_checkout_action ? $attendance_checkout_action->is_in_wifi : null;
                $checkout_is_geo = $attendance_checkout_action ? $attendance_checkout_action->is_geo_location : null;
                $checkout_business_office = $checkout_is_in_wifi || $checkout_is_geo ? $this->businessOfficeRepo->findWithTrashed($attendance_checkout_action->business_office_id) : null;
                $totalCheckoutMiss = $attendance_checkin_action && !$attendance_checkout_action ? ($totalCheckoutMiss + 1) : ($totalCheckoutMiss + 0);
                if ($this->forOneEmployee) {
                    $business_office_name = $business_office ? $business_office->name : null;
                    $checkout_business_office_name = $checkout_business_office ? $checkout_business_office->name : null;
                    $breakdown_data['show_attendance'] = 1;
                    $breakdown_data['attendance'] = [
                        'id' => $attendance->id,
                        'check_in' => $attendance_checkin_action ? [
                            'status' => $is_unassigned || (!$shift || $is_general) && $is_weekend_or_holiday || $this->isFullDayLeave($date, $leaves_date_with_half_and_full_day) ? null : $attendance_checkin_action->status,
                            'time' => Carbon::parse($attendance->checkin_time)->format('h:i a'),
                            'is_remote' => $attendance_checkin_action->is_remote ?: 0,
                            'is_geo' => $is_geo,
                            'is_in_wifi' => $is_in_wifi,
                            'remote_mode' => $attendance_checkin_action->remote_mode ?: null,
                            'address' => $attendance_checkin_action->is_remote ?
                                $attendance_checkin_action->location ?
                                    json_decode($attendance_checkin_action->location)->address ?: json_decode($attendance_checkin_action->location)->lat . ', ' . json_decode($attendance_checkin_action->location)->lng
                                    : null
                                : $business_office_name,
                        ] : null,
                        'check_out' => $attendance_checkout_action ? [
                            'status' => $is_unassigned || (!$shift || $is_general) && $is_weekend_or_holiday || $this->isFullDayLeave($date, $leaves_date_with_half_and_full_day) ? null : $attendance_checkout_action->status,
                            'time' => Carbon::parse($attendance->checkout_time)->format('h:i a'),
                            'is_remote' => $attendance_checkout_action->is_remote ?: 0,
                            'is_geo' => $checkout_is_geo,
                            'is_in_wifi' => $checkout_is_in_wifi,
                            'remote_mode' => $attendance_checkout_action->remote_mode ?: null,
                            'address' => $attendance_checkout_action->is_remote ?
                                $attendance_checkout_action->location ?
                                    json_decode($attendance_checkout_action->location)->address ?: json_decode($attendance_checkout_action->location)->lat . ', ' . json_decode($attendance_checkout_action->location)->lng
                                    : null
                                : $checkout_business_office_name,
                        ] : null,
                        'late_note' => (!($is_weekend_or_holiday || $this->isFullDayLeave($date, $leaves_date_with_half_and_full_day)) && $attendance->hasLateCheckin()) ? $attendance->checkinAction()->note : null,
                        'left_early_note' => (!($is_weekend_or_holiday || $this->isFullDayLeave($date, $leaves_date_with_half_and_full_day)) && $attendance->hasEarlyCheckout()) ? $attendance->checkoutAction()->note : null,
                        'active_hours' => $attendance->staying_time_in_minutes ? formatMinuteToHourMinuteString($attendance->staying_time_in_minutes) : null,
                        'overtime_in_minutes' => $overtime_in_minutes ?: 0,
                        'overtime' => $overtime_in_minutes ? formatMinuteToHourMinuteString($overtime_in_minutes) : null,
                        'is_attendance_reconciled' => $attendance->is_attendance_reconciled,
                        'shift' => AttendanceShiftFormatter::get($attendance)
                    ];
                    if ($attendance->overrideLogs) {
                        foreach ($attendance->overrideLogs as $override_log) {
                            if ($override_log->action == Actions::CHECKIN) $breakdown_data['check_in_overridden'] = 1;
                            if ($override_log->action == Actions::CHECKOUT) $breakdown_data['check_out_overridden'] = 1;
                        }
                    }
                }

                if (!($is_unassigned || (!$shift || $is_general) && $is_weekend_or_holiday || $this->isFullDayLeave($date, $leaves_date_with_half_and_full_day)) && $attendance->hasLateCheckin()) $late_days[] = $date->toDateString();

                if (!($is_unassigned || (!$shift || $is_general) && $is_weekend_or_holiday || $this->isFullDayLeave($date, $leaves_date_with_half_and_full_day)) && $attendance_checkin_action) $statistics[$attendance_checkin_action->status]++;

                if (!($is_unassigned || (!$shift || $is_general) && $is_weekend_or_holiday || $this->isFullDayLeave($date, $leaves_date_with_half_and_full_day)) && $attendance_checkout_action) $statistics[$attendance_checkout_action->status]++;
                $statistics['left_early_note'] = (!($is_weekend_or_holiday || $this->isFullDayLeave($date, $leaves_date_with_half_and_full_day)) && $attendance->hasEarlyCheckout()) ? $attendance->checkoutAction()->note : null;
                $statistics['total_hours'] += $attendance->staying_time_in_minutes;
                $statistics['overtime_in_minutes'] += $overtime_in_minutes ?: 0;
                if ($attendance_checkin_action->is_remote) $statistics['remote_checkin'] = $statistics['remote_checkin'] + 1;
                if ($business_office) $statistics['office_checkin'] = $statistics['office_checkin'] + 1;
                if ($attendance_checkout_action && $attendance_checkout_action->is_remote) $statistics['remote_checkout'] = $statistics['remote_checkout'] + 1;
                if ($checkout_business_office) $statistics['office_checkout'] = $statistics['office_checkout'] + 1;
                $statistics['total_checkout_miss'] = $totalCheckoutMiss;
            }

            if ($this->isAbsent($attendance, ($is_unassigned || (!$shift || $is_general) && $is_weekend_or_holiday || $this->isFullDayLeave($date, $leaves_date_with_half_and_full_day)), $date)) {
                if ($this->forOneEmployee) $breakdown_data['is_absent'] = 1;
                $statistics[Statuses::ABSENT]++;
                $absent_days[] = $date->toDateString();
            }

            if ($this->forOneEmployee) $breakdown_data['date'] = $date->toDateString();
            if ($this->forOneEmployee) $daily_breakdown[] = $breakdown_data;
        }

        $statistics['present'] = $statistics[Statuses::ON_TIME] + $statistics[Statuses::LATE];
        $statistics['on_leave'] = $statistics['full_day_leave'] + $statistics['half_day_leave'];
        $statistics['total_hours'] = $statistics['total_hours'] ? formatMinuteToHourMinuteString($statistics['total_hours']) : 0;
        $statistics['overtime'] = $statistics['overtime_in_minutes'] ? formatMinuteToHourMinuteString($statistics['overtime_in_minutes']) : 0;
        $statistics['leave_days'] = !empty($leave_days) ? implode(", ", $leave_days) : "-";
        $statistics['absent_days'] = !empty($absent_days) ? implode(", ", $absent_days) : "-";
        $statistics['late_days'] = !empty($late_days) ? implode(", ", $late_days) : "-";

        $result = ['statistics' => $statistics];
        if ($this->forOneEmployee) $result['daily_breakdown'] = $daily_breakdown;
        return $result;
    }

    private function getShiftOnDate(Carbon $date)
    {
        $key = $date->toDateString();
        if($this->dayWiseShifts && !$this->dayWiseShifts->has($key)) return null;
        return $this->dayWiseShifts[$key];
    }

    /**
     * @param Carbon $date
     * @param $weekend_day
     * @return bool
     */
    private function isWeekend(Carbon $date, $weekend_day)
    {
        return in_array(strtolower($date->format('l')), $weekend_day);
    }

    /**
     * @param Carbon $date
     * @param $holidays
     * @return bool
     */
    private function isHoliday(Carbon $date, $holidays)
    {
        return in_array($date->format('Y-m-d'), $holidays);
    }

    /**
     * @return array
     */
    private function formatLeaveAsDateArray()
    {
        $business_member_leaves_date = [];
        $business_member_leaves_date_with_half_and_full_day = [];
        $this->businessMemberLeave->each(function ($leave) use (&$business_member_leaves_date, &$business_member_leaves_date_with_half_and_full_day) {
            $leave_period = CarbonPeriod::create($leave->start_date, $leave->end_date);
            foreach ($leave_period as $date) {
                $business_member_leaves_date[] = $date->toDateString();
                $business_member_leaves_date_with_half_and_full_day[$date->toDateString()] = [
                    'is_half_day_leave' => $leave->is_half_day,
                    'which_half_day' => $leave->half_day_configuration,
                    'leave_type' => $leave->leaveType()->withTrashed()->first()->title
                ];
            }
        });

        return [array_unique($business_member_leaves_date), $business_member_leaves_date_with_half_and_full_day];
    }

    /**
     * @param Carbon $date
     * @param array $leaves
     * @return bool
     */
    private function isLeave(Carbon $date, array $leaves)
    {
        return in_array($date->format('Y-m-d'), $leaves);
    }

    /**
     * @param Carbon $date
     * @param array $leaves_date_with_half_and_full_day
     * @return int
     */
    private function isFullDayLeave(Carbon $date, array $leaves_date_with_half_and_full_day)
    {
        if (array_key_exists($date->format('Y-m-d'), $leaves_date_with_half_and_full_day)) {
            if ($leaves_date_with_half_and_full_day[$date->format('Y-m-d')]['is_half_day_leave'] == 0) return 1;
        }
        return 0;
    }

    /**
     * @param Carbon $date
     * @param array $leaves_date_with_half_and_full_day
     * @return int
     */
    private function isHalfDayLeave(Carbon $date, array $leaves_date_with_half_and_full_day)
    {
        if (array_key_exists($date->format('Y-m-d'), $leaves_date_with_half_and_full_day)) {
            if ($leaves_date_with_half_and_full_day[$date->format('Y-m-d')]['is_half_day_leave'] == 1) return 1;
        }
        return 0;
    }

    /**
     * @param Carbon $date
     * @param array $leaves_date_with_half_and_full_day
     * @return string
     */
    private function whichHalfDayLeave(Carbon $date, array $leaves_date_with_half_and_full_day)
    {
        if (array_key_exists($date->format('Y-m-d'), $leaves_date_with_half_and_full_day)) {
            if ($leaves_date_with_half_and_full_day[$date->format('Y-m-d')]['which_half_day'] == HalfDayType::FIRST_HALF) return HalfDayType::FIRST_HALF;
        }
        return HalfDayType::SECOND_HALF;
    }

    /**
     * @param $date
     * @param $weekend_day
     * @param $dates_of_holidays_formatted
     * @return int
     */
    private function isWeekendHoliday($date, $weekend_day, $dates_of_holidays_formatted)
    {
        return $this->isWeekend($date, $weekend_day)
            || $this->isHoliday($date, $dates_of_holidays_formatted);

    }

    /**
     * @param $date
     * @param $leaves_date_with_half_and_full_day
     * @param $dates_of_holidays_formatted
     * @return string
     */
    private function isWeekendHolidayLeaveTag($date, $leaves_date_with_half_and_full_day, $dates_of_holidays_formatted)
    {
        if ($this->isFullDayLeave($date, $leaves_date_with_half_and_full_day)) return 'full_day';
        if ($this->isHalfDayLeave($date, $leaves_date_with_half_and_full_day)) return $this->whichHalfDayLeave($date, $leaves_date_with_half_and_full_day);
        if ($this->isHoliday($date, $dates_of_holidays_formatted)) return 'holiday';
        return 'weekend';
    }

    /**
     * @param Attendance $attendance | null
     * @param $is_weekend_or_holiday_or_leave
     * @param Carbon $date
     * @return bool
     */
    private function isAbsent($attendance, $is_weekend_or_holiday_or_leave, Carbon $date)
    {
        return !$attendance && !$is_weekend_or_holiday_or_leave && $date->lt(Carbon::today());
    }

    /**
     * @param $date
     * @return null
     */
    private function getHolidayName($date)
    {
        $holiday_name = null;
        foreach ($this->businessHoliday as $holiday) {
            if (!$date->between($holiday->start_date, $holiday->end_date)) continue;
            $holiday_name = $holiday->title;
            break;
        }
        return $holiday_name;
    }

    /**
     * @param Carbon $date
     * @param array $leaves_date_with_half_and_full_day
     * @return mixed|null
     */
    private function getLeaveType(Carbon $date, array $leaves_date_with_half_and_full_day)
    {
        $key = $date->format('Y-m-d');
        if (!array_key_exists($key, $leaves_date_with_half_and_full_day)) return null;

        return $leaves_date_with_half_and_full_day[$key]['leave_type'];
    }
}
