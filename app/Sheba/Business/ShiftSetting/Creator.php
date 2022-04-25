<?php namespace Sheba\Business\ShiftSetting;

use Sheba\Dal\BusinessShift\BusinessShiftRepository;

class Creator
{
    /*** @var BusinessShiftRepository  */
    private $businessShiftRepository;

    public function __construct()
    {
        $this->businessShiftRepository = app(BusinessShiftRepository::class);
    }

    /** @var Requester $shiftRequester */
    private $shiftRequester;

    public function setShiftRequester(Requester $shiftRequester)
    {
        $this->shiftRequester = $shiftRequester;
        return $this;
    }

    public function create()
    {
        $data = $this->makeData();
        $this->businessShiftRepository->create($data);
    }

    private function makeData()
    {
        return [
          'business_id' => $this->shiftRequester->getBusiness()->id,
            'name' => $this->shiftRequester->getName(),
            'start_time' => $this->shiftRequester->getStartTime(),
            'end_time' => $this->shiftRequester->getEndTime(),
            'checkin_grace_enable' => $this->shiftRequester->getIsCheckInGraceAllowed(),
            'checkout_grace_enable' => $this->shiftRequester->getIsCheckOutGraceAllowed(),
            'checkin_grace_time' => $this->shiftRequester->getCheckinGraceTime(),
            'checkout_grace_time' => $this->shiftRequester->getCheckOutGraceTime(),
            'is_halfday_enable' => $this->shiftRequester->getIsHalfDayActivated()
        ];
    }
}