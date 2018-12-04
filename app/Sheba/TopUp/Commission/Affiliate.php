<?php namespace App\Sheba\TopUp\Commission;

use App\Sheba\TopUp\TopUpCommission;

class Affiliate extends TopUpCommission
{

    private $topUpOrder;
    private $agent;
    private $vendor;
    private $amount;

    public function setAgent(TopUpAgent $agent)
    {
        $this->agent = $agent;
    }

    public function setTopUpOrder(TopUpOrder $topUpOrder)
    {
        $this->topUpOrder = $topUpOrder;
    }

    public function setTopUpVendor($topUpVendor)
    {
        $this->vendor = $topUpVendor;
    }

    public function disburse()
    {
        $this->topUpOrder->agent_commission =  $this->agent->calculateCommission($this->topUpOrder->amount, $this->vendor);
    }
}