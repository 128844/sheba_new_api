<?php


namespace App\Sheba\Reward\Event\Affiliate\Campaign\Topup\Parameter;


use Illuminate\Database\Eloquent\Builder;
use Sheba\Reward\Event\CampaignEventParameter;
use Sheba\Reward\Event\EventTarget;
use Sheba\Reward\Exception\ParameterTypeMismatchException;

class Target extends CampaignEventParameter implements EventTarget
{

    public function check(Builder $query)
    {
        // TODO: Implement check() method.
    }

    public function validate()
    {
        if ((empty($this->value) && !is_numeric($this->value)) || is_null($this->value))
            throw new ParameterTypeMismatchException("Target can't be empty");
    }

    public function calculateProgress(Builder $query)
    {
        // TODO: Implement calculateProgress() method.
    }

    public function getAchieved()
    {
        // TODO: Implement getAchieved() method.
    }

    public function setAchieved($achieved)
    {
        // TODO: Implement setAchieved() method.
    }

    public function getTarget()
    {
        // TODO: Implement getTarget() method.
    }

    public function hasAchieved()
    {
        // TODO: Implement hasAchieved() method.
    }
}