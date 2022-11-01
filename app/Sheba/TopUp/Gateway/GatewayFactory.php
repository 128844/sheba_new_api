<?php namespace Sheba\TopUp\Gateway;

use App\Models\TopUpOrder;
use InvalidArgumentException;
use Sheba\TopUp\Gateway\Pretups\Operator\Airtel;
use Sheba\TopUp\Gateway\Pretups\Operator\Robi;
use Sheba\TopUp\Gateway\Pretups\Operator\Banglalink;

class GatewayFactory
{
    /**
     * @param $name
     * @return Gateway
     */
    public static function getByName($name)
    {
        if ($name == Names::BANGLALINK) return app(Banglalink::class);
        if ($name == Names::ROBI) return app(Robi::class);
        if ($name == Names::AIRTEL) return app(Airtel::class);
        if ($name == Names::PAYWELL) return app(Paywell::class);
        if ($name == Names::BD_RECHARGE) return app(BdRecharge::class);
        if ($name == Names::PAY_STATION) return app(PayStation::class);
        return app(Ssl::class);
    }

    /**
     * @param $name
     * @return HasIpn
     */
    public static function getIpnGatewayByName($name): HasIpn
    {
        if ($name == Names::SSL) return app(Ssl::class);
        if ($name == Names::BD_RECHARGE) return app(BdRecharge::class);
        if ($name == Names::PAY_STATION) return app(PayStation::class);

        throw new InvalidArgumentException("$name does not support ipn.");
    }

    /**
     * @param TopUpOrder $order
     * @return Gateway
     */
    public static function getByOrder(TopUpOrder $order)
    {
        return self::getByName($order->gateway);
    }
}
