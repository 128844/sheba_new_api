<?php namespace Sheba\Business\Attendance;

use App\Models\Business;
use App\Models\BusinessDepartment;
use App\Models\BusinessMember;
use App\Models\BusinessRole;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Sheba\Business\MyTeamDashboard\CommonFunctions;
use Sheba\Dal\Attendance\Contract as AttendanceRepositoryInterface;
use Sheba\Dal\AttendanceActionLog\Actions;
use Sheba\Dal\AttendanceActionLog\RemoteMode;
use Sheba\Dal\BusinessHoliday\Contract as BusinessHolidayRepoInterface;
use Sheba\Dal\BusinessOffice\Contract as BusinessOffice;
use Sheba\Dal\Leave\Contract as LeaveRepositoryInterface;
use Sheba\Dal\Attendance\Model;
use Sheba\Dal\Attendance\Statuses;
use Sheba\Dal\ShiftAssignment\ShiftAssignment;
use Sheba\Dal\ShiftAssignment\ShiftAssignmentRepository;
use Sheba\Helpers\TimeFrame;
use Sheba\Repositories\Interfaces\BusinessMemberRepositoryInterface;

class AttendanceList
{
    const ALL = 'all';
    const PRESENT = 'present';
    const ABSENT = 'absent';
    const ON_LEAVE = 'on_leave';
    const CHECKIN_TIME = 'checkin_time';
    const CHECKOUT_TIME = 'checkout_time';
    const STAYING_TIME = 'staying_time';

    /** @var Business */
    private $business;
    /** @var Carbon */
    private $startDate;
    /** @var Carbon */
    private $endDate;
    /** @var Model[] */
    private $attendances;
    /** @var AttendanceRepositoryInterface $attendanceRepositoryInterface */
    private $attendanceRepositoryInterface;
    /** @var LeaveRepositoryInterface $leaveRepositoryInterface */
    private $leaveRepositoryInterface;
    /** @var BusinessMemberRepositoryInterface */
    private $businessMemberRepository;
    private $businessDepartmentId;
    private $sort;
    private $sortColumn;
    private $search;
    private $checkinStatus;
    private $checkoutStatus;
    private $statusFilter;
    private $businessMemberId;
    private $usersWhoGiveAttendance;
    private $usersWhoOnLeave;
    private $usersLeaveIds;
    /** @var Collection $departments */
    private $departments;
    /** @var BusinessHolidayRepoInterface $businessHoliday */
    private $businessHoliday;
    private $checkoutLocation;
    private $checkinLocation;
    private $checkinOfficeOrRemote;
    private $checkoutOfficeOrRemote;
    private $checkInRemoteMode;
    private $checkOutRemoteMode;
    /*** @var BusinessOffice */
    private $businessOfficeRepo;
    /*** @var CommonFunctions */
    private $commonFunctions;

    /** @var array */
    private $officeNameMemo = [];
    private $specificDayWiseShift;
    /*** @var ShiftAssignmentRepository */
    private $shiftAssignmentRepo;

    /**
     * AttendanceList constructor.
     *
     * @param AttendanceRepositoryInterface $attendance_repository_interface
     * @param BusinessMemberRepositoryInterface $business_member_repository
     * @param LeaveRepositoryInterface $leave_repository_interface
     * @param BusinessHolidayRepoInterface $business_holiday_repo
     * @param CommonFunctions $common_functions
     */
    public function __construct(
        AttendanceRepositoryInterface          $attendance_repository_interface,
        BusinessMemberRepositoryInterface      $business_member_repository,
        LeaveRepositoryInterface               $leave_repository_interface,
        BusinessHolidayRepoInterface           $business_holiday_repo,
        CommonFunctions                        $common_functions,
        ShiftAssignmentRepository              $shift_assignment_repo
    )
    {
        $this->attendanceRepositoryInterface = $attendance_repository_interface;
        $this->businessMemberRepository = $business_member_repository;
        $this->leaveRepositoryInterface = $leave_repository_interface;
        $this->departments = collect();
        $this->usersWhoGiveAttendance = [];
        $this->usersWhoOnLeave = [];
        $this->usersLeaveIds = [];
        $this->businessHoliday = $business_holiday_repo;
        $this->businessOfficeRepo = app(BusinessOffice::class);
        $this->commonFunctions = $common_functions;
        $this->shiftAssignmentRepo = $shift_assignment_repo;
    }

    /**
     * @param Business $business
     * @return AttendanceList
     */
    public function setBusiness(Business $business)
    {
        $this->business = $business;
        $this->commonFunctions->setBusiness($this->business);
        return $this;
    }

    /**
     * @param TimeFrame $selected_date
     * @return AttendanceList
     */
    public function setSelectedDate(TimeFrame $selected_date)
    {
        $this->startDate = $selected_date->start;
        $this->endDate = $selected_date->end;
        $this->commonFunctions->setSelectedDate($selected_date);
        return $this;
    }

    /**
     * @param $businessDepartmentId
     * @return AttendanceList
     */
    public function setBusinessDepartment($businessDepartmentId)
    {
        $this->businessDepartmentId = $businessDepartmentId;
        return $this;
    }

    /**
     * @param $sort
     * @return $this
     */
    public function setSortKey($sort)
    {
        $this->sort = $sort;
        return $this;
    }

    /**
     * @param $column
     * @return $this
     */
    public function setSortColumn($column)
    {
        $this->sortColumn = $column;
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
     * @param $search
     * @return $this
     */
    public function setSearch($search)
    {
        $this->search = $search;
        return $this;
    }

    /**
     * @param $checkin_status
     * @return $this
     */
    public function setCheckinStatus($checkin_status)
    {
        $this->checkinStatus = $checkin_status;
        return $this;
    }

    /**
     * @param $checkout_status
     * @return $this
     */
    public function setCheckoutStatus($checkout_status)
    {
        $this->checkoutStatus = $checkout_status;
        return $this;
    }

    /**
     * @param $checkin_office_or_remote
     * @return $this
     */
    public function setOfficeOrRemoteCheckin($checkin_office_or_remote)
    {
        $this->checkinOfficeOrRemote = $checkin_office_or_remote;
        return $this;
    }

    /**
     * @param $checkout_office_or_remote
     * @return $this
     */
    public function setOfficeOrRemoteCheckout($checkout_office_or_remote)
    {
        $this->checkoutOfficeOrRemote = $checkout_office_or_remote;
        return $this;
    }

    /**
     * @param $checkin_location
     * @return $this
     */
    public function setCheckinLocation($checkin_location)
    {
        $this->checkinLocation = $checkin_location;
        return $this;
    }

    /**
     * @param $checkout_location
     * @return $this
     */
    public function setCheckoutLocation($checkout_location)
    {
        $this->checkoutLocation = $checkout_location;
        return $this;
    }

    /**
     * @param $checkin_remote_mode
     * @return $this
     */
    public function setCheckInRemoteMode($checkin_remote_mode)
    {
        $this->checkInRemoteMode = $checkin_remote_mode;
        return $this;
    }

    /**
     * @param $checkout_remote_mode
     * @return $this
     */
    public function setCheckOutRemoteMode($checkout_remote_mode)
    {
        $this->checkOutRemoteMode = $checkout_remote_mode;
        return $this;
    }

    /**
     * @return array
     */
    public function get()
    {
        $this->runAttendanceQueryV2();
        return $this->getDataV2();
    }

    private function withMembers($query)
    {
        return $query->select('id', 'member_id', 'business_role_id', 'employee_id')
            ->with([
                'member' => function ($q) {
                    $q->select('id', 'profile_id')
                        ->with([
                            'profile' => function ($q) {
                                $q->select('id', 'name', 'pro_pic');
                            }
                        ]);
                },
                'role'
            ]);
    }

    private function runAttendanceQueryV2()
    {
        $business_member_ids = [];
        if ($this->businessMemberId) $business_member_ids = [$this->businessMemberId];
        elseif ($this->business) $business_member_ids = $this->getBusinessMemberIds();

        $this->specificDayWiseShift = $this->loadShifts($business_member_ids);

        $attendances = $this->attendanceRepositoryInterface->builder()
            ->select('id', 'business_member_id', 'checkin_time', 'checkout_time', 'staying_time_in_minutes', 'overtime_in_minutes', 'status', 'date', 'is_attendance_reconciled', 'shift_assignment_id')
            ->whereIn('business_member_id', $business_member_ids)
            ->where('date', '>=', $this->startDate->toDateString())
            ->where('date', '<=', $this->endDate->toDateString())
            ->with([
                'actions' => function ($q) {
                    $q->select('id', 'attendance_id', 'note', 'action', 'status', 'ip', 'is_remote', 'remote_mode', 'is_in_wifi', 'is_geo_location', 'business_office_id', 'location', 'created_at');
                },
                'businessMember' => function ($q) {
                    $this->withMembers($q);
                },
                'shiftAssignment' => function ($q) {
                    $q->select('id', 'shift_id', 'shift_title', 'color_code', 'is_general', 'is_unassigned', 'is_shift');
                },
                'overrideLogs'
            ]);

        if ($this->businessDepartmentId) {
            $role_ids = $this->getBusinessRoleIds();
            $attendances = $attendances->whereHas('businessMember', function ($q) use ($role_ids) {
                $q->whereIn('business_role_id', $role_ids);
            });
        }

        if ($this->checkinStatus) {
            $attendances = $attendances->whereHas('actions', function ($q) {
                $q->where('status', $this->checkinStatus);
            });
        }

        if ($this->checkoutStatus) {
            $attendances = $attendances->whereHas('actions', function ($q) {
                $q->where('status', $this->checkoutStatus);
            });
        }

        if ($this->checkinOfficeOrRemote) {
            $attendances = $attendances->whereHas('actions', function ($q) {
                $q->where([['is_remote', $this->checkinOfficeOrRemote == 'remote' ? 1 : 0], ['action', Actions::CHECKIN]]);
            });
        }

        if ($this->checkoutOfficeOrRemote) {
            $attendances = $attendances->whereHas('actions', function ($q) {
                $q->where([['is_remote', $this->checkoutOfficeOrRemote == 'remote' ? 1 : 0], ['action', Actions::CHECKOUT]]);
            });
        }

        if ($this->checkinLocation) {
            $attendances = $attendances->whereHas('actions', function ($q) {
                $q->where([['ip', $this->checkinLocation], ['action', Actions::CHECKIN]]);
            });
        }

        if ($this->checkoutLocation) {
            $attendances = $attendances->whereHas('actions', function ($q) {
                $q->where([['ip', $this->checkoutLocation], ['action', Actions::CHECKOUT]]);
            });
        }

        if ($this->checkInRemoteMode && $this->checkinOfficeOrRemote == 'remote') {
            if ($this->checkInRemoteMode === RemoteMode::HOME) {
                $attendances = $attendances->whereHas('actions', function ($q) {
                    $q->where('action', Actions::CHECKIN);
                    $q->where('remote_mode', RemoteMode::HOME)->orWhereNull('remote_mode');
                });
            }
            if ($this->checkInRemoteMode === RemoteMode::FIELD) {
                $attendances = $attendances->whereHas('actions', function ($q) {
                    $q->where([['remote_mode', RemoteMode::FIELD], ['action', Actions::CHECKIN]]);
                });
            }
        }

        if ($this->checkOutRemoteMode && $this->checkoutOfficeOrRemote == 'remote') {
            if ($this->checkOutRemoteMode === RemoteMode::HOME) {
                $attendances = $attendances->whereHas('actions', function ($q) {
                    $q->where('action', Actions::CHECKOUT);
                    $q->where('remote_mode', RemoteMode::HOME)->orWhereNull('remote_mode');
                });
            }
            if ($this->checkOutRemoteMode === RemoteMode::FIELD) {
                $attendances = $attendances->whereHas('actions', function ($q) {
                    $q->where([['remote_mode', RemoteMode::FIELD], ['action', Actions::CHECKOUT]]);
                });
            }
        }

        if ($this->sort && $this->sortColumn) {
            $sort_by = $this->sort === 'asc' ? 'ASC' : 'DESC';
            if ($this->sortColumn == self::CHECKIN_TIME) {
                $attendances = $attendances->orderByRaw("UNIX_TIMESTAMP(checkin_time) $sort_by");
            }
            if ($this->sortColumn == self::CHECKOUT_TIME) {
                $attendances = $attendances->orderByRaw("UNIX_TIMESTAMP(checkout_time) $sort_by");
            }
            if ($this->sortColumn == self::STAYING_TIME) {
                $attendances = $attendances->orderByRaw("staying_time_in_minutes $sort_by");
            }
        } else {
            $attendances = $attendances->orderByRaw('id desc');
        }

        $this->attendances = $attendances->get();
    }

    private function getBusinessMemberIds()
    {
        return $this->businessMemberRepository->where('business_id', $this->business->id)->pluck('id')->toArray();
    }

    /**
     * @return array|null
     */
    private function getBusinessRoleIds()
    {
        /** @var Collection $role_ids */
        $role_ids = BusinessRole::select('id', 'business_department_id')->whereHas('businessDepartment', function ($q) {
            $q->where([['business_id', $this->business->id], ['business_departments.id', $this->businessDepartmentId]]);
        })->get();
        return count($role_ids) > 0 ? $role_ids->pluck('id')->toArray() : [];
    }

    /**
     * @return array|Collection
     */
    private function getDataV2()
    {
        $data = [];
        $this->setDepartments();

        $is_weekend_or_holiday = $this->commonFunctions->isWeekendHoliday();
        $business_members_in_leave = $this->getBusinessMemberWhoAreOnLeave();

        if ($this->statusFilter != self::ABSENT) {
            foreach ($this->attendances as $attendance) {
                $checkin_data = $checkout_data = null;
                $check_in_overridden = $check_out_overridden = 0;
                $is_on_leave = $this->isOnLeave($attendance->businessMember->member->id);
                $is_on_half_day_leave = 0;
                $which_half_day = null;
                $leave_type = null;
                if ($is_on_leave) {
                    $leave = $this->usersLeaveIds[$attendance->businessMember->member->id];
                    $leave_type = $leave['leave']['type'];
                    $is_on_half_day_leave = $leave['leave']['is_half_day_leave'];
                    $which_half_day = $leave['leave']['which_half_day'];
                }

                if ($this->statusFilter == self::ON_LEAVE && !$is_on_half_day_leave) continue;
                $this->usersWhoGiveAttendance[] = $attendance->businessMember->member->id;

                if (
                    !$is_on_half_day_leave &&
                    $is_on_leave && (!!$this->checkinStatus || !!$this->checkoutStatus)
                ) continue;

                foreach ($attendance->actions as $action) {
                    $business_office_name = $this->getOfficeName($action);
                    if ($action->action == Actions::CHECKIN) {
                        $checkin_data = collect([
                            'status' => $this->getStatusBasedOnLeaveAction($action, $is_weekend_or_holiday, $is_on_leave, $is_on_half_day_leave),
                            'is_remote' => $action->is_remote ?: 0,
                            'is_geo' => $action->is_geo_location,
                            'is_in_wifi' => $action->is_in_wifi,
                            'address' => $action->is_remote ?
                                $action->location ?
                                    json_decode($action->location)->address ?: json_decode($action->location)->lat . ', ' . json_decode($action->location)->lng
                                    : null
                                : $business_office_name,
                            'checkin_time' => Carbon::parse($attendance->date . ' ' . $attendance->checkin_time)->format('g:i a'),
                            'note' => $action->note,
                            'remote_mode' => $action->remote_mode ?: null
                        ]);
                    }
                    if ($action->action == Actions::CHECKOUT) {
                        $checkout_data = collect([
                            'status' => $this->getStatusBasedOnLeaveAction($action, $is_weekend_or_holiday, $is_on_leave, $is_on_half_day_leave),
                            'is_remote' => $action->is_remote ?: 0,
                            'is_geo' => $action->is_geo_location,
                            'is_in_wifi' => $action->is_in_wifi,
                            'address' => $action->is_remote ?
                                $action->location ?
                                    json_decode($action->location)->address ?: json_decode($action->location)->lat . ', ' . json_decode($action->location)->lng
                                    : null
                                : $business_office_name,
                            'checkout_time' => $attendance->checkout_time ? Carbon::parse($attendance->date . ' ' . $attendance->checkout_time)->format('g:i a') : null,
                            'note' => $action->note,
                            'remote_mode' => $action->remote_mode ?: null
                        ]);
                    }
                }

                if ($attendance->overrideLogs) {
                    foreach ($attendance->overrideLogs as $override_log) {
                        if ($override_log->action == Actions::CHECKIN) $check_in_overridden = 1;
                        if ($override_log->action == Actions::CHECKOUT) $check_out_overridden = 1;
                    }
                }

                $data[] = $this->getBusinessMemberData($attendance->businessMember) + [
                    'id' => $attendance->id,
                    'check_in' => $checkin_data,
                    'check_out' => $checkout_data,
                    'active_hours' => $attendance->staying_time_in_minutes ? formatMinuteToHourMinuteString((int)$attendance->staying_time_in_minutes) : null,
                    'overtime_in_minutes' => (int)$attendance->overtime_in_minutes ?: 0,
                    'overtime' => (int)$attendance->overtime_in_minutes ? formatMinuteToHourMinuteString((int)$attendance->overtime_in_minutes) : null,
                    'date' => $attendance->date,
                    'is_absent' => $attendance->status == Statuses::ABSENT ? 1 : 0,
                    'is_on_leave' => $is_on_leave ? 1 : 0,
                    'is_holiday' => $is_weekend_or_holiday ? 1 : 0,
                    'weekend_or_holiday' => $is_weekend_or_holiday ? $this->commonFunctions->getWeekendOrHolidayString() : null,
                    'is_half_day_leave' => $is_on_half_day_leave,
                    'is_attendance_reconciled' => $attendance->is_attendance_reconciled,
                    'which_half_day_leave' => $which_half_day,
                    'leave_type' => $is_on_leave ? $leave_type : null,
                    'holiday_name' => $is_weekend_or_holiday ? $this->getHolidayName() : null,
                    'override' => [
                        'is_check_in_overridden' => $check_in_overridden,
                        'is_check_out_overridden' => $check_out_overridden
                    ],
                    'shift' => AttendanceShiftFormatter::get($attendance)
                ];
            }
        }

        foreach ($business_members_in_leave as $index => $business_member_in_leave) {
            if (in_array($business_member_in_leave['member']['id'], $this->usersWhoGiveAttendance)) {
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

        $final_data = array_merge($present_and_on_leave_business_members, $business_members_in_absence);

        if ($this->search) $final_data = collect($this->searchWithEmployeeName($final_data))->values();

        return $final_data;
    }

    /**
     * @param $present_and_on_leave_business_members
     * @return array
     */
    private function getBusinessMemberWhoAreAbsence($present_and_on_leave_business_members)
    {
        $is_weekend_or_holiday = $this->commonFunctions->isWeekendHoliday();
        $business_member_ids = [];
        $present_and_on_leave_business_member_ids = array_map(function ($business_member) use ($business_member_ids) {
            return $business_member_ids[] = $business_member['business_member_id'];
        }, $present_and_on_leave_business_members);

        $business_member_ids_who_give_attendance = $this->attendances->pluck('business_member_id')->toArray();
        $present_and_on_leave_business_member_ids = array_merge($present_and_on_leave_business_member_ids, $business_member_ids_who_give_attendance);

        $business_members = $this->businessMemberRepository->builder()->select('id', 'member_id', 'business_role_id', 'employee_id')
            ->with([
                'member' => function ($q) {
                    $q->select('id', 'profile_id')
                        ->with([
                            'profile' => function ($q) {
                                $q->select('id', 'name');
                            }]);
                },
                'role' => function ($q) {
                    $q->select('business_roles.id', 'business_department_id', 'name')->with([
                        'businessDepartment' => function ($q) {
                            $q->select('business_departments.id', 'business_id', 'name');
                        }
                    ]);
                }
            ])
            ->where('business_id', $this->business->id)
            ->active()
            ->whereNotIn('id', $present_and_on_leave_business_member_ids);

        if ($this->businessDepartmentId) {
            $business_members = $business_members->whereHas('role', function ($q) {
                $q->whereHas('businessDepartment', function ($q) {
                    $q->where('business_departments.id', $this->businessDepartmentId);
                });
            });
        }

        $business_members = $business_members->get();

        $data = [];
        foreach ($business_members as $business_member) {
            $business_member_id = $business_member->id;
            $shift_assignment = $this->specificDayWiseShift->has($business_member_id) ? $this->specificDayWiseShift[$business_member_id] : null;
            $shift = AttendanceShiftFormatter::getByShiftAssignment($shift_assignment);
            if ($shift['is_unassigned']) continue;
            $data[] = $this->getBusinessMemberData($business_member) + [
                'id' => $business_member_id,
                'check_in' => null,
                'check_out' => null,
                'overtime_in_minutes' => 0,
                'overtime' => null,
                'active_hours' => null,
                'is_absent' => $is_weekend_or_holiday ? 0 : 1,
                'is_on_leave' => 0,
                'is_holiday' => $is_weekend_or_holiday ? 1 : 0,
                'weekend_or_holiday' => $is_weekend_or_holiday ? $this->commonFunctions->getWeekendOrHolidayString() : null,
                'holiday_name' => $is_weekend_or_holiday ? $this->getHolidayName() : null,
                'is_half_day_leave' => 0,
                'which_half_day_leave' => null,
                'date' => null,
                'shift' => $shift
            ];
        }

        return $data;
    }

    private function getBusinessMemberWhoAreOnLeave()
    {
        $business_member_ids = [];
        if ($this->businessMemberId) $business_member_ids = [$this->businessMemberId];
        elseif ($this->business) $business_member_ids = $this->getBusinessMemberIds();

        $leaves = $this->leaveRepositoryInterface->builder()
            ->select('id', 'business_member_id', 'leave_type_id', 'end_date', 'status', 'is_half_day', 'half_day_configuration')
            ->whereIn('business_member_id', $business_member_ids)
            ->accepted()
            ->where('start_date', '<=', $this->startDate->toDateString())->where('end_date', '>=', $this->endDate->toDateString())
            ->with(['businessMember' => function ($q) {
                $q->select('id', 'member_id', 'business_role_id', 'employee_id')
                    ->with([
                        'member' => function ($q) {
                            $q->select('id', 'profile_id')
                                ->with([
                                    'profile' => function ($q) {
                                        $q->select('id', 'name', 'pro_pic');
                                    }]);
                        },
                        'role' => function ($q) {
                            $q->select('business_roles.id', 'business_department_id', 'name')->with([
                                'businessDepartment' => function ($q) {
                                    $q->select('business_departments.id', 'business_id', 'name');
                                }
                            ]);
                        }
                    ]);
            }, 'leaveType' => function ($query) {
                $query->withTrashed()->select('id', 'business_id', 'title');
            }]);

        if ($this->businessDepartmentId) {
            $leaves = $leaves->whereHas('businessMember', function ($q) {
                $q->whereHas('role', function ($q) {
                    $q->whereHas('businessDepartment', function ($q) {
                        $q->where('business_departments.id', $this->businessDepartmentId);
                    });
                });
            });
        }
        $leaves = $leaves->get();

        $data = [];
        foreach ($leaves as $leave) {
            $this->usersWhoOnLeave[] = $leave->businessMember->member->id;
            $this->usersLeaveIds[$leave->businessMember->member->id] = [
                'member_id' => $leave->businessMember->member->id,
                'business_member_id' => $leave->businessMember->id,
                'leave' => [
                    'id' => $leave->id,
                    'type' => $leave->leaveType->title,
                    'is_half_day_leave' => (int)$leave->is_half_day,
                    'which_half_day' => $leave->is_half_day ? $leave->half_day_configuration : null
                ]
            ];
            if (!($this->statusFilter == self::ON_LEAVE || $this->statusFilter == self::ABSENT || $this->statusFilter == self::ALL)) continue;
            if (!!$this->checkinStatus || !!$this->checkoutStatus) continue;
            $data[] = $this->getBusinessMemberData($leave->businessMember) + [
                'id' => $leave->id,
                'check_in' => null,
                'check_out' => null,
                'active_hours' => null,
                'overtime_in_minutes' => 0,
                'overtime' => null,
                'date' => null,
                'is_absent' => 0,
                'is_on_leave' => 1,
                'is_holiday' => 0,
                'leave_type' => $leave->leaveType->title,
                'weekend_or_holiday' => null,
                'is_half_day_leave' => $leave->is_half_day ? 1 : 0,
                'which_half_day_leave' => $leave->is_half_day ? $leave->half_day_configuration : null
            ];
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
            'employee_id' => $business_member->employee_id ?: 'N/A',
            'business_member_id' => $business_member->id,
            'member' => [
                'id' => $business_member->member->id,
                'name' => $business_member->member->profile->name,
                'pro_pic' => $business_member->member->profile->pro_pic
            ],
            'department' => $business_member->role ? [
                'id' => $business_member->role->business_department_id,
                'name' => $this->departments->where('id', $business_member->role->business_department_id)->first() ?
                    $this->departments->where('id', $business_member->role->business_department_id)->first()->name :
                    'n/s'
            ] : null
        ];
    }

    /**
     * @param $final_data
     * @return array
     */
    private function searchWithEmployeeName($final_data)
    {
        return array_where($final_data, function ($key, $value) {
            return str_contains(strtoupper($value['member']['name']), strtoupper($this->search));
        });
    }

    private function setDepartments()
    {
        $this->departments = BusinessDepartment::where('business_id', $this->business->id)->select('id', 'name')->get();
        return $this;
    }

    /**
     * @param $action
     * @param $is_weekend_or_holiday
     * @param $is_on_leave
     * @param $is_on_half_day_leave
     * @return null
     */
    private function getStatusBasedOnLeaveAction($action, $is_weekend_or_holiday, $is_on_leave, $is_on_half_day_leave)
    {
        if ($is_weekend_or_holiday) return null;
        if ($is_on_half_day_leave) return $action->status;
        if ($is_on_leave) return null;

        return $action->status;
    }

    /**
     * @param $member_id
     * @return bool
     */
    private function isOnLeave($member_id)
    {
        return in_array($member_id, $this->usersWhoOnLeave);
    }

    /**
     * @return null
     */
    private function getHolidayName()
    {
        $business_holiday = $this->businessHoliday->getAllByBusiness($this->business);
        $holiday_name = null;
        foreach ($business_holiday as $holiday) {
            if (!$this->startDate->between($holiday->start_date, $holiday->end_date)) continue;
            $holiday_name = $holiday->title;
            break;
        }
        return $holiday_name;
    }

    private function getOfficeName($action)
    {
        if (array_key_exists($action->business_office_id, $this->officeNameMemo)) {
            return $this->officeNameMemo[$action->business_office_id];
        }

        $is_in_wifi = $action->is_in_wifi;
        $is_geo = $action->is_geo_location;
        $business_office = $is_in_wifi || $is_geo ? $this->businessOfficeRepo->findWithTrashed($action->business_office_id) : null;
        $this->officeNameMemo[$action->business_office_id] = $business_office ? $business_office->name : null;
        return $this->officeNameMemo[$action->business_office_id];
    }

    private function loadShifts($business_member_ids)
    {
        return $this->shiftAssignmentRepo->where('date', $this->startDate)->whereIn('business_member_id', $business_member_ids)->get()->toAssocFromKey(function (ShiftAssignment $assignment) {
            return $assignment->business_member_id;
        });
    }

}
