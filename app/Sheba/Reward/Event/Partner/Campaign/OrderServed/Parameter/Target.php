<?php namespace Sheba\Reward\Event\Partner\Campaign\OrderServed\Parameter;

use Illuminate\Database\Eloquent\Builder;

use Sheba\Reward\Event\CampaignEventParameter;
use Sheba\Reward\Event\EventTarget;
use Sheba\Reward\Exception\ParameterTypeMismatchException;

class Target extends CampaignEventParameter implements EventTarget
{
    private $achieved;

    public function validate()
    {
        if ((empty($this->value) && !is_numeric($this->value)) || is_null($this->value))
            throw new ParameterTypeMismatchException("Target can't be empty");
    }

    public function check(Builder $query)
    {
        $query->select('partner_orders.partner_id')->groupBy('partner_orders.partner_id')
            ->havingRaw('count(*) >=' . $this->value);
    }

    public function calculateProgress(Builder $query)
    {
        $this->achieved = $query->count();
    }

    public function getAchieved()
    {
        return $this->achieved;
    }

    public function getTarget()
    {
        return $this->value;
    }

    public function hasAchieved()
    {
        return $this->achieved >= $this->value;
    }
}