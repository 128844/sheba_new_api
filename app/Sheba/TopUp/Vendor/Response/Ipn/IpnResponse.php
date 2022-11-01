<?php namespace Sheba\TopUp\Vendor\Response\Ipn;

use App\Models\TopUpOrder;
use Sheba\TopUp\TopUpLifecycleManager;
use Throwable;

abstract class IpnResponse
{
    /** @var TopUpLifecycleManager */
    protected $topUp;
    /** @var array $response */
    protected $response;
    /** @var TopUpOrder */
    protected $topUpOrder;

    public function __construct(TopUpLifecycleManager $top_up)
    {
        $this->topUp = $top_up;
    }

    public function setResponse($response)
    {
        if (is_object($response)) $response = json_decode(json_encode($response), true);

        $this->response = $response;
    }

    public function getTransactionDetails(): array
    {
        return $this->response;
    }

    public function getResponse(): array
    {
        return $this->response;
    }

    public function setTopUpOrder(TopUpOrder $order): IpnResponse
    {
        $this->topUpOrder = $order;
        return $this;
    }

    public function getTopUpOrder(): TopUpOrder
    {
        if (is_null($this->topUpOrder)) $this->topUpOrder = $this->findTopUpOrder();
        return $this->topUpOrder;
    }

    abstract protected function findTopUpOrder(): TopUpOrder;

    abstract public function isFailed();

    /**
     * @throws Throwable
     * @return void
     */
    public function handleTopUp()
    {
        $this->topUp->setTopUpOrder($this->getTopUpOrder());
        $this->_handleTopUp();
    }

    /**
     * @throws Throwable
     * @return void
     */
    abstract protected function _handleTopUp();
}
