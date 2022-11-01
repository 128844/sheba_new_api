<?php namespace Sheba\TopUp\Vendor\Response\Ipn\PayStation;

use App\Models\TopUpOrder;
use Sheba\TopUp\Gateway\Names;

trait PayStationIpnResponse
{
    public function findTopUpOrder(): TopUpOrder
    {
        return TopUpOrder::gateway(Names::PAY_STATION)
            ->where('id', TopUpOrder::getIdFromUniformGatewayRefId($this->response['refer_no']))
            ->first();
    }
}