<?php namespace Sheba\TopUp;

use App\Http\Validators\MobileNumberValidator;
use App\Models\Partner;
use App\Models\TopUpOrder;
use Sheba\TopUp\Vendor\Response\TopUpErrorResponse;
use Sheba\TopUp\Vendor\Response\TopUpWalletErrorResponse;
use Sheba\TopUp\Vendor\Vendor;

class TopUpValidator
{
    /** @var Vendor */
    private $vendor;
    /** @var TopUpAgent */
    private $agent;
    /** @var TopUpRequest */
    private $request;
    /** @var TopUpOrder */
    private $topUpOrder;
    /** @var TopUpErrorResponse */
    private $error;

    /**
     * @param TopUpAgent $agent
     * @return $this
     */
    public function setAgent(TopUpAgent $agent)
    {
        $this->agent = $agent;
        return $this;
    }

    /**
     * @param Vendor $model
     * @return $this
     */
    public function setVendor(Vendor $model)
    {
        $this->vendor = $model;
        return $this;
    }

    /**
     * @param TopUpRequest $request
     * @return $this
     */
    public function setRequest(TopUpRequest $request)
    {
        $this->request = $request;
        return $this;
    }

    public function setTopUpOrder(TopUpOrder $order)
    {
        $this->topUpOrder = $order;
        return $this;
    }

    public function validate()
    {
        $this->agent->reload();

        if (!$this->vendor->isPublished()) {
            $this->error = new TopUpErrorResponse();
            $this->error->errorCode = 421;
            $this->error->errorMessage = "Unsupported operator.";
        } elseif (!(new MobileNumberValidator())->validateBangladeshi($this->topUpOrder->payee_mobile)) {
            $this->error = new TopUpErrorResponse();
            $this->error->errorCode = 421;
            $this->error->errorMessage = "Invalid number.";
        } elseif ($this->topUpOrder->isRobiWalletTopUp() && $this->agent->robi_topup_wallet < $this->topUpOrder->amount) {
            $this->error = new TopUpWalletErrorResponse();
        } elseif (!$this->topUpOrder->isRobiWalletTopUp() && $this->agent->wallet < $this->topUpOrder->amount) {
            $this->error = new TopUpWalletErrorResponse();
        } elseif ($this->agent instanceof Partner) {
            if (!$this->topUpOrder->isShebaPayOrder()) {
                $withdrawalRequests = $this->agent->walletSetting->pending_withdrawal_amount;
                $remainingAmount = $this->agent->wallet - (float)$withdrawalRequests;

                if ($withdrawalRequests > 0 && $this->topUpOrder->amount > $remainingAmount) {
                    $this->error = new TopUpWalletErrorResponse();
                }
            }
        }

        return $this;
    }

    public function hasError()
    {
        return !empty($this->error);
    }

    public function getError()
    {
        return $this->error;
    }
}
