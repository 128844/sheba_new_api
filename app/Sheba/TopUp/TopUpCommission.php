<?php namespace Sheba\TopUp;

use App\Models\Affiliate;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\TopUpOrder;
use App\Models\TopUpVendor;
use App\Models\TopUpVendorCommission;
use App\Sheba\Transactions\Wallet\RobiTopUpWalletTransactionHandler;
use Sheba\Dal\TopUpOTFSettings\Model as TopUpOTFSettings;
use Sheba\FraudDetection\TransactionSources;
use Sheba\ModificationFields;
use Sheba\TopUp\Exception\InvalidSubscriptionWiseCommission;
use Sheba\Transactions\Types;
use Sheba\Transactions\Wallet\HasWalletTransaction;
use Sheba\Transactions\Wallet\WalletTransactionHandler;
use Sheba\Dal\TopUpOTFSettings\Contract as TopUpOTFSettingsRepo;
use Sheba\Dal\TopUpVendorOTF\Contract as TopUpVendorOTFRepo;
use Sheba\Dal\SubscriptionWisePaymentGateway\Model as SubscriptionWisePaymentGateway;

abstract class TopUpCommission
{
    use ModificationFields;

    /** @var TopUpOrder */
    protected $topUpOrder;
    /** @var TopUpAgent */
    protected $agent;
    /** @var TopUpVendor */
    protected $vendor;
    /** @var TopUpVendorCommission */
    protected $vendorCommission;
    protected $amount;
    protected $transaction;
    protected $subscriptionWiseTopUpCommission;

    /**
     * @param TopUpOrder $top_up_order
     * @return $this
     */
    public function setTopUpOrder(TopUpOrder $top_up_order)
    {
        $this->topUpOrder = $top_up_order;
        $this->amount = $this->topUpOrder->amount;
        $this->setAgent($top_up_order->agent)->setTopUpVendor($top_up_order->vendor)->setVendorCommission();

        unset($top_up_order->agent);
        unset($top_up_order->vendor);

        return $this;
    }

    /**
     * @return TopUpCommission
     */
    protected function setVendorCommission()
    {
        $commissions = $this->vendor->commissions()->where('type', get_class($this->agent));
        $commissions_copy = clone $commissions;
        $commission_of_individual = $commissions_copy->where('type_id', $this->agent->id)->first();
        $this->vendorCommission = $commission_of_individual ?: $commissions->whereNull('type_id')->first();
        return $this;
    }

    /**
     * @param TopUpVendor $top_up_order
     * @return TopUpCommission
     */
    protected function setTopUpVendor(TopUpVendor $top_up_order)
    {
        $this->vendor = $top_up_order;
        return $this;
    }

    /**
     * @param TopUpAgent $agent
     * @return TopUpCommission
     */
    protected function setAgent(TopUpAgent $agent)
    {
        $this->agent = $agent;
        return $this;
    }

    abstract public function disburse();

    abstract public function refund();

    protected function storeAgentsCommission()
    {
        try {
            if($this->agent instanceof Partner) $this->setSubscriptionWiseCommission();
            $otf_details = $this->getVendorOTFDetails($this->topUpOrder->vendor_id, $this->topUpOrder->amount, $this->topUpOrder->gateway, $this->topUpOrder->payee_mobile_type);
            $this->topUpOrder->agent_commission = $this->calculateCommission($this->topUpOrder->amount);
        } catch (InvalidSubscriptionWiseCommission $exception) {
            $otf_details = $this->getVendorOTFDetails($this->topUpOrder->vendor_id, $this->topUpOrder->amount, $this->topUpOrder->gateway, $this->topUpOrder->payee_mobile_type, true);
            $this->topUpOrder->agent_commission = $this->getDefaultCommission($this->topUpOrder->amount);
            logError($exception);
        }

        $this->topUpOrder->otf_id = $otf_details['otf_id'] ?? 0;
        $this->topUpOrder->otf_agent_commission = $this->topUpOrder->otf_id ? $otf_details['agent_commisssion'] : 0;
        $this->topUpOrder->otf_sheba_commission = $this->topUpOrder->otf_id ? $otf_details['sheba_commisssion'] : 0;
        $this->topUpOrder->agent_commission = $this->topUpOrder->otf_agent_commission > 0 ? 0 : $this->topUpOrder->agent_commission;

        $this->topUpOrder->save();

        if ($this->topUpOrder->otf_agent_commission > 0) {
            $log_message = $this->amount . "tk " . $otf_details['otf_name'] . " - OTF TopUp has been recharged to " . $this->topUpOrder->payee_mobile;
        } else {
            $log_message = $this->getBasicTopUpLog();
        }

        $commission_amount = $this->topUpOrder->otf_agent_commission > 0 ? $this->topUpOrder->otf_agent_commission : $this->topUpOrder->agent_commission;

        $transaction = (new TopUpTransaction())
            ->setAmount($this->amount -  $commission_amount)
            ->setLog($log_message)
            ->setTopUpOrder($this->topUpOrder)
            ->setIsRobiTopUp($this->topUpOrder->isRobiWalletTopUp());
        $this->transaction = $this->agent->topUpTransaction($transaction);
    }

    /**
     * @param $amount
     * @return float|int
     */
    protected function calculateCommission($amount)
    {
        if($this->agent instanceof Partner)
            return (double)$amount * ($this->getSubscriptionWiseTopUpCommission() / 100);

        $commission = (double)$amount * ($this->getVendorAgentCommission() / 100);
        if ($this->agent instanceof Affiliate) {
            $cap = constants('AFFILIATE_REWARD')['TOP_UP']['AGENT']['cap'];
            return min($commission, $cap);
        }
        return $commission;
    }

    protected function getDefaultCommission($amount)
    {
        return (double)$amount * ($this->getVendorAgentCommission() / 100);
    }

    /**
     * @return float
     */
    private function getVendorAgentCommission(): float
    {
        return (double)$this->vendorCommission->agent_commission;
    }

    /**
     * @param $amount
     * @return float|int
     */
    protected function calculateAmbassadorCommission($amount)
    {
        $commission = (double)$amount * ($this->getVendorAmbassadorCommission() / 100);
        if ($this->agent instanceof Affiliate) {
            $cap = constants('AFFILIATE_REWARD')['TOP_UP']['AMBASSADOR']['cap'];
            return min($commission, $cap);
        }
        return $commission;
    }

    /**
     * @return float
     */
    private function getVendorAmbassadorCommission()
    {
        return (double)$this->vendorCommission->ambassador_commission;
    }

    protected function refundAgentsCommission()
    {
        $this->setModifier($this->agent);
        $amount = $this->topUpOrder->amount;
        try {
            if ($this->agent instanceof Partner) $this->setSubscriptionWiseCommission();
            $commission = $this->calculateCommission($amount);
        } catch (InvalidSubscriptionWiseCommission $exception) {
            logError($exception);
            $commission = $this->getDefaultCommission($amount);
        }

        $otf_commission          = $this->topUpOrder->otf_agent_commission ?? 0;
        $amount_after_commission = round($amount - $commission - $otf_commission, 2);
        $log                     = "Your recharge TK $amount to {$this->topUpOrder->payee_mobile} has failed, TK $amount_after_commission is refunded in your account.";
        $this->transaction       = $this->refundUser($amount_after_commission, $log,$this->topUpOrder->isRobiWalletTopUp());
    }

    private function refundUser($amount, $log, $isRobiTopUp=false)
    {
        if ($amount == 0) return 0;

        /** @var HasWalletTransaction $model */
        $model = $this->agent;

        if (!$isRobiTopUp)
            return (new WalletTransactionHandler())
                ->setModel($model)
                ->setSource(TransactionSources::TOP_UP)
                ->setType(Types::credit())
                ->setAmount($amount)
                ->setLog($log)
                ->store();
        else {
            (new RobiTopupWalletTransactionHandler())
                ->setModel($model)
                ->setAmount($amount)
                ->setLog($log)
                ->setType(Types::credit())
                ->store();
        }

    }

    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * @param $vendor_id
     * @param $amount
     * @param $gateway
     * @param $con_type
     * @param bool $default_for_partner
     * @return array
     */
    private function getVendorOTFDetails($vendor_id, $amount, $gateway, $con_type,  $default_for_partner = false): array
    {
        if (!$this->isAgentEligibleForOtf()) return [];

        $otf_settings = app(TopUpOTFSettingsRepo::class);
        $otf_repo = app(TopUpVendorOTFRepo::class);
        $otf_setting = $otf_settings->builder()->where([
            ['topup_vendor_id', $vendor_id],
            ['type', get_class($this->agent)]
        ])->first();

        if (! ($otf_setting && $this->isGatewayEligibleForOtf($otf_setting, $gateway)) ) return [];


        $otf = $otf_repo->builder()->where('topup_vendor_id', $vendor_id)
            ->where('amount', $amount)->where('sim_type', 'like', '%' . $con_type . '%')
            ->where('status', 'Active')->first();

        if(!$otf) return [];

        if($this->agent instanceof Partner && !$default_for_partner)
            $agent_otf_commission = $this->getSubscriptionWiseOTFCommission();
        else $agent_otf_commission = $otf_setting->agent_commission;

        $agent_commission = round(($agent_otf_commission/ 100) * $otf->cashback_amount, 2);
        return [
            'otf_id' => $otf->id,
            'agent_commisssion' => $agent_commission,
            'sheba_commisssion' => $otf->cashback_amount - $agent_commission,
            'otf_name' => $otf->name_en
        ];
    }

    private function isAgentEligibleForOtf()
    {
        return $this->agent instanceof Affiliate ||
            $this->agent instanceof Partner ||
            $this->agent instanceof Customer;
    }

    private function isGatewayEligibleForOtf(TopUpOTFSettings $otf_setting, $gateway)
    {
        return $otf_setting->applicable_gateways != 'null' &&
            in_array($gateway, json_decode($otf_setting->applicable_gateways));
    }

    /**
     * @throws InvalidSubscriptionWiseCommission
     */
    public function setSubscriptionWiseCommission()
    {
        /** @var Partner $partner */
        $partner = $this->agent;
        /** @var SubscriptionWisePaymentGateway $gateway_charges */
        $gateway_charges = $partner->subscription->validPaymentGatewayAndTopUpCharges;

        $topup_charges = json_decode($gateway_charges->topup_charges);
        foreach ($topup_charges as $charge)
            if(strtolower($charge->key) == strtolower($this->topUpOrder->vendor->name))
                $subscription_wise_commission = $charge;
        if(isset($subscription_wise_commission)) $this->subscriptionWiseTopUpCommission = $subscription_wise_commission;
        else throw new InvalidSubscriptionWiseCommission();
    }

    public function getSubscriptionWiseOTFCommission()
    {
        return $this->subscriptionWiseTopUpCommission->otf_commission;
    }

    public function getSubscriptionWiseTopUpCommission()
    {
        return $this->subscriptionWiseTopUpCommission->commission;
    }

    protected function getBasicTopUpLog()
    {
        return $this->amount . " has been topped up to " . $this->topUpOrder->payee_mobile . ".";
    }
}
