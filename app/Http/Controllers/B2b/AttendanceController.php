<?php namespace App\Http\Controllers\B2b;

use App\Http\Controllers\Controller;
use App\Jobs\Business\SendMonthlyAttendanceReportEmail;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Sheba\Business\Attendance\Filter;
use App\Sheba\Business\Attendance\MonthlyStat;
use App\Sheba\Business\Attendance\Setting\GeoLocationCreator;
use App\Sheba\Business\Attendance\Setting\GeoLocationDeleter;
use App\Sheba\Business\Attendance\Setting\GeoLocationUpdater;
use App\Sheba\Business\Attendance\Setting\OfficeLocationFormatter;
use App\Sheba\Business\BusinessBasicInformation;
use App\Sheba\Business\OfficeSetting\PolicyRuleRequester;
use App\Sheba\Business\OfficeSetting\PolicyRuleUpdater;
use App\Sheba\Business\OfficeSetting\PolicyTransformer;
use App\Sheba\Business\OfficeSettingChangesLogs\ChangesLogsTransformer;
use App\Sheba\Business\OfficeSettingChangesLogs\Creator;
use App\Sheba\Business\OfficeSettingChangesLogs\Requester;
use App\Transformers\Business\AttendanceMonthlyListTransformer;
use App\Transformers\CustomSerializer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Serializer\ArraySerializer;
use Sheba\Business\Attendance\AttendanceList;
use Sheba\Business\Attendance\Daily\DailyExcel;
use Sheba\Business\Attendance\Detail\DetailsExcel as DetailsExcel;
use Sheba\Business\Attendance\HalfDaySetting\Updater as HalfDaySettingUpdater;
use Sheba\Business\Attendance\Monthly\Excel;
use Sheba\Business\Attendance\Monthly\MonthlyAttendanceCalculator;
use Sheba\Business\Attendance\Setting\ActionType;
use Sheba\Business\Attendance\Setting\AttendanceSettingTransformer;
use Sheba\Business\Attendance\Setting\Creator as SettingCreator;
use Sheba\Business\Attendance\Setting\Deleter as SettingDeleter;
use Sheba\Business\Attendance\Setting\Updater as SettingUpdater;
use Sheba\Business\Attendance\Type\Updater as TypeUpdater;
use Sheba\Business\CoWorker\Filter\CoWorkerInfoFilter;
use Sheba\Business\Holiday\CreateRequest as HolidayCreatorRequest;
use Sheba\Business\Holiday\Creator as HolidayCreator;
use Sheba\Business\Holiday\HolidayList;
use Sheba\Business\Holiday\Updater as HolidayUpdater;
use Sheba\Business\OfficeSetting\AttendaceSettingUpdater;
use Sheba\Business\OfficeSetting\OperationalSetting;
use Sheba\Business\OfficeTiming\Updater as OfficeTimingUpdater;
use Sheba\Dal\Attendance\Contract as AttendanceRepoInterface;
use Sheba\Dal\Attendance\Statuses;
use Sheba\Dal\BusinessHoliday\Contract as BusinessHolidayRepoInterface;
use Sheba\Dal\BusinessOffice\Contract as BusinessOfficeRepoInterface;
use Sheba\Dal\BusinessOfficeHours\Contract as BusinessOfficeHoursRepoInterface;
use Sheba\Dal\BusinessWeekend\Contract as BusinessWeekendRepoInterface;
use Sheba\Dal\BusinessWeekendSettings\BusinessWeekendSettingsRepo;
use Sheba\Dal\OfficePolicy\Type;
use Sheba\Dal\OfficeSettingChangesLogs\OfficeSettingChangesLogsRepository;
use Sheba\Helpers\TimeFrame;
use Sheba\ModificationFields;
use Sheba\Repositories\Interfaces\BusinessMemberRepositoryInterface;

class AttendanceController extends Controller
{
    use ModificationFields, BusinessBasicInformation;

    const FIRST_DAY_OF_MONTH = 1;

    /** @var BusinessHolidayRepoInterface $holidayRepository */
    private $holidayRepository;
    /*** @var OfficeSettingChangesLogsRepository $officeSettingChangesLogsRepo */
    private $officeSettingChangesLogsRepo;
    /*** @var Requester $officeSettingChangesLogsRequester */
    private $officeSettingChangesLogsRequester;
    /*** @var Creator $officeSettingChangesLogsCreator */
    private $officeSettingChangesLogsCreator;
    private $businessWeekendRepo;
    /*** @var BusinessOfficeRepoInterface $businessOfficeRepo */
    private $businessOfficeRepo;
    /**  @var CoWorkerInfoFilter $coWorkerInfoFilter */
    private $coWorkerInfoFilter;
    /** @var TimeFrame $timeFrame */
    private $timeFrame;

    /**
     * AttendanceController constructor.
     * @param BusinessHolidayRepoInterface $business_holidays_repo
     * @param BusinessWeekendRepoInterface $business_weekend_repo
     * @param BusinessOfficeRepoInterface $business_office_repo
     * @param CoWorkerInfoFilter $co_worker_info_filter
     * @param OfficeSettingChangesLogsRepository $office_setting_changes_logs_repo
     * @param TimeFrame $time_frame
     */
    public function __construct(BusinessHolidayRepoInterface       $business_holidays_repo, BusinessWeekendRepoInterface $business_weekend_repo,
                                BusinessOfficeRepoInterface        $business_office_repo, CoWorkerInfoFilter $co_worker_info_filter,
                                OfficeSettingChangesLogsRepository $office_setting_changes_logs_repo, TimeFrame $time_frame)
    {
        $this->holidayRepository = $business_holidays_repo;
        $this->officeSettingChangesLogsRepo = $office_setting_changes_logs_repo;
        $this->officeSettingChangesLogsRequester = new Requester();
        $this->officeSettingChangesLogsCreator = new Creator();
        $this->businessWeekendRepo = $business_weekend_repo;
        $this->businessOfficeRepo = $business_office_repo;
        $this->coWorkerInfoFilter = $co_worker_info_filter;
        $this->timeFrame = $time_frame;
    }

    /**
     * @param $business
     * @param Request $request
     * @param AttendanceList $stat
     * @param Filter $daily_filter
     * @return JsonResponse
     */
    public function getDailyStats($business, Request $request, AttendanceList $stat, Filter $daily_filter)
    {
        $this->validate($request, [
            'status' => 'string|in:' . implode(',', Statuses::get()),
            'department_id' => 'numeric',
            'date' => 'date|date_format:Y-m-d',
        ]);

        $date = $request->has('date') ? Carbon::parse($request->date) : Carbon::now();
        $selected_date = $this->timeFrame->forADay($date);

        $checkin_location = $checkout_location = null;
        $checkin_remote_mode = $checkout_remote_mode = null;
        if ($request->checkin_location) $checkin_location = $this->getIpById($request->checkin_location);
        if ($request->checkout_location) $checkout_location = $this->getIpById($request->checkout_location);
        if ($request->checkin_remote_mode) $checkin_remote_mode = $request->checkin_remote_mode;
        if ($request->checkout_remote_mode) $checkout_remote_mode = $request->checkout_remote_mode;

        /** @var array $attendances */
        $attendances = $stat->setBusiness($request->business)
            ->setSelectedDate($selected_date)
            ->setBusinessDepartment($request->department_id)
            ->setSearch($request->search)
            ->setCheckinStatus($request->checkin_status)
            ->setCheckoutStatus($request->checkout_status)
            ->setSortKey($request->sort)
            ->setSortColumn($request->sort_column)
            ->setStatusFilter($request->status_filter)
            ->setOfficeOrRemoteCheckin($request->checkin_office_or_remote)
            ->setOfficeOrRemoteCheckout($request->checkout_office_or_remote)
            ->setCheckinLocation($checkin_location)
            ->setCheckoutLocation($checkout_location)
            ->setCheckInRemoteMode($checkin_remote_mode)
            ->setCheckOutRemoteMode($checkout_remote_mode)
            ->get();

        if ($request->sort && $request->sort_column === 'overtime') {
            $attendances = $daily_filter->attendanceSortOnOvertime(collect($attendances), $request->sort)->values()->toArray();
        }

        $count = count($attendances);
        if ($request->file == 'excel') {
            return (new DailyExcel())->setDate($date->format('Y-m-d'))->setData($attendances)->download();
        }
        return api_response($request, null, 200, [
            'attendances' => $attendances,
            'total' => $count
        ]);
    }

    /**
     * @param $business
     * @param Request $request
     * @param MonthlyAttendanceCalculator $calculator
     * @return JsonResponse
     */
    public function getMonthlyStats($business, Request $request, MonthlyAttendanceCalculator $calculator)
    {
        $this->validate($request, ['file' => 'string|in:excel']);

        if ($request->file == 'excel') {
            dispatch(new SendMonthlyAttendanceReportEmail($business, $request->all()));
            return api_response($request, null, 200, [
                'message' => "Your report will be sent to your email. Please check a few moments later."
            ]);
        }

        list($all_employee_attendance, $total_business_members_count, , ) = $calculator->calculate($business, $request);

        return api_response($request, $all_employee_attendance, 200, [
            'all_employee_attendance' => $all_employee_attendance,
            'total_members' => $total_business_members_count
        ]);
    }

    /**
     * @param $business
     * @param $member
     * @param Request $request
     * @param BusinessHolidayRepoInterface $business_holiday_repo
     * @param BusinessWeekendSettingsRepo $business_weekend_settings_repo
     * @param AttendanceRepoInterface $attendance_repo
     * @param BusinessMemberRepositoryInterface $business_member_repository
     * @param AttendanceList $list
     * @param DetailsExcel $details_excel
     * @param Filter $daily_filer
     * @return JsonResponse|void
     */
    public function showStat($business, $member, Request $request, BusinessHolidayRepoInterface $business_holiday_repo,
                             BusinessWeekendSettingsRepo $business_weekend_settings_repo,
                             AttendanceList $list, DetailsExcel $details_excel, Filter $daily_filer)
    {
        $this->validate($request, ['month' => 'numeric|min:1|max:12']);
        $business = $request->business;
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;

        $time_frame = $this->timeFrame->forDateRange($request->start_date, $request->end_date);
        $business_member_joining_date = $business_member->join_date;
        $joining_date = null;
        if ($this->checkJoiningDate($business_member_joining_date, $request->start_date, $request->end_date)) {
            $joining_date = $business_member_joining_date->format('d F');
            $start_date = $business_member_joining_date;
            $end_date = $request->end_date;
            $time_frame = $time_frame->forDateRange($start_date, $end_date);
        }

        $business_member_leave = $business_member->leaves()->accepted()->between($time_frame)->get();
        $attendances = $business_member->attendances()->whereBetween('date', $time_frame->getArray())->get();
        $business_holiday = $business_holiday_repo->getAllByBusiness($business);
        $weekend_settings = $business_weekend_settings_repo->getAllByBusiness($business);

        $shifts_counts = 0;
        if ($business_member->isShiftEnable())
            $shifts_counts = $business_member->shifts()->where('is_general', 0)->whereBetween('date', $this->timeFrame->getArray())->count();

        $employee_attendance = (new MonthlyStat($time_frame, $business, $weekend_settings, $business_member_leave, true, $business_member->isShiftEnable()))
            ->setBusinessHolidays($business_holiday)->transform($attendances, $shifts_counts);

        $daily_breakdowns = collect($employee_attendance['daily_breakdown']);
        $daily_breakdowns = $daily_breakdowns->filter(function ($breakdown) {
            return Carbon::parse($breakdown['date'])->lessThanOrEqualTo(Carbon::today());
        });

        if ($request->has('sort_on_date')) $daily_breakdowns = $daily_filer->attendanceSortOnDate($daily_breakdowns, $request->sort_on_date)->values();
        if ($request->has('sort_on_hour')) $daily_breakdowns = $daily_filer->attendanceSortOnHour($daily_breakdowns, $request->sort_on_hour)->values();
        if ($request->has('sort_on_checkin')) $daily_breakdowns = $daily_filer->attendanceSortOnCheckin($daily_breakdowns, $request->sort_on_checkin)->values();
        if ($request->has('sort_on_checkout')) $daily_breakdowns = $daily_filer->attendanceSortOnCheckout($daily_breakdowns, $request->sort_on_checkout)->values();
        if ($request->has('sort_on_overtime')) $daily_breakdowns = $daily_filer->attendanceCustomSortOnOvertime($daily_breakdowns, $request->sort_on_overtime)->values();
        if ($request->file == 'excel') {
            return $details_excel->setBreakDownData($daily_breakdowns->toArray())
                ->setBusinessMember($business_member)
                ->setStartDate($request->start_date)
                ->setEndDate($request->end_date)
                ->download();
        }

        return api_response($request, $list, 200, [
            'stat' => $employee_attendance['statistics'],
            'attendances' => $daily_breakdowns,
            'employee' => [
                'id' => $business_member->member->id,
                'business_member_id' => $business_member->id,
                'name' => $business_member->member->profile->name,
                'image' => $business_member->member->profile->pro_pic,
                'mobile' => $business_member->member->profile->mobile ?: 'N/S',
                'designation' => $business_member->role ? $business_member->role->name : null,
                'department' => $business_member->role && $business_member->role->businessDepartment ? $business_member->role->businessDepartment->name : null,
            ],
            'joining_date' => $joining_date
        ]);
    }

    /**
     * @param $business
     * @param Request $request
     * @param BusinessOfficeHoursRepoInterface $office_hours
     * @return JsonResponse
     */
    public function getOfficeTime($business, Request $request, BusinessOfficeHoursRepoInterface $office_hours)
    {
        $business = $request->business;
        $weekends = $this->businessWeekendRepo->getAllByBusiness($business);
        $weekend_days = $weekends->pluck('weekday_name')->toArray();
        $weekend_days = array_map('ucfirst', $weekend_days);
        $office_time = $office_hours->getOfficeTime($business);
        $half_day_leave_types = $business->leaveTypes()->isHalfDayEnable();
        $data = [
            'office_hour_type' => 'Fixed Time',
            'start_time' => $office_time ? Carbon::parse($office_time->start_time)->format('h:i a') : '09:00 am',
            'end_time' => $office_time ? Carbon::parse($office_time->end_time)->format('h:i a') : '05:00 pm',
            'weekends' => $weekend_days,
            'is_half_day_enable' => $business->is_half_day_enable,
            'half_day_leave_types_count' => $half_day_leave_types->count(),
            'half_day_leave_types' => $half_day_leave_types->pluck('title'),
            'half_day_initial_timings' => $this->getHalfDayTimings($business)
        ];

        return api_response($request, null, 200, ['office_timing' => $data]);
    }

    /**
     * @param Request $request
     * @param OfficeTimingUpdater $updater
     * @return JsonResponse
     */
    public function updateOfficeTime(Request $request, OfficeTimingUpdater $updater)
    {
        $this->validate($request, [
            'office_hour_type' => 'required',
            'start_time' => 'date_format:H:i:s',
            'end_time' => 'date_format:H:i:s|after:start_time',
            'weekends' => 'required|array',
            'half_day' => 'required',
            'half_day_config' => 'string'
        ], [
            'end_time.after' => 'Start Time Must Be Less Than End Time'
        ]);
        $start_time = Carbon::parse($request->start_time)->format('H:i') . ':59';
        $end_time = Carbon::parse($request->end_time)->format('H:i') . ':59';

        $business_member = $request->business_member;
        $office_timing = $updater->setBusiness($request->business)
            ->setMember($business_member->member)
            ->setOfficeHourType($request->office_hour_type)
            ->setStartTime($start_time)
            ->setEndTime($end_time)
            ->setWeekends($request->weekends)
            ->setHalfDayTimings($request)
            ->update();

        if ($office_timing) return api_response($request, null, 200, ['msg' => "Update Successful"]);
    }

    /**
     * @param $business
     * @param Request $request
     * @param AttendanceSettingTransformer $transformer
     * @return JsonResponse
     */
    public function getAttendanceSetting($business, Request $request, AttendanceSettingTransformer $transformer)
    {
        $business = $request->business;
        $business_offices = $this->businessOfficeRepo->getAllByBusiness($business);
        $attendance_types = $business->attendanceTypes()->withTrashed()->get();
        $attendance_setting_data = $transformer->getData($attendance_types, $business_offices);

        return api_response($request, null, 200, [
            'sheba_attendance_types' => $attendance_setting_data["sheba_attendance_types"],
            'business_attendance_types' => $attendance_setting_data["attendance_types"],
            'business_offices' => $attendance_setting_data["business_offices"]
        ]);
    }

    /**
     * @param $business
     * @param Request $request
     * @param TypeUpdater $type_updater
     * @param SettingCreator $creator
     * @param SettingUpdater $updater
     * @param SettingDeleter $deleter
     * @return JsonResponse
     */
    public function updateAttendanceSetting($business, Request $request, TypeUpdater $type_updater,
                                            SettingCreator $creator, SettingUpdater $updater, SettingDeleter $deleter)
    {
        $this->validate($request, ['attendance_types' => 'required|string', 'business_offices' => 'required|string']);

        $business_member = $request->business_member;
        $business = $request->business;
        $this->setModifier($business_member->member);
        $errors = [];

        $attendance_types = json_decode($request->attendance_types);
        if (!is_null($attendance_types)) {
            foreach ($attendance_types as $attendance_type) {
                $attendance_type_id = isset($attendance_type->id) ? $attendance_type->id : null;

                $type_updater->setBusiness($business)
                    ->setTypeId($attendance_type_id)
                    ->setType($attendance_type->type)
                    ->setAction($attendance_type->action)
                    ->update();
            }
        }

        $business_offices = json_decode($request->business_offices);

        if (!is_null($business_offices)) {
            $offices = collect($business_offices);
            $deleted_offices = $offices->where('action', ActionType::DELETE);
            $deleted_offices->each(function ($deleted_office) use ($deleter) {
                $deleter->setBusinessOfficeId($deleted_office->id);
                $deleter->delete();
            });

            $added_offices = $offices->where('action', ActionType::ADD);
            $added_offices->each(function ($added_office) use ($creator, $business, &$errors) {
                $creator->setBusiness($business)->setName($added_office->name)->setIp($added_office->ip);
                if ($creator->hasError()) {
                    array_push($errors, $creator->getErrorMessage());
                    $creator->resetError();
                    return;
                }
                $creator->create();
            });

            $edited_offices = $offices->where('action', ActionType::EDIT);
            $edited_offices->each(function ($edited_office) use ($updater, $business, &$errors) {
                $updater->setBusinessOfficeId($edited_office->id)->setName($edited_office->name)->setIp($edited_office->ip);
                if ($updater->hasError()) {
                    array_push($errors, $updater->getErrorMessage());
                    $updater->resetError();
                    return;
                }
                $updater->update();
            });
        }

        if ($errors) {
            if ($this->isFailedToUpdateAllSettings($errors, $business_offices))
                return api_response($request, null, 422, ['message' => implode(', ', $errors)]);

            return api_response($request, null, 303, ['message' => implode(', ', $errors)]);
        }

        return api_response($request, null, 200, ['message' => "Update Successful"]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getHolidays(Request $request)
    {
        $holiday_list = new HolidayList($request->business, $this->holidayRepository);
        $holidays = $holiday_list->getHolidays($request);

        return api_response($request, null, 200, [
            'business_holidays' => $holidays
        ]);
    }

    /**
     * @param Request $request
     * @param HolidayCreator $creator
     * @return JsonResponse
     */
    public function storeHoliday(Request $request, HolidayCreator $creator)
    {
        $this->validate($request, [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'title' => 'required|string'
        ]);
        $business_member = $request->business_member;

        $holiday = $creator->setBusiness($request->business)
            ->setMember($business_member->member)
            ->setHolidayRepo($this->holidayRepository)
            ->setStartDate($request->start_date)
            ->setEndDate($request->end_date)
            ->setHolidayName($request->title)
            ->create();
        $this->officeSettingChangesLogsRequester->setBusiness($request->business)->setHolidayStartDate($request->start_date)->setHolidayEndDate($request->end_date)->setHolidayName($request->title);
        $this->officeSettingChangesLogsCreator->setOfficeSettingChangesLogsRequester($this->officeSettingChangesLogsRequester)->createHolidayStoreLogs();
        return api_response($request, null, 200, ['holiday' => $holiday]);
    }

    /**
     * @param $business
     * @param $holiday
     * @param Request $request
     * @param HolidayCreatorRequest $creator_request
     * @param HolidayUpdater $updater
     * @return JsonResponse
     */
    public function update($business, $holiday, Request $request, HolidayCreatorRequest $creator_request, HolidayUpdater $updater)
    {
        $this->validate($request, [
            'start_date' => 'required|date_format:Y-m-d|',
            'end_date' => 'required|date_format:Y-m-d||after_or_equal:start_date',
            'title' => 'required|string'
        ]);
        $manager_member = $request->manager_member;
        $holiday = $this->holidayRepository->find((int)$holiday);

        $this->officeSettingChangesLogsRequester->setBusiness($request->business)
            ->setExistingHolidayStart($holiday->start_date)
            ->setExistingHolidayEnd($holiday->end_date)
            ->setExistingHolidayName($holiday->title)
            ->setHolidayStartDate($request->start_date)
            ->setHolidayEndDate($request->end_date)
            ->setHolidayName($request->title);

        $updater_request = $creator_request->setBusiness($request->business)
            ->setMember($manager_member)
            ->setStartDate($request->start_date)
            ->setEndDate($request->end_date)
            ->setHolidayName($request->title);

        $updater->setHoliday($holiday)->setBusinessHolidayCreatorRequest($updater_request)->update();
        $this->officeSettingChangesLogsCreator->setOfficeSettingChangesLogsRequester($this->officeSettingChangesLogsRequester)->createHolidayUpdateLogs();
        return api_response($request, null, 200);
    }

    /**
     * @param $business
     * @param $holiday
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy($business, $holiday, Request $request)
    {
        $manager_member = $request->manager_member;
        $holiday = $this->holidayRepository->find((int)$holiday);
        if (!$holiday) return api_response($request, null, 404);
        $this->officeSettingChangesLogsRequester->setBusiness($request->business)->setExistingHoliday($holiday);
        $this->holidayRepository->delete($holiday);
        $this->setModifier($manager_member);
        $this->officeSettingChangesLogsCreator->setOfficeSettingChangesLogsRequester($this->officeSettingChangesLogsRequester)->createHolidayDeleteLogs();
        return api_response($request, null, 200);
    }

    /**
     * @param $business
     * @param Request $request
     * @param HalfDaySettingUpdater $updater
     * @return JsonResponse
     */
    public function updateHalfDaySetting($business, Request $request, HalfDaySettingUpdater $updater)
    {
        $this->validate($request, ['half_day' => 'required', 'half_day_config' => 'required|string']);

        $business_member = $request->business_member;
        $business = $request->business;
        $this->setModifier($business_member->member);

        $updater->setBusiness($business)->setHalfDayConfig($request->half_day_config)->update();

        return api_response($request, null, 200, ['message' => "Update Successful"]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllHolidayDates(Request $request)
    {
        $holiday_list = new HolidayList($request->business, $this->holidayRepository);
        $holidays = $holiday_list->getAllHolidayDates($request);

        return api_response($request, null, 200, ['business_holidays' => array_values($holidays)]);
    }

    /**
     * @param $location
     * @return mixed
     */
    public function getIpById($location)
    {
        $business_office = $this->businessOfficeRepo->find($location);
        return $business_office ? $business_office->ip : null;
    }

    /**
     * @param $business
     * @param Request $request
     * @param BusinessOfficeHoursRepoInterface $office_hours
     * @param AttendanceSettingTransformer $transformer
     * @return JsonResponse
     */
    public function getOperationalOfficeSettings($business, Request $request, BusinessOfficeHoursRepoInterface $office_hours, AttendanceSettingTransformer $transformer)
    {
        $business = $request->business;

        $business_offices = $this->businessOfficeRepo->getAllByBusiness($business);
        $attendance_types = $business->attendanceTypes()->withTrashed()->get();
        $attendance_setting_data = $transformer->getData($attendance_types, $business_offices);

        $weekends = $this->businessWeekendRepo->getAllByBusiness($business);
        $weekend_days = $weekends->pluck('weekday_name')->toArray();
        $weekend_days = array_map('ucfirst', $weekend_days);

        $office_time = $office_hours->getOfficeTime($business);
        $data = [
            'total_working_days_type' => $office_time->type,
            'total_working_days' => $office_time->number_of_days,
            'is_weekend_included' => $office_time->is_weekend_included,
            'weekends' => $weekend_days,
            'sheba_attendance_types' => $attendance_setting_data["sheba_attendance_types"],
            'business_attendance_types' => $attendance_setting_data["attendance_types"],
            'business_offices' => $attendance_setting_data["business_offices"],
            'business_office_locations' => $attendance_setting_data["business_geo_locations"]
        ];

        return api_response($request, null, 200, ['office_settings_operational' => $data]);
    }

    /**
     * @param $business
     * @param Request $request
     * @param TypeUpdater $type_updater
     * @param SettingCreator $setting_creator
     * @param SettingUpdater $setting_updater
     * @param SettingDeleter $setting_deleter
     * @param OperationalSetting $operational_setting_updater
     * @param GeoLocationCreator $geo_location_creator
     * @param GeoLocationUpdater $geo_location_updater
     * @param GeoLocationDeleter $geo_location_deleter
     * @return JsonResponse|void
     */
    public function updateOperationalOfficeSettings($business, Request $request, TypeUpdater $type_updater,
                                                    SettingCreator $setting_creator, SettingUpdater $setting_updater,
                                                    SettingDeleter $setting_deleter, OperationalSetting $operational_setting_updater,
                                                    GeoLocationCreator $geo_location_creator, GeoLocationUpdater $geo_location_updater,
                                                    GeoLocationDeleter $geo_location_deleter)
    {
        $this->validate($request, [
            'attendance_types' => 'required|string',
            'business_offices' => 'required|string',
            'business_geo_locations' => 'required|string'
        ]);

        $business_member = $request->business_member;
        $business = $request->business;
        $this->setModifier($business_member->member);
        $errors = [];
        $previous_attendance_type = $business->attendanceTypes->pluck('attendance_type')->toArray();
        $attendance_types = json_decode($request->attendance_types);
        $new_attendance_type = [];
        $this->officeSettingChangesLogsRequester->setBusiness($business);
        if (!is_null($attendance_types)) {
            foreach ($attendance_types as $attendance_type) {
                $attendance_type_id = isset($attendance_type->id) ? $attendance_type->id : null;

                $type_updater->setBusiness($business)
                    ->setTypeId($attendance_type_id)
                    ->setType($attendance_type->type)
                    ->setAction($attendance_type->action)
                    ->update();
                if ($attendance_type->action == 'checked') $new_attendance_type[] = $attendance_type->type;
            }
            $this->officeSettingChangesLogsRequester->setPreviousAttendanceType($previous_attendance_type)->setNewAttendanceType($new_attendance_type);
            $this->officeSettingChangesLogsCreator->setOfficeSettingChangesLogsRequester($this->officeSettingChangesLogsRequester)->createAttendanceTypeLogs();
        }

        $business_offices = json_decode($request->business_offices);
        $business_geo_location = json_decode($request->business_geo_locations);
        if (!is_null($business_offices)) {
            $offices = collect($business_offices);
            $deleted_offices = $offices->where('action', ActionType::DELETE);
            $deleted_offices->each(function ($deleted_office) use ($setting_deleter) {
                $this->officeSettingChangesLogsRequester->setOfficeName($deleted_office->office_name)->setOfficeIp($deleted_office->ip);
                $setting_deleter->setBusinessOfficeId($deleted_office->id);
                $setting_deleter->delete();
                $this->officeSettingChangesLogsCreator->setOfficeSettingChangesLogsRequester($this->officeSettingChangesLogsRequester)->createDeleteOfficeIpLogs();
            });

            $added_offices = $offices->where('action', ActionType::ADD);
            $added_offices->each(function ($added_office) use ($setting_creator, $business, &$errors) {
                $this->officeSettingChangesLogsRequester->setOfficeName($added_office->office_name)->setOfficeIp($added_office->ip);
                $setting_creator->setBusiness($business)->setName($added_office->office_name)->setIp($added_office->ip);
                if ($setting_creator->hasError()) {
                    array_push($errors, $setting_creator->getErrorMessage());
                    $setting_creator->resetError();
                    return;
                }
                $setting_creator->create();
                $this->officeSettingChangesLogsCreator->setOfficeSettingChangesLogsRequester($this->officeSettingChangesLogsRequester)->createCreatedOfficeIpLogs();
            });

            $edited_offices = $offices->where('action', ActionType::EDIT);
            $edited_offices->each(function ($edited_office) use ($setting_updater, $business, &$errors) {

                $previous_office_ip = $this->businessOfficeRepo->builder()->withTrashed()->find($edited_office->id);
                $this->officeSettingChangesLogsRequester->setPreviousOfficeIp($previous_office_ip)->setOfficeName($edited_office->office_name)->setOfficeIp($edited_office->ip);
                $setting_updater->setBusinessOfficeId($edited_office->id)->setName($edited_office->office_name)->setIp($edited_office->ip);
                if ($setting_updater->hasError()) {
                    array_push($errors, $setting_updater->getErrorMessage());
                    $setting_updater->resetError();
                    return;
                }
                $setting_updater->update();
                $this->officeSettingChangesLogsCreator->setOfficeSettingChangesLogsRequester($this->officeSettingChangesLogsRequester)->createEditedOfficeIpLogs();
            });
        }

        if ($errors) {
            if ($this->isFailedToUpdateAllSettings($errors, $business_offices)) return api_response($request, null, 422, ['message' => implode(', ', $errors)]);
            return api_response($request, null, 303, ['message' => implode(', ', $errors)]);
        }

        if (!is_null($business_geo_location)) {
            $geo_offices = collect($business_geo_location);

            $added_geo_offices = $geo_offices->where('action', ActionType::ADD);
            $added_geo_offices->each(function ($added_geo_office) use ($geo_location_creator, $business, &$errors) {
                //$this->officeSettingChangesLogsRequester->setOfficeName($added_office->office_name)->setOfficeIp($added_office->ip);
                $geo_location_creator->setBusiness($business)->setName($added_geo_office->office_name)->setLat($added_geo_office->lat)->setLng($added_geo_office->lng)->setRadius($added_geo_office->radius);
                $geo_location_creator->create();
                //$this->officeSettingChangesLogsCreator->setOfficeSettingChangesLogsRequester($this->officeSettingChangesLogsRequester)->createCreatedOfficeIpLogs();
            });

            $edited_geo_offices = $geo_offices->where('action', ActionType::EDIT);
            $edited_geo_offices->each(function ($edited_geo_office) use ($geo_location_updater, $business, &$errors) {

                //$previous_office_ip = $this->businessOfficeRepo->builder()->withTrashed()->find($edited_office->id);
                //$this->officeSettingChangesLogsRequester->setPreviousOfficeIp($previous_office_ip)->setOfficeName($edited_office->office_name)->setOfficeIp($edited_office->ip);
                $geo_location_updater->setBusinessOfficeId($edited_geo_office->id)->setName($edited_geo_office->office_name)->setLat($edited_geo_office->lat)->setLng($edited_geo_office->lng)->setRadius($edited_geo_office->radius);
                $geo_location_updater->update();
                //$this->officeSettingChangesLogsCreator->setOfficeSettingChangesLogsRequester($this->officeSettingChangesLogsRequester)->createEditedOfficeIpLogs();
            });

            $deleted_geo_offices = $geo_offices->where('action', ActionType::DELETE);
            $deleted_geo_offices->each(function ($deleted_geo_office) use ($geo_location_deleter) {
                //$this->officeSettingChangesLogsRequester->setOfficeName($deleted_office->office_name)->setOfficeIp($deleted_office->ip);
                $geo_location_deleter->setBusinessOfficeId($deleted_geo_office->id);
                $geo_location_deleter->delete();
                //$this->officeSettingChangesLogsCreator->setOfficeSettingChangesLogsRequester($this->officeSettingChangesLogsRequester)->createDeleteOfficeIpLogs();
            });
        }


        $business_weekend = $this->businessWeekendRepo->getAllByBusiness($business)->pluck('weekday_name')->toArray();
        $business_office = $business->officeHour;
        $previous_working_days_type = $business_office->type;
        $previous_number_of_days = $business_office->number_of_days;
        $previous_is_weekend_included = $business_office->is_weekend_included;
        $office_timing = $operational_setting_updater->setBusiness($request->business)
            ->setMember($business_member->member)
            ->setPreviousWeekends($business_weekend)
            ->setWeekends($request->weekends)
            ->setTotalWorkingDaysType($request->working_days_type)
            ->setNumberOfDays($request->days)
            ->setIsWeekendIncluded($request->is_weekend_included)
            ->update();
        $this->officeSettingChangesLogsRequester->setPreviousWeekends($business_weekend)->setPreviousTotalWorkingDaysType($previous_working_days_type)->setPreviousNumberOfDays($previous_number_of_days)->setPreviousIsWeekendIncluded($previous_is_weekend_included)->setRequest($request);
        if ($request->weekends) $this->officeSettingChangesLogsCreator->setOfficeSettingChangesLogsRequester($this->officeSettingChangesLogsRequester)->createWeekendLogs();
        $this->officeSettingChangesLogsCreator->setOfficeSettingChangesLogsRequester($this->officeSettingChangesLogsRequester)->createWorkingDaysTypeLogs();

        if ($office_timing) return api_response($request, null, 200, ['msg' => "Update Successful"]);
    }

    /**
     * @param $business
     * @param Request $request
     * @param BusinessOfficeHoursRepoInterface $office_hours
     * @return JsonResponse
     */
    public function getAttendanceOfficeSettings($business, Request $request, BusinessOfficeHoursRepoInterface $office_hours)
    {
        $business = $request->business;
        $office_time = $office_hours->getOfficeTime($business);
        $half_day_leave_types = $business->leaveTypes()->isHalfDayEnable();
        $data = [
            'office_hour_type' => 'Fixed Time',
            'start_time' => $office_time ? Carbon::parse($office_time->start_time)->format('h:i a') : '09:00 am',
            'is_allow_start_time_grace' => $office_time->is_start_grace_time_enable,
            'starting_grace_time' => $office_time->start_grace_time,
            'end_time' => $office_time ? Carbon::parse($office_time->end_time)->format('h:i a') : '05:00 pm',
            'is_allow_end_time_grace' => $office_time->is_end_grace_time_enable,
            'ending_grace_time' => $office_time->end_grace_time,
            'is_half_day_enable' => $business->is_half_day_enable,
            'half_day_leave_types_count' => $half_day_leave_types->count(),
            'half_day_leave_types' => $half_day_leave_types->pluck('title'),
            'half_day_initial_timings' => $this->getHalfDayTimings($business),
            'is_grace_period_policy_enable' => $office_time->is_grace_period_policy_enable,
            'is_late_checkin_early_checkout_enable' => $office_time->is_late_checkin_early_checkout_policy_enable,
            'is_for_late_checkin' => $office_time->is_for_late_checkin,
            'is_for_early_checkout' => $office_time->is_for_early_checkout,
        ];

        return api_response($request, null, 200, ['office_settings_attendance' => $data]);
    }

    /**
     * @param Request $request
     * @param AttendaceSettingUpdater $updater
     * @param PolicyRuleRequester $requester
     * @param PolicyRuleUpdater $policy_updater
     * @return JsonResponse|void
     */
    public function updateAttendanceOfficeSettings(Request $request, AttendaceSettingUpdater $updater, PolicyRuleRequester $requester, PolicyRuleUpdater $policy_updater)
    {
        $validation_data = [
            'office_hour_type' => 'required',
            'start_time' => 'date_format:H:i:s',
            'end_time' => 'date_format:H:i:s|after:start_time',
            'half_day' => 'required', 'half_day_config' => 'string',
            'is_start_grace_period_allow" => "required',
            'is_end_grace_period_allow" => "required',
            'is_grace_policy_enable' => 'required',
            'is_checkin_checkout_policy_enable' => 'required',
        ];
        if ($request->is_grace_policy_enable == 1) $data['grace_policy_rules'] = 'required';
        if ($request->is_checkin_checkout_policy_enable == 1) $data['checkin_checkout_policy_rules'] = 'required';
        $this->validate($request, $validation_data, [
            'end_time.after' => 'Start Time Must Be Less Than End Time'
        ]);

        $start_time = Carbon::parse($request->start_time)->format('H:i') . ':59';
        $end_time = Carbon::parse($request->end_time)->format('H:i') . ':00';
        $business = $request->business;
        $business_office_hour = $business->officeHour;
        $business_member = $request->business_member;
        $this->officeSettingChangesLogsRequester
            ->setBusiness($business)
            ->setPreviousOfficeStartTime($business_office_hour->start_time)
            ->setPreviousOfficeEndTime($business_office_hour->end_time)
            ->setPreviousIsStartGracePeriodEnable($business_office_hour->is_start_grace_time_enable)
            ->setPreviousIsEndGracePeriodEnable($business_office_hour->is_end_grace_time_enable)
            ->setPreviousStartGracePeriodTime($business_office_hour->start_grace_time)
            ->setPreviousEndGracePeriodTime($business_office_hour->end_grace_time)
            ->setRequest($request);

        $office_timing = $updater->setBusiness($request->business)
            ->setMember($business_member->member)
            ->setOfficeHourType($request->office_hour_type)
            ->setStartTime($start_time)
            ->setStartGracePeriod($request->is_start_grace_period_allow)
            ->setStartGracePeriodTime($request->starting_grace_time)
            ->setEndTime($end_time)
            ->setEndGracePeriod($request->is_end_grace_period_allow)
            ->setEndGracePeriodTime($request->ending_grace_time)
            ->setHalfDayTimings($request)
            ->update();

        $this->officeSettingChangesLogsCreator->setOfficeSettingChangesLogsRequester($this->officeSettingChangesLogsRequester);
        $this->officeSettingChangesLogsCreator->createAttendanceOfficeStartTimingLogs();
        $this->officeSettingChangesLogsCreator->createAttendanceOfficeEndTimingLogs();
        $this->officeSettingChangesLogsCreator->createAttendanceStartGraceTimingLogs();
        $this->officeSettingChangesLogsCreator->createAttendanceEndGraceTimingLogs();
        if ($office_timing) {
            $requester->setBusiness($request->business)
                ->setIsEnable($request->is_grace_policy_enable)
                ->setPolicyType(Type::GRACE_PERIOD)
                ->setRules($request->grace_policy_rules);
            $grace_policy = $policy_updater->setPolicyRuleRequester($requester)->update();
        }

        if ($grace_policy) {
            $requester->setBusiness($request->business)
                ->setIsEnable($request->is_checkin_checkout_policy_enable)
                ->setPolicyType(Type::LATE_CHECKIN_EARLY_CHECKOUT)
                ->setForLateCheckIn($request->for_checkin)
                ->setForEarlyCheckOut($request->for_checkout)
                ->setRules($request->checkin_checkout_policy_rules);
            $checkin_checkout_policy = $policy_updater->setPolicyRuleRequester($requester)->update();
        }
        if ($checkin_checkout_policy) return api_response($request, null, 200, ['msg' => "Update Successful"]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getGracePolicy(Request $request)
    {
        $business = $request->business;
        if (!$business) return api_response($request, null, 403, ['message' => 'You Are not authorized to show this settings']);
        $grace_policy = $business->gracePolicy;
        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $resource = new Collection($grace_policy, new PolicyTransformer());
        $grace_policy_rules = $manager->createData($resource)->toArray()['data'];

        return api_response($request, $grace_policy_rules, 200, ['grace_policy_rules' => $grace_policy_rules]);
    }

    /**
     * @param Request $request
     * @param PolicyRuleRequester $requester
     * @param PolicyRuleUpdater $updater
     * @return JsonResponse
     */
    public function createUnpaidLeavePolicy(Request $request, PolicyRuleRequester $requester, PolicyRuleUpdater $updater)
    {
        $data = [
            'is_enable' => 'required',
            'policy_type' => 'required'
        ];
        if ($request->is_enable == 1) $data['rules'] = 'required';
        $this->validate($request, $data);
        $business = $request->business;
        if (!$business) return api_response($request, null, 403, ['message' => 'You Are not authorized to show this settings']);
        $this->setModifier($request->manager_member);
        $requester->setBusiness($business)
            ->setIsEnable($request->is_enable)
            ->setPenaltyComponent($request->component)
            ->setPolicyType($request->policy_type)
            ->setRules($request->rules);
        if ($requester->getError()) return api_response($request, null, 400, ['message' => $requester->getError()]);
        $updater->setPolicyRuleRequester($requester)->update();

        return api_response($request, null, 200);
    }

    /**
     * @param Request $request
     * @param BusinessOfficeHoursRepoInterface $office_hours
     * @return JsonResponse
     */
    public function getUnpaidLeavePolicy(Request $request, BusinessOfficeHoursRepoInterface $office_hours)
    {
        $business = $request->business;
        if (!$business) return api_response($request, null, 403, ['message' => 'You Are not authorized to show this settings']);
        $office_time = $office_hours->getOfficeTime($business);
        $unpaid_leave_policy = $business->unpaidLeavePolicy;
        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $resource = new Collection($unpaid_leave_policy, new PolicyTransformer());
        $unpaid_leave_policy_rules = $manager->createData($resource)->toArray()['data'];
        $unauthorised_leave_penalty_component = $office_time->unauthorised_leave_penalty_component;
        return api_response($request, $unpaid_leave_policy_rules, 200, [
            'is_unpaid_leave_policy_enable' => $office_time->is_unpaid_leave_policy_enable,
            'unauthorised_leave_penalty_component' => is_numeric($unauthorised_leave_penalty_component) ? intval($unauthorised_leave_penalty_component) : $unauthorised_leave_penalty_component,
            'unpaid_leave_policy_rules' => $unpaid_leave_policy_rules
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getLateCheckinEarlyCheckoutPolicy(Request $request)
    {
        $business = $request->business;
        if (!$business) return api_response($request, null, 403, ['message' => 'You Are not authorized to show this settings']);
        $checkin_checkout_policy = $business->checkinCheckoutPolicy;
        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $resource = new Collection($checkin_checkout_policy, new PolicyTransformer());
        $checkin_checkout_policy_rules = $manager->createData($resource)->toArray()['data'];

        return api_response($request, $checkin_checkout_policy_rules, 200, ['checkin_checkout_policy_rules' => $checkin_checkout_policy_rules]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getOfficeSettingChangesLogs(Request $request)
    {
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        $operational_changes_logs = $this->officeSettingChangesLogsRepo->where('business_id', $business->id)->orderBy('created_at', 'DESC')->get();
        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $resource = new Collection($operational_changes_logs, new ChangesLogsTransformer());
        $operational_changes_logs = $manager->createData($resource)->toArray()['data'];
        return api_response($request, $operational_changes_logs, 200, ['office_setting_changes_logs' => $operational_changes_logs]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getOfficeLocations(Request $request)
    {
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);
        $business = $request->business;
        if (!$business) return api_response($request, null, 401);
        $office_id = $request->office_id;
        $business_offices = $this->businessOfficeRepo->getAllByBusiness($business);
        $office_locations = (new OfficeLocationFormatter($business_offices))->get($office_id);
        return api_response($request, $office_locations, 200, ['office_locations' => $office_locations]);
    }

    /**
     * @param array $errors
     * @param $business_offices
     * @return bool
     */
    private function isFailedToUpdateAllSettings(array $errors, $business_offices)
    {
        return count($errors) == count($business_offices);
    }

    /**
     * @param Business $business
     * @return array[]|\string[][]
     */
    private function getHalfDayTimings(Business $business)
    {
        if ($business->half_day_configuration) {
            $half_day_times = json_decode($business->half_day_configuration);
            return [
                'first_half' => [
                    'start_time' => Carbon::parse($half_day_times->first_half->start_time)->format('h:i a'),
                    'end_time' => Carbon::parse($half_day_times->first_half->end_time)->format('h:i a')
                ],
                'second_half' => [
                    'start_time' => Carbon::parse($half_day_times->second_half->start_time)->format('h:i a'),
                    'end_time' => Carbon::parse($half_day_times->second_half->end_time)->format('h:i a')
                ]
            ];
        } else {
            return [
                'first_half' => [
                    'start_time' => '09:00 am',
                    'end_time' => '12:59 pm'
                ],
                'second_half' => [
                    'start_time' => '01:00 pm',
                    'end_time' => '06:00 pm'
                ]
            ];
        }
    }

    /**
     * @param $business_member_joining_date
     * @param $start_date
     * @param $end_date
     * @return bool
     */
    private function checkJoiningDate($business_member_joining_date, $start_date, $end_date)
    {
        if (!$business_member_joining_date) return false;
        if ($business_member_joining_date->format('d') == self::FIRST_DAY_OF_MONTH) return false;
        return $business_member_joining_date->format('Y-m-d') >= $start_date && $business_member_joining_date->format('Y-m-d') <= $end_date;
    }

    /**
     * @param $attendances
     * @param string $sort
     * @return mixed
     */
    private function attendanceSortOnOvertime($attendances, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return $attendances->$sort_by(function ($attendance, $key) {
            return $attendance['overtime_in_minutes'];
        });
    }

    /**
     * @param $attendances
     * @param string $sort
     * @return mixed
     */
    private function attendanceCustomSortOnOvertime($attendances, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return $attendances->$sort_by(function ($attendance, $key) {
            return $attendance['attendance']['overtime_in_minutes'];
        });
    }

    /**
     * @param $month
     * @param $year
     * @return bool
     */
    private function isShowRunningMonthsAttendance($year, $month)
    {
        return (Carbon::now()->month == (int)$month && Carbon::now()->year == (int)$year);
    }
}
