<?php namespace Sheba\Analysis\PartnerPerformance;

use App\Models\Partner;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Sheba\Helpers\TimeFrame;

abstract class PartnerPerformance
{
    const CALCULATE_PREVIOUS_SLOT = 5;

    /** @var TimeFrame */
    protected $timeFrame;

    /** @var Partner */
    protected $partner;

    /** @var PartnerPerformance  */
    protected $next;

    /** @var Collection */
    protected $data;

    public function __construct(PartnerPerformance $next = null)
    {
        $this->next = $next;
    }

    public function setPartner(Partner $partner)
    {
        $this->partner = $partner;
        if($this->next) $this->next->setPartner($partner);
        return $this;
    }

    public function setTimeFrame(TimeFrame $time_frame)
    {
        $this->timeFrame = $time_frame;
        if($this->next) $this->next->setTimeFrame($time_frame);
        return $this;
    }

    public function calculate()
    {
        $this->data = $this->get();
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data->toArray();
    }

    /**
     * @return Collection
     */
    public function getObject()
    {
        return $this->data;
    }

    protected function isCalculatingWeekly()
    {
        return $this->timeFrame->end->diffInDays($this->timeFrame->start) < 10;
    }

    protected function isCalculatingCurrentDate()
    {
        return $this->timeFrame->hasDateBetween(Carbon::today());
    }

    /**
     * @return Collection
     */
    protected abstract function get();
}