<?php namespace App\Http\Controllers\Employee;

use App\Http\Presenters\EmployeeActionPresenter;
use App\Sheba\Business\BusinessBasicInformation;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Sheba\Business\Attendance\AttendanceCommonInfo;
use Sheba\Business\Employee\AttendanceActionChecker;
use Sheba\Dal\AttendanceActionLog\RemoteMode;
use Sheba\Dal\BusinessHoliday\Contract as BusinessHolidayRepoInterface;
use Sheba\Business\AttendanceActionLog\ActionChecker\ActionProcessor;
use Sheba\Dal\Attendance\Contract as AttendanceRepoInterface;
use Sheba\Business\AttendanceActionLog\AttendanceAction;
use App\Transformers\Business\AttendanceTransformer;
use App\Sheba\Business\Attendance\Note\Updater as AttendanceNoteUpdater;
use Sheba\Dal\Attendance\Model as Attendance;
use Sheba\Dal\BusinessOffice\Contract as BusinessOffice;
use Sheba\Dal\AttendanceActionLog\Actions;
use App\Transformers\CustomSerializer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use League\Fractal\Resource\Item;
use App\Models\BusinessMember;
use Sheba\Dal\BusinessWeekendSettings\BusinessWeekendSettingsRepo;
use Sheba\Dal\ShiftAssignment\ShiftAssignmentRepository;
use Sheba\ModificationFields;
use Illuminate\Http\Request;
use Sheba\Helpers\TimeFrame;
use League\Fractal\Manager;
use App\Models\Business;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    use ModificationFields, BusinessBasicInformation;

    const FIRST_DAY_OF_MONTH = 1;

    /**
     * @param Request $request
     * @param AttendanceRepoInterface $attendance_repo
     * @param TimeFrame $time_frame
     * @param BusinessHolidayRepoInterface $business_holiday_repo
     * @param BusinessWeekendSettingsRepo $business_weekend_settings_repo
     * @return JsonResponse
     */
    public function index(Request $request, AttendanceRepoInterface $attendance_repo, TimeFrame $time_frame, BusinessHolidayRepoInterface $business_holiday_repo,
                          BusinessWeekendSettingsRepo $business_weekend_settings_repo, BusinessOffice $business_office)
    {
        $this->validate($request, ['year' => 'required|string', 'month' => 'required|string']);
        $year = $request->year;
        $month = $request->month;
        $business_member = $this->getBusinessMember($request);
        if (!$business_member) return api_response($request, null, 404);
        $time_frame = $time_frame->forAMonth($month, $year);
        $business_member_created_date = $business_member->created_at;
        $business_member_joining_date = $business_member->join_date;
        $is_new_joiner = false;
        if ($this->checkJoiningDate($business_member_joining_date, $month, $year)){
            $start_date = $business_member_joining_date;
            $end_date = Carbon::now()->month($month)->year($year)->lastOfMonth();
            $time_frame = $time_frame->forDateRange($start_date, $end_date);
            $is_new_joiner = true;
        }
        $business_member_leave = $business_member->leaves()->accepted()->between($time_frame)->get();
        $time_frame->end = $this->isShowRunningMonthsAttendance($year, $month) ? Carbon::now() : $time_frame->end;
        $attendances = $attendance_repo->getAllAttendanceByBusinessMemberFilteredWithYearMonth($business_member, $time_frame);

        $business_holiday = $business_holiday_repo->getAllByBusiness($business_member->business);
        $weekend_settings = $business_weekend_settings_repo->getAllByBusiness($business_member->business);

        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $resource = new Item($attendances, new AttendanceTransformer($time_frame, $is_new_joiner, $business_holiday, $weekend_settings, $business_member_leave, $business_office));
        $attendances_data = $manager->createData($resource)->toArray()['data'];

        return api_response($request, null, 200, [
            'attendance' => $attendances_data,
            'joining_date' => $business_member_joining_date ? $business_member_joining_date->format('Y-m-d') : $business_member_created_date->format('Y-m-d')
        ]);
    }

    private function checkJoiningDate($business_member_joining_date, $month, $year)
    {
        if (!$business_member_joining_date) return false;
        if ($business_member_joining_date->format('d') == self::FIRST_DAY_OF_MONTH) return false;
        return $business_member_joining_date->format('m-Y') === Carbon::now()->month($month)->year($year)->format('m-Y');
    }

    /**
     * @param Request $request
     * @param AttendanceAction $attendance_action
     * @return JsonResponse
     * @throws ValidationException
     */
    public function takeAction(Request $request, AttendanceAction $attendance_action, ShiftAssignmentRepository $shift_assignment_repository)
    {
        $validation_data = [
            'action' => 'required|string|in:' . implode(',', Actions::get()),
            'device_id' => 'string',
            'user_agent' => 'string',
            #'is_in_wifi_area' => 'required|numeric|in:0,1'
        ];

        $business_member = $this->getBusinessMember($request);
        /** @var Business $business */
        $business = $this->getBusiness($request);
        if (!$business_member) return api_response($request, null, 404);

        Log::info("Attendance for Employee#$business_member->id, Request#" . json_encode($request->except(['profile', 'auth_info', 'auth_user', 'access_token'])));

        if ($request->is_geo_location_enable && !$request->is_in_wifi_area) {
            $validation_data += ['lat' => 'required|numeric', 'lng' => 'required|numeric'];
        }

        if (!$request->is_geo_location_enable && !$request->is_in_wifi_area && $business->isRemoteAttendanceEnable($business_member->id)) {
            $validation_data += ['remote_mode' => 'required|string|in:' . implode(',', RemoteMode::get())];
        }
        #$this->validate($request, $validation_data);
        $this->setModifier($business_member->member);
        $attendance_action->setBusinessMember($business_member)
            ->setAction($request->action)
            ->setBusiness($business_member->business)
            ->setDeviceId($request->device_id)
            ->setRemoteMode($request->remote_mode)
            ->setLat($request->lat)
            ->setLng($request->lng)
            ->setShiftAssignmentId($request->shift_assignment_id);
        $action = $attendance_action->doAction();

        $result = $action->getResult();

        return response()->json([
            'code' => $result->getCode(),
            'is_note_required' => (int) $result->isNoteRequired(),
            'date' => Carbon::now()->format('jS F Y'),
            'message' => $result->getMessage($request->action)
        ]);
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

    public function getTodaysInfo(Request $request, AttendanceActionChecker $attendance_action_checker)
    {
        $attendance_action_checker->setBusinessMember($request->business_member);
        return api_response($request, null, 200, ['attendance' => (new EmployeeActionPresenter($attendance_action_checker))->toArray()]);
    }

    public function attendanceInfo(Request $request, AttendanceCommonInfo $attendance_common_info)
    {
        /** @var Business $business */
        $business = $this->getBusiness($request);
        $attendance_common_info->setLat($request->lat)->setLng($request->lng);
        $is_ip_attendance_enable = $business->isIpBasedAttendanceEnable();
        $is_in_wifi_area = $is_ip_attendance_enable ? $attendance_common_info->isInWifiArea($business) ? 1 : 0 : 0;
        $data = [
            'is_in_wifi_area' => $is_in_wifi_area,
            'which_office' => $is_in_wifi_area ? $attendance_common_info->whichOffice($business) : null,
            'address' => $attendance_common_info->getAddress()
        ];

        return api_response($request, null, 200, ['info' => $data]);
    }

    /**
     * @param Request $request
     * @param AttendanceNoteUpdater $note_updater
     * @return JsonResponse
     */
    public function storeNote(Request $request, AttendanceNoteUpdater $note_updater)
    {
        $validation_data = [
            'action' => 'required|string|in:' . implode(',', Actions::get()),
            'note' => 'required|string'
        ];
        $this->validate($request, $validation_data);

        /** @var BusinessMember $business_member */
        $business_member = $this->getBusinessMember($request);
        if (!$business_member) return api_response($request, null, 404);

        $note_updater->setBusinessMember($business_member)
            ->setAction($request->action)
            ->setNote($request->note)
            ->updateNote();
        return api_response($request, null, 200);
    }

}
