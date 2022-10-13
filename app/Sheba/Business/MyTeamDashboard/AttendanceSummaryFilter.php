<?php namespace Sheba\Business\MyTeamDashboard;

use App\Models\Business;
use App\Models\BusinessMember;
use Carbon\Carbon;
use Sheba\Business\Attendance\CheckWeekend;
use Sheba\Dal\Attendance\Contract as AttendanceRepositoryInterface;
use Sheba\Dal\Attendance\Statuses;
use Sheba\Dal\AttendanceActionLog\Actions;
use Sheba\Dal\BusinessHoliday\Contract as BusinessHolidayRepoInterface;
use Sheba\Dal\BusinessWeekend\Contract as BusinessWeekendRepoInterface;
use Sheba\Dal\BusinessWeekendSettings\BusinessWeekendSettingsRepo;
use Sheba\Dal\Leave\Contract as LeaveRepositoryInterface;
use Sheba\Helpers\TimeFrame;
use Sheba\Repositories\Interfaces\BusinessMemberRepositoryInterface;

class AttendanceSummaryFilter
{
    const ALL = 'all';
    const PRESENT = 'present';
    const ABSENT = 'absent';
    const ON_LEAVE = 'on_leave';
    const CHECKIN_TIME = 'checkin_time';
    const CHECKOUT_TIME = 'checkout_time';

    /** @var Business */
    private $business;
    /** @var Carbon */
    private $startDate;
    /** @var Carbon */
    private $endDate;
    private $selectedDate;
    /** @var AttendanceRepositoryInterface $attendanceRepositoryInterface */
    private $attendanceRepositoryInterface;
    /** @var LeaveRepositoryInterface $leaveRepositoryInterface */
    private $leaveRepositoryInterface;
    /** @var BusinessMemberRepositoryInterface */
    private $businessMemberRepository;
    /** @var BusinessWeekendRepoInterface $businessWeekend */
    private $businessWeekend;
    private $commonFunctions;
    private $usersWhoGiveAttendance = [];
    private $usersWhoOnLeave = [];
    private $usersLeaveIds = [];
    private $myTeam;
    private $statusFilter;
    private $attendances;
    private $isWeekendOrHoliday;
    private $teamBusinessMemberIds;
    private $statuses = [Statuses::ON_TIME, Statuses::LATE, Statuses::LEFT_TIMELY, Statuses::LEFT_EARLY];
    private $businessWeekendSettingsRepo;
    private $checkWeekend;

    /**
     * @param AttendanceRepositoryInterface $attendance_repository_interface
     * @param LeaveRepositoryInterface $leave_repository_interface
     * @param BusinessMemberRepositoryInterface $business_member_repository
     * @param BusinessWeekendRepoInterface $business_weekend_repo
     * @param CommonFunctions $common_functions
     */
    public function __construct(AttendanceRepositoryInterface     $attendance_repository_interface,
                                LeaveRepositoryInterface          $leave_repository_interface,
                                BusinessMemberRepositoryInterface $business_member_repository,
                                BusinessWeekendRepoInterface      $business_weekend_repo,
                                CommonFunctions                   $common_functions,
                                BusinessWeekendSettingsRepo       $business_weekend_settings_repo,
                                CheckWeekend                      $check_weekend)
    {
        $this->attendanceRepositoryInterface = $attendance_repository_interface;
        $this->leaveRepositoryInterface = $leave_repository_interface;
        $this->businessMemberRepository = $business_member_repository;
        $this->businessWeekend = $business_weekend_repo;
        $this->commonFunctions = $common_functions;
        $this->businessWeekendSettingsRepo = $business_weekend_settings_repo;
        $this->checkWeekend = $check_weekend;
    }

    /**
     * @param Business $business
     * @return $this
     */
    public function setBusiness(Business $business)
    {
        $this->business = $business;
        return $this;
    }

    /**
     * @param TimeFrame $selected_date
     * @return $this
     */
    public function setSelectedDate(TimeFrame $selected_date)
    {
        $this->selectedDate = $selected_date;
        $this->startDate = $selected_date->start;
        $this->endDate = $selected_date->end;
        return $this;
    }

    /**
     * @param $status_filter
     * @return $this
     */
    public function setStatusFilter($status_filter)
    {
        $this->statusFilter = $status_filter;
        return $this;
    }

    /**
     * @param $my_team
     * @return $this
     */
    public function setMyTeam($my_team)
    {
        $this->myTeam = $my_team;
        return $this;
    }

    /**
     * @return array
     */
    public function get()
    {
        $this->runAttendanceQuery();
        $this->isWeekendOrHoliday = $this->commonFunctions->setBusiness($this->business)->setSelectedDate($this->selectedDate)->isWeekendHoliday();
        return $this->getData();
    }

    private function runAttendanceQuery()
    {
        $business_member_ids = array_column($this->myTeam, 'id');
        $this->teamBusinessMemberIds = $business_member_ids;
        $attendances = $this->getAttendanceInfo($business_member_ids);

        if ($this->statusFilter === Statuses::ON_TIME) {
            $attendances = $attendances->whereHas('actions', function ($q) {
                $q->where('status', $this->statusFilter);
            });
        }
        if ($this->statusFilter === Statuses::LATE) {
            $attendances = $attendances->whereHas('actions', function ($q) {
                $q->where('status', $this->statusFilter);
            });
        }
        if ($this->statusFilter === Statuses::LEFT_TIMELY) {
            $attendances = $attendances->whereHas('actions', function ($q) {
                $q->where('status', $this->statusFilter);
            });
        }
        if ($this->statusFilter === Statuses::LEFT_EARLY) {
            $attendances = $attendances->whereHas('actions', function ($q) {
                $q->where('status', $this->statusFilter);
            });
        }

        $this->attendances = $attendances->get();
    }

    private function getAttendanceInfo($business_member_ids) {
        return $this->attendanceRepositoryInterface->builder()
            ->select('id', 'business_member_id', 'checkin_time', 'checkout_time', 'status', 'date')
            ->whereIn('business_member_id', $business_member_ids)
            ->where('date', '>=', $this->startDate->toDateString())
            ->where('date', '<=', $this->endDate->toDateString())
            ->with([
                'actions' => function ($q) {
                    $q->select('id', 'attendance_id', 'note', 'action', 'status', 'is_remote', 'remote_mode', 'location', 'created_at');
                },
                'businessMember' => function ($q) {
                    $q->select('id', 'member_id', 'business_role_id', 'employee_id')
                        ->with([
                            'member' => function ($q) {
                                $q->select('members.id', 'profile_id')
                                    ->with([
                                        'profile' => function ($q) {
                                            $q->select('profiles.id', 'name', 'pro_pic');
                                        }]);
                            }, 'role' => function ($q) {
                                $q->select('business_roles.id', 'name');
                            }
                        ]);
                }]);
    }

    private function getData()
    {
        $data = [];

        $is_weekend_or_holiday = $this->isWeekendOrHoliday;
        $business_members_in_leave = $this->getBusinessMemberWhoAreOnLeave();

        if ($this->statusFilter != self::ABSENT) {
            foreach ($this->attendances as $attendance) {
                $checkin_data = $checkout_data = null;
                $is_on_leave = $this->isOnLeave($attendance->businessMember->id);
                $is_on_half_day_leave = 0;
                $which_half_day = null;
                if ($is_on_leave) {
                    $leave = $this->usersLeaveIds[$attendance->businessMember->id];
                    $is_on_half_day_leave = $leave['leave']['is_half_day_leave'];
                    $which_half_day = $leave['leave']['which_half_day'];
                }

//                if ($this->statusFilter == self::ON_LEAVE && !$is_on_half_day_leave) continue;
                array_push($this->usersWhoGiveAttendance, $attendance->businessMember->id);


                if (
                    !$is_on_half_day_leave &&
                    $is_on_leave && in_array($this->statusFilter, $this->statuses)
                ) continue;

                foreach ($attendance->actions as $action) {
                    if ($action->action == Actions::CHECKIN) {
                        $checkin_data = collect([
                            'status' => $this->getStatusBasedOnLeaveAction($action, $is_weekend_or_holiday, $is_on_leave, $is_on_half_day_leave),
                            'is_remote' => $action->is_remote ?: 0,
                            'address' => $action->is_remote ?
                                $action->location ? json_decode($action->location)->address : null
                                : null,
                            'checkin_time' => Carbon::parse($attendance->date . ' ' . $attendance->checkin_time)->format('h:i a'),
                            'note' => $action->note,
                            'remote_mode' => $action->remote_mode ?: null
                        ]);
                    }
                    if ($action->action == Actions::CHECKOUT) {
                        $checkout_data = collect([
                            'status' => $this->getStatusBasedOnLeaveAction($action, $is_weekend_or_holiday, $is_on_leave, $is_on_half_day_leave),
                            'is_remote' => $action->is_remote ?: 0,
                            'address' => $action->is_remote ?
                                $action->location ? json_decode($action->location)->address : null
                                : null,
                            'checkout_time' => $attendance->checkout_time ? Carbon::parse($attendance->date . ' ' . $attendance->checkout_time)->format('h:i a') : null,
                            'note' => $action->note,
                            'remote_mode' => $action->remote_mode ?: null
                        ]);
                    }
                }

                array_push($data, $this->getBusinessMemberData($attendance->businessMember) + [
                        'check_in' => $checkin_data,
                        'check_out' => $checkout_data,
                        'is_absent' => $attendance->status == Statuses::ABSENT ? 1 : 0,
                        'is_on_leave' => $is_on_leave ? 1 : 0,
                        'is_holiday' => $is_weekend_or_holiday ? 1 : 0,
                        'weekend_or_holiday' => $is_weekend_or_holiday ? $this->isWeekendOrHoliday() : null,
                        'is_half_day_leave' => $is_on_half_day_leave,
                        'which_half_day_leave' => $which_half_day
                    ]);
            }
        }

        foreach ($business_members_in_leave as $index => $business_member_in_leave) {
            if (in_array($business_member_in_leave['business_member_id'], $this->usersWhoGiveAttendance)) {
                unset($business_members_in_leave[$index]);
            }
        }

        $present_and_on_leave_business_members = array_merge($data, $business_members_in_leave);
        if ($this->statusFilter == self::ABSENT || $this->statusFilter == self::ALL) {
            $business_members_in_absence = $this->getBusinessMemberWhoAreAbsence($present_and_on_leave_business_members);
            if ($this->statusFilter == self::ABSENT) $present_and_on_leave_business_members = [];
            if ($this->statusFilter == self::ABSENT && $is_weekend_or_holiday) {
                $present_and_on_leave_business_members = [];
                $business_members_in_absence = [];
            }
        } else {
            $business_members_in_absence = [];
        }

        if ($this->statusFilter == self::ON_LEAVE) {
            $present_and_on_leave_business_members = array_filter($present_and_on_leave_business_members, function ($item) {
                return $item['is_on_leave'];
            });
        }

        return array_merge($present_and_on_leave_business_members, $business_members_in_absence);
    }

    /**
     * @param $present_and_on_leave_business_members
     * @return array
     */
    private function getBusinessMemberWhoAreAbsence($present_and_on_leave_business_members)
    {
        $is_weekend_or_holiday = $this->isWeekendOrHoliday;
        $business_member_ids = [];
        $present_and_on_leave_business_member_ids = array_map(function ($business_member) use ($business_member_ids) {
            return $business_member_ids[] = $business_member['business_member_id'];
        }, $present_and_on_leave_business_members);

        $business_member_ids_who_give_attendance = $this->attendances->pluck('business_member_id')->toArray();

        $present_and_on_leave_business_member_ids = array_unique(array_merge($present_and_on_leave_business_member_ids, $business_member_ids_who_give_attendance));
        $absent_members = array_diff($this->teamBusinessMemberIds, $present_and_on_leave_business_member_ids);

        $business_members = $this->businessMemberRepository->builder()->select('id', 'member_id', 'business_role_id')
            ->with([
                'member' => function ($q) {
                    $q->select('id', 'profile_id')
                        ->with([
                            'profile' => function ($q) {
                                $q->select('id', 'name', 'pro_pic');
                            }]);
                },
                'role' => function ($q) {
                    $q->select('business_roles.id', 'business_department_id', 'name');
                }
            ])
            ->where('business_id', $this->business->id)
            ->active()
            ->whereIn('id', $absent_members);

        $business_members = $business_members->get();

        $data = [];
        foreach ($business_members as $business_member) {
            $data[] = $this->getBusinessMemberData($business_member) + [
                'check_in' => null,
                'check_out' => null,
                'is_absent' => $is_weekend_or_holiday ? 0 : 1,
                'is_on_leave' => 0,
                'is_holiday' => $is_weekend_or_holiday ? 1 : 0,
                'weekend_or_holiday' => $is_weekend_or_holiday ? $this->isWeekendOrHoliday() : null,
                'is_half_day_leave' => 0,
                'which_half_day_leave' => null,
            ];
        }

        return $data;
    }

    /**
     * @return array
     */
    private function getBusinessMemberWhoAreOnLeave()
    {
        $leaves = $this->leaveRepositoryInterface->builder()
            ->select('id', 'business_member_id', 'end_date', 'status', 'is_half_day', 'half_day_configuration')
            ->whereIn('business_member_id', $this->teamBusinessMemberIds)
            ->accepted()
            ->where('start_date', '<=', $this->startDate->toDateString())->where('end_date', '>=', $this->endDate->toDateString())
            ->with(['businessMember' => function ($q) {
                $q->select('id', 'member_id', 'business_role_id')
                    ->with([
                        'member' => function ($q) {
                            $q->select('id', 'profile_id')
                                ->with([
                                    'profile' => function ($q) {
                                        $q->select('id', 'name', 'pro_pic');
                                    }]);
                        },
                        'role' => function ($q) {
                            $q->select('business_roles.id', 'business_department_id', 'name');
                        }
                    ]);
            }]);

        $leaves = $leaves->get();

        $data = [];
        foreach ($leaves as $leave) {
            array_push($this->usersWhoOnLeave, $leave->businessMember->id);
            $this->usersLeaveIds[$leave->businessMember->id] = [
                'member_id' => $leave->businessMember->member->id,
                'business_member_id' => $leave->businessMember->id,
                'leave' => [
                    'id' => $leave->id,
                    'is_half_day_leave' => (int)$leave->is_half_day,
                    'which_half_day' => $leave->is_half_day ? $leave->half_day_configuration : null
                ]
            ];
            if (!($this->statusFilter == self::ON_LEAVE || $this->statusFilter == self::ABSENT || $this->statusFilter == self::ALL)) continue;

            if (in_array($this->statusFilter, $this->statuses)) continue;
            array_push($data, $this->getBusinessMemberData($leave->businessMember) + [
                    'check_in' => null,
                    'check_out' => null,
                    'is_absent' => 0,
                    'is_on_leave' => 1,
                    'is_holiday' => 0,
                    'weekend_or_holiday' => null,
                    'is_half_day_leave' => $leave->is_half_day ? 1 : 0,
                    'which_half_day_leave' => $leave->is_half_day ? $leave->half_day_configuration : null
                ]);
        }

        return $data;
    }

    /**
     * @param BusinessMember $business_member
     * @return array
     */
    private function getBusinessMemberData(BusinessMember $business_member)
    {
        return [
            'business_member_id' => $business_member->id,
            'profile' => [
                'name' => $business_member->member->profile->name,
                'pro_pic' => $business_member->member->profile->pro_pic,
                'designation' => $business_member->role ? $business_member->role->name : null
            ]
        ];
    }

    /**
     * @param $action
     * @param $is_weekend_or_holiday
     * @param $is_on_leave
     * @param $is_on_half_day_leave
     * @return mixed|null
     */
    private function getStatusBasedOnLeaveAction($action, $is_weekend_or_holiday, $is_on_leave, $is_on_half_day_leave)
    {
        if ($is_on_half_day_leave) return $action->status;
        if ($is_weekend_or_holiday || $is_on_leave) return null;

        return $action->status;
    }

    /**
     * @param $business_member_id
     * @return bool
     */
    private function isOnLeave($business_member_id)
    {
        return in_array($business_member_id, $this->usersWhoOnLeave);
    }

    /**
     * @return string
     */
    private function isWeekendOrHoliday()
    {
        $weekend_settings = $this->businessWeekendSettingsRepo->getAllByBusiness($this->business);
        $weekend_day = $this->checkWeekend->getWeekendDays($this->startDate, $weekend_settings);

        return $this->isWeekend($this->startDate, $weekend_day) ? 'weekend' : 'holiday';
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

}
