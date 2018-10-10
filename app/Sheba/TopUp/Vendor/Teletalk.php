<?php

namespace Sheba\TopUp\Vendor;

use Sheba\TopUp\Vendor\Internal\Ssl;
use Sheba\TopUp\Vendor\Response\TopUpResponse;

class Teletalk extends Vendor
{
    private $ssl;

    public function __construct(Ssl $ssl)
    {
        $this->ssl = $ssl;
    }

    public function recharge($mobile_number, $amount, $type): TopUpResponse
    {
        return $this->ssl->recharge($mobile_number, $amount, $type);
    }
}