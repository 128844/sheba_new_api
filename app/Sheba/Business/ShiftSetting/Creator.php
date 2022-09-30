<?php namespace Sheba\Business\ShiftSetting;

use Sheba\Dal\BusinessShift\BusinessShiftRepository;

class Creator
{
    const DEFAULT_COLOR = '#F4C2C2';
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
            'title' => $this->shiftRequester->getTitle(),
            'start_time' => $this->shiftRequester->getStartTime(),
            'end_time' => $this->shiftRequester->getEndTime(),
            'checkin_grace_enable' => $this->shiftRequester->getIsCheckInGraceAllowed(),
            'checkout_grace_enable' => $this->shiftRequester->getIsCheckOutGraceAllowed(),
            'checkin_grace_time' => $this->shiftRequester->getCheckinGraceTime(),
            'checkout_grace_time' => $this->shiftRequester->getCheckOutGraceTime(),
            'is_halfday_enable' => $this->shiftRequester->getIsHalfDayActivated(),
            'color_code' => $this->getColorCode()
        ];
    }

    private function getColorCode()
    {
        $colors = config('b2b.SHIFT_COLORS');
        $existing_colors = $this->businessShiftRepository
            ->where('business_id', $this->shiftRequester->getBusiness()->id)
            ->groupBy('color_code')
            ->pluck('color_code')
            ->toArray();
        $unique_colors = array_diff($colors, $existing_colors);
        return empty($unique_colors) ? self::DEFAULT_COLOR : $unique_colors[array_rand($unique_colors)];
    }
}
