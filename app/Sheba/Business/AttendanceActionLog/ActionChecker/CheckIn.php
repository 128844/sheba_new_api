<?php namespace Sheba\Business\AttendanceActionLog\ActionChecker;

use Carbon\Carbon;
use Sheba\Dal\AttendanceActionLog\Actions;

class CheckIn extends ActionChecker
{
    CONST ENTRY_TIME = '9:30:00';

    public function getActionName()
    {
        return Actions::CHECKIN;
    }

    public function check()
    {
        parent::check(); // TODO: Change the autogenerated stub
        $this->checkForLateAction();
    }

    protected function setAlreadyHasActionForTodayResponse()
    {
        $this->setResult(ActionResultCodes::ALREADY_CHECKED_IN, ActionResultCodeMessages::ALREADY_CHECKED_IN);
    }

    protected function setSuccessfulResponseMessage()
    {
        $this->setResult(ActionResultCodes::SUCCESSFUL, ActionResultCodeMessages::SUCCESSFUL_CHECKIN);
    }

    protected function checkForLateAction()
    {
        if (!$this->isSuccess()) return;
        if (Carbon::now() > Carbon::parse(self::ENTRY_TIME)) $this->setResult(ActionResultCodes::LATE_TODAY, ActionResultCodeMessages::LATE_TODAY);
    }
}
