<?php namespace Sheba\Business\Attendance;


use App\Models\BusinessMember;
use Carbon\Carbon;
use Sheba\Business\MyTeamDashboard\CommonFunctions;
use Sheba\Dal\Attendance\EloquentImplementation;
use Sheba\Dal\BusinessWeekendSettings\BusinessWeekendSettingsRepo;

class Creator
{
    /** @var BusinessMember */
    private $businessMember;
    private $date;
    /** @var EloquentImplementation */
    private $attendRepository;
    /** @var Carbon */
    private $now;
    private $shiftAssignment;
    /*** @var BusinessWeekendSettingsRepo */
    private $businessWeekendSettingsRepo;
    /*** @var CommonFunctions */
    private $commonFunctions;

    public function __construct(EloquentImplementation $attend_repository, BusinessWeekendSettingsRepo $businessWeekendSettingsRepo, CommonFunctions $common_functions)
    {
        $this->attendRepository = $attend_repository;
        $this->businessWeekendSettingsRepo = $businessWeekendSettingsRepo;
        $this->commonFunctions = $common_functions;
        $this->now = Carbon::now();
    }

    /**
     * @param BusinessMember $businessMember
     * @return $this
     */
    public function setBusinessMember(BusinessMember $businessMember)
    {
        $this->businessMember = $businessMember;
        return $this;
    }

    /**
     * @param mixed $date
     * @return Creator
     */
    public function setDate($date)
    {
        $this->date = $date;
        return $this;
    }

    public function setShiftAssignment($shift_assignment)
    {
        $this->shiftAssignment = $shift_assignment;
        return $this;
    }

    public function create()
    {
        return $this->attendRepository->create([
            'business_member_id' => $this->businessMember->id,
            'date' => $this->date,
            'shift_assignment_id' => $this->shiftAssignment ? $this->shiftAssignment->id : null,
            'checkin_time' => $this->now->format('H:i:s'),
            'is_off_day' => (int) $this->isOffDay()
        ]);
    }

    private function isOffDay(): bool
    {
        if ($this->shiftAssignment) return $this->shiftAssignment->is_unassigned;
        return $this->commonFunctions->setBusiness($this->businessMember->business)->isWeekendHoliday();
    }
}
