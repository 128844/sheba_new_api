<?php

namespace Sheba\TopUp\Vendor\Internal;

use App\Models\TopUpVendor;
use Sheba\TopUp\TopUpRequest;
use Sheba\TopUp\Vendor\Response\TopUpResponse;
use Sheba\TopUp\Vendor\VendorFactory;

trait Ssl
{
    private $ssl;

    public function __construct(SslClient $ssl)
    {
        $this->ssl = $ssl;
    }

    /**
     * @param TopUpRequest $top_up_request
     * @return TopUpResponse
     * @throws \SoapFault
     */
    public function recharge(TopUpRequest $top_up_request): TopUpResponse
    {
        return $this->ssl->recharge($top_up_request);
    }

    public function getTopUpInitialStatus()
    {
        return config('topup.status.pending')['sheba'];
    }

    /**
     * @param $amount
     * @throws \Exception
     */
    public function deductAmount($amount)
    {
        TopUpVendor::whereIn('id', [VendorFactory::GP, VendorFactory::TELETALK])
            ->update(['amount' => $this->model->amount - $amount]);
    }

    public function refill($amount)
    {
        TopUpVendor::whereIn('id', [VendorFactory::GP, VendorFactory::TELETALK])
            ->increment('amount', $amount);
    }
}