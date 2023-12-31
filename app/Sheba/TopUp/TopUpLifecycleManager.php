<?php

namespace Sheba\TopUp;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Sheba\Dal\TopupOrder\TopUpOrderRepository;
use Sheba\Reward\ActionRewardDispatcher;
use Sheba\ShebaPay\Clients\ShebaPayCallbackClient;
use Sheba\TopUp\Commission\Partner;
use Sheba\TopUp\Gateway\HasIpn;
use Sheba\TopUp\Vendor\Response\Ipn\FailResponse;
use Sheba\TopUp\Vendor\Response\Ipn\IpnResponse;
use Sheba\TopUp\Vendor\Response\Ipn\SuccessResponse;
use Throwable;

class TopUpLifecycleManager extends TopUpManager
{
    /**
     * @param FailResponse $fail_response
     * @throws Throwable
     */
    public function fail(FailResponse $fail_response)
    {
        if ($this->topUpOrder->isFailed()) {
            return;
        }

        $this->doTransaction(function () use ($fail_response) {
            $this->statusChanger->failed(FailDetails::buildFromIpnFailResponse($fail_response));
            if ($this->topUpOrder->isAgentDebited() && !$this->topUpOrder->isShebaPayOrder()) {
                $this->refund();
            }
            $this->getVendor()->refill($this->topUpOrder->amount);
        });
        if ($this->topUpOrder->isShebaPayOrder()) {
            (new ShebaPayCallbackClient($this->topUpOrder))->call();
        }
        // $this->sendPushNotification("দুঃখিত", "দুঃখিত, কারিগরি ত্রুটির কারনে " .$this->topUpOrder->payee_mobile. " নাম্বারে আপনার টপ-আপ রিচার্জ সফল হয়নি। অনুগ্রহ করে আবার চেষ্টা করুন।");
    }

    /**
     * @param SuccessResponse $success_response
     * @throws Throwable
     */
    public function success(SuccessResponse $success_response)
    {
        if ($this->topUpOrder->isSuccess()) {
            return;
        }

        $order_repo = app(TopUpOrderRepository::class);
        $vendor = $this->getVendor();
        /** @var TopUpCommission $commission */
        $commission = $this->topUpOrder->agent->getCommission();
        $this->doTransaction(function () use ($success_response, $order_repo, $vendor, &$commission) {
            $details = $success_response->getTransactionDetails();
            $id = $success_response->getUpdatedTransactionId();
            $this->topUpOrder = $this->statusChanger->successful($details, $id);
            if (!$this->topUpOrder->isAgentDebited() && !$this->topUpOrder->isShebaPayOrder()) {
                $commission->setTopUpOrder($this->topUpOrder)->disburse();
                $order_repo->update($this->topUpOrder, ['is_agent_debited' => 1]);
                $vendor->deductAmount($this->topUpOrder->amount);
                $this->logSuccessfulButAgentNotDebited($this->topUpOrder);
            }
        });
        if ($this->topUpOrder->isShebaPayOrder()) {
            (new ShebaPayCallbackClient($this->topUpOrder))->call();
        }
        if ($commission instanceof Partner && !$this->topUpOrder->isShebaPayOrder()) {
            $commission->storeTopUpJournal();
        }
        if ($this->topUpOrder->isSuccess()) {
            app()->make(ActionRewardDispatcher::class)->run('top_up', $this->topUpOrder->agent, $this->topUpOrder);
        }
    }

    /**
     * @return IpnResponse | void
     * @throws Throwable
     */
    public function reload()
    {
        if (!$this->topUpOrder->canRefresh()) {
            return;
        }
        $vendor = $this->getVendor();
        $response = $vendor->enquire($this->topUpOrder);
        $response->handleTopUp();
        return $response;
    }

    /**
     * @throws Throwable
     */
    public function handleIpn(HasIpn $gateway, $request_data)
    {
        $ipn_response = $gateway->buildIpnResponse($request_data);
        $ipn_response->setResponse($request_data);
        $ipn_response->handleTopUp();
        $this->logIpn($ipn_response, $request_data);
    }

    private function logIpn(IpnResponse $ipn_response, $request_data)
    {
        $key = 'Topup::' . ($ipn_response instanceof FailResponse ? "Failed:failed" : "Success:success") . "_";
        $key .= Carbon::now()->timestamp . '_' . $ipn_response->getTopUpOrder()->id;
        Redis::set($key, json_encode($request_data));
        Redis::expire($key, 60 * 60);
    }

    private function logSuccessfulButAgentNotDebited($topUpOrder)
    {
        $key = 'Topup::' . "AgentNotDebited:topup" . "_";
        $key .= Carbon::now()->timestamp . '_' . $topUpOrder->id;
        Redis::set($key, $topUpOrder->id);
    }
}
