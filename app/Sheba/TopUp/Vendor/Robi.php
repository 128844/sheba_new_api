<?php namespace Sheba\TopUp\Vendor;

use Sheba\TopUp\Vendor\Internal\RobiAxiata;
use Sheba\TopUp\Vendor\Internal\Ssl;

class Robi extends Vendor
{
    /**
     * TEMPORARY ROBI/AIRTEL MOVE TO SSL
     */
    use Ssl;

    /*use RobiAxiata;

    private function getMid()
    {
        return config('topup.robi.robi_mid');
    }

    private function getPin()
    {
        return config('topup.robi.robi_pin');
    }*/
}