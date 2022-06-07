<?php namespace Sheba\Reward\Event\Affiliate\Action\TopUp\Parameter;

use Sheba\Reward\Event\ActionEventParameter;

class Operator extends ActionEventParameter
{
    public function validate(){}

    /**
     * @param array $params
     * @return bool
     */
    public function check(array $params): bool
    {
        if (is_null($this->value) || in_array("All", $this->value)) return true;

        $topup_order = $params[0];
        return in_array($topup_order->vendor->name, $this->value);
    }
}