<?php namespace Sheba\Business\ShiftSetting;

use Carbon\Carbon;
use Sheba\Business\ShiftCalendar\Updater as ShiftCalendarUpdater;
use Sheba\Dal\ShiftAssignment\ShiftAssignmentRepository;
use Sheba\Dal\ShiftSettingLog\ShiftSettingLogRepository;
use Sheba\ModificationFields;
use Sheba\Business\ShiftSetting\ShiftAssign\Requester as ShiftCalendarRequest;

class Updater
{
    use ModificationFields;

    /** @var Requester $shiftRequester */
    private $shiftRequester;

    /*** @var ShiftAssignmentRepository */
    private $shiftAssignmentRepo;
    /*** @var ShiftSettingLogRepository */
    private $shiftSettingLogsRepo;
    /*** @var ShiftCalendarUpdater */
    private $shiftCalendarUpdater;

    public function __construct(
        ShiftSettingLogRepository $setting_logs_repo,
        ShiftAssignmentRepository $assignment_repo,
        ShiftCalendarUpdater $calendar_updater
    )
    {
        $this->shiftAssignmentRepo = $assignment_repo;
        $this->shiftSettingLogsRepo = $setting_logs_repo;
        $this->shiftCalendarUpdater = $calendar_updater;
    }

    public function setShiftRequester(Requester $shiftRequester)
    {
        $this->shiftRequester = $shiftRequester;
        return $this;
    }

    public function updateColor()
    {
        $this->shiftRequester->getShift()
            ->update($this->withUpdateModificationField([
                'color_code' => $this->shiftRequester->getColor()
            ]));

        $calendar_request = (new ShiftCalendarRequest())->setColorCode($this->shiftRequester->getColor());
        $this->updateShiftCalendar($calendar_request);
    }

    public function update()
    {
        $existing_shift = $this->shiftRequester->getShift();
        $previous_data = [
            'name' => $existing_shift->name,
            'title' => $existing_shift->title,
            'start_time' => $existing_shift->start_time,
            'checkin_grace_enable'  => $existing_shift->checkin_grace_enable,
            'checkin_grace_time'    => $existing_shift->checkin_grace_time,
            'end_time'  => $existing_shift->end_time,
            'checkout_grace_enable' => $existing_shift->checkout_grace_enable,
            'checkout_grace_time'   => $existing_shift->checkout_grace_time,
            'is_halfday_enable' => $existing_shift->is_halfday_enable
        ];
        $existing_shift->update($this->withUpdateModificationField([
            'name' => $this->shiftRequester->getName(),
            'title' => $this->shiftRequester->getTitle(),
            'start_time' => $this->shiftRequester->getStartTime(),
            'end_time' => $this->shiftRequester->getEndTime(),
            'checkin_grace_enable' => $this->shiftRequester->getIsCheckInGraceAllowed(),
            'checkout_grace_enable' => $this->shiftRequester->getIsCheckOutGraceAllowed(),
            'checkin_grace_time' => $this->shiftRequester->getCheckinGraceTime(),
            'checkout_grace_time' => $this->shiftRequester->getCheckOutGraceTime(),
            'is_halfday_enable' => $this->shiftRequester->getIsHalfDayActivated(),
        ]));
        $this->createShiftSettingsLogs($previous_data);
        $this->updateShiftCalendar($this->convertShiftRequstToCalendarRequest());
    }

    public function softDelete()
    {
        $this->shiftRequester->getShift()->delete();

        $calender_request = (new ShiftCalendarRequest())
            ->setIsUnassignedActivated(1)
            ->setIsGeneralActivated(0)
            ->setIsShiftActivated(0);

        $this->updateShiftCalendar($calender_request);
    }

    private function convertShiftRequstToCalendarRequest()
    {
        return (new ShiftCalendarRequest())
            ->setShiftName($this->shiftRequester->getName())
            ->setShiftTitle($this->shiftRequester->getTitle())
            ->setStartTime($this->shiftRequester->getStartTime())
            ->setEndTime($this->shiftRequester->getEndTime())
            ->setIsCheckinGraceEnable($this->shiftRequester->getIsCheckInGraceAllowed())
            ->setIsCheckoutGraceEnable($this->shiftRequester->getIsCheckOutGraceAllowed())
            ->setCheckinGraceTime($this->shiftRequester->getCheckinGraceTime())
            ->setCheckoutGraceTime($this->shiftRequester->getCheckOutGraceTime())
            ->setIsHalfDayActivated($this->shiftRequester->getIsHalfDayActivated());
    }

    private function updateShiftCalendar(ShiftCalendarRequest $calendar_request)
    {
        foreach ($this->getAssignments() as $assignment) {
            $this->shiftCalendarUpdater->update($assignment, $calendar_request);
        }
    }

    private function getAssignments()
    {
        return $this->shiftAssignmentRepo
            ->where('shift_id', $this->shiftRequester->getShift()->id)
            ->where('date', '>=', Carbon::now()->addDay())
            ->get();
    }

    private function createShiftSettingsLogs($previous_data)
    {
        $this->shiftSettingLogsRepo->create($this->withCreateModificationField([
            'shift_id' => $this->shiftRequester->getShift()->id,
            'old_settings' => json_encode($previous_data)
        ]));
    }

}
