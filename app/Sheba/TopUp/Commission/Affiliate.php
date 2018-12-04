<?php namespace App\Sheba\TopUp\Commission;

use App\Models\TopUpOrder;
use App\Models\TopUpVendor;
use App\Sheba\TopUp\TopUpCommission;
use Sheba\TopUp\TopUpAgent;

class Affiliate extends TopUpCommission
{
    public function disburse()
    {
       $this->storeAgentsCommission();
    }
}