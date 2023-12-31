<?php namespace Sheba\PaymentLink;

use App\Models\Partner;
use App\Models\PosCustomer;
use App\Sheba\Pos\Order\PosOrderObject;
use Carbon\Carbon;
use Sheba\Pos\Customer\PosCustomerObject;
use Sheba\Pos\Customer\PosCustomerResolver;
use Sheba\Pos\Order\PosOrderResolver;
use Sheba\Transactions\Wallet\HasWalletTransaction;
use Sheba\Dal\ExternalPayment\Model as ExternalPayment;
use stdClass;

class PaymentLinkTransformer
{
    private $response;
    private $target;

    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param stdClass $response
     * @return $this
     */
    public function setResponse(stdClass $response)
    {
        $this->response = $response;
        return $this;
    }

    public function getLinkID()
    {
        return $this->response->linkId;
    }

    public function getReason()
    {
        return $this->response->reason;
    }

    public function getLink()
    {
        return $this->response->link;
    }

    public function getType()
    {
        return $this->response->type;
    }

    public function getLinkIdentifier()
    {
        return $this->response->linkIdentifier;
    }

    public function getAmount()
    {
        return $this->response->amount;
    }

    public function getIsActive()
    {
        return $this->response->isActive;
    }

    public function getIsDefault()
    {
        return $this->response->isDefault;
    }

    public function getEmiMonth()
    {
        return isset($this->response->emiMonth) ? $this->response->emiMonth : null;
    }

    public function isEmi()
    {
        $month = $this->getEmiMonth();
        return !is_null($month) && $month > 0;
    }

    public function getInterest()
    {
        return isset($this->response->interest) ? $this->response->interest : null;
    }

    public function getBankTransactionCharge()
    {
        return isset($this->response->bankTransactionCharge) ? $this->response->bankTransactionCharge : null;
    }

    public function getRealAmount()
    {
        return $this->response->realAmount ?? null;
    }

    /**
     * @return HasWalletTransaction
     */
    public function getPaymentReceiver()
    {
        $model_name = "App\\Models\\" . ucfirst($this->response->userType);
        return $model_name::find($this->response->userId);
    }

    /**
     * @return null
     */
    public function getPayer()
    {
        $order = $this->getTarget();
        if ($order && $order instanceof ExternalPayment) return $this->getPaymentLinkPayer();
        if ($order && $order instanceof PosOrderObject) {
            /** @var PosCustomerResolver $posCustomerResolver */
            $posCustomerResolver = app(PosCustomerResolver::class);
            return $posCustomerResolver->setCustomerId($order->customer_id)->setPartner(Partner::find($order->partner_id))->get();
        }
        return $this->getPaymentLinkPayer();
    }

    /**
     * @return Target
     */
    public function getUnresolvedTarget()
    {
        return new Target($this->response->targetType, $this->response->targetId);
    }

    /**
     * @return mixed
     */
    public function getTarget()
    {
        if ($this->response->targetType) {
            $model_name = $this->resolveTargetClass();
            if ($model_name == 'due_tracker') return null;
            /** @var PosOrderResolver $posOrderResolver */
            $posOrderResolver = app(PosOrderResolver::class);
            if ($model_name == 'pos_order') return $posOrderResolver->setOrderId($this->response->targetId)->get();
            $this->target = $model_name::find($this->response->targetId);
            return $this->target;
        } else
            return null;
    }

    public function isDueTrackerPaymentLink()
    {
        return $this->response->targetType == 'due_tracker' ? 1 : 0;
    }

    private function resolveTargetClass()
    {
        if ($this->response->targetType == 'pos_order')
            return 'pos_order';
        if ($this->response->targetType == 'external_payment')
            return "Sheba\\Dal\\ExternalPayment\\Model";
        if ($this->response->targetType == 'due_tracker') return 'due_tracker';
    }

    /**
     * @return PosCustomerObject|null
     * @throws \Exception
     */
    private function getPaymentLinkPayer()
    {
        //TODO: Only Resolving PosCustomer
//        $model_name = "App\\Models\\";
        if (isset($this->response->payerId)) {
            /** @var PosCustomerResolver $posCustomerResolver */
            $posCustomerResolver = app(PosCustomerResolver::class);
            /** @var Partner $partner */
            $partner = $this->getPaymentReceiver();
            return $posCustomerResolver->setCustomerId($this->response->payerId)->setPartner($partner)->get();
//            $model_name = $model_name . pamelCase($this->response->payerType);
//            /** @var PosCustomer $customer */
//            $customer = $model_name::find($this->response->payerId);
//            return $customer ? $customer->profile : null;
        }
        return null;
    }

    public function isForMissionSaveBangladesh()
    {
        $receiver = $this->getPaymentReceiver();
        if ($receiver instanceof Partner) return false;
        /** @var Partner $receiver */
        return $receiver->isMissionSaveBangladesh();
    }

    public function isExternalPayment()
    {
        return !!($this->target instanceof ExternalPayment);
    }

    public function getSuccessUrl()
    {
        return $this->target->success_url . '?transaction_id=' . $this->target->transaction_id;
    }

    public function getFailUrl()
    {
        return $this->target->fail_url . '?transaction_id=' . $this->target->transaction_id;
    }

    public function getCreatedAt()
    {
        return Carbon::createFromTimestampMs($this->response->createdAt);
    }

    public function getPaidBy()
    {
        return isset($this->response->paidBy) ? $this->response->paidBy : PaymentLinkStatics::paidByTypes()[($this->getEmiMonth() ? 1 : 0)];
    }

    public function getPartnerProfit()
    {
        return isset($this->response->partnerProfit) ? $this->response->partnerProfit : 0;
    }

    public function toArray()
    {
        $user = $this->getPaymentReceiver();
        $payer = $this->getPayer();
        $isExternal = $this->isExternalPayment();
        return [
                'id' => $this->getLinkID(),
                'identifier' => $this->getLinkIdentifier(),
                'purpose' => $this->getReason(),
                'amount' => $this->getAmount(),
                'emi_month' => $this->getEmiMonth(),
                'paid_by' => $this->getPaidBy(),
                'partner_profit' => $this->getPartnerProfit(),
                'is_old' => $this->isOld(),
                'interest' => $this->getInterest(),
                'bank_transaction_fee' => $this->getBankTransactionCharge(),
                'payment_receiver' => [
                    'name' => $user->name,
                    'image' => $user->logo,
                    'id' => $user->id,
                ],
                'payer' => $payer ? [
                    'id' => $payer->id,
                    'name' => $payer->name,
                    'mobile' => $payer->mobile
                ] : null,
                'is_external_payment' => $isExternal,
                'installment_per_month' => $this->getInstallmentPerMonth()
            ] + ($isExternal ? ['success_url' => $this->getSuccessUrl(), 'fail_url' => $this->getFailUrl()] : []);

    }

    public function partialInfo()
    {
        $user = $this->getPaymentReceiver();
        return [
            'name' => $user->name,
            'mobile' => $user->getContactNumber()
        ];
    }

    public function getPaymentLinkData()
    {
        $payer = null;
        $payerInfo = $this->getPayerInfo();

        return array_merge([
            'link_id' => $this->getLinkID(),
            'reason' => $this->getReason(),
            'type' => $this->getType(),
            'status' => $this->response->isActive == 1 ? 'active' : 'inactive',
            'amount' => $this->getAmount(),
            'link' => $this->response->link,
            'emi_month' => $this->response->emiMonth,
            'interest' => $this->response->interest,
            'bank_transaction_charge' => $this->response->bankTransactionCharge
        ], $payerInfo);
    }

    private function getPayerInfo()
    {
        $payerInfo = [];
        if ($this->response->payerId) {
            try {
                /** @var PosCustomer $payer */
                $payer = app('App\\Models\\' . pamelCase($this->response->payerType))::find($this->response->payerId);
                $details = $payer ? $payer->details() : null;
                if ($details) {
                    $payerInfo = [
                        'payer' => [
                            'id' => $details['id'],
                            'name' => $details['name'],
                            'mobile' => $details['phone']
                        ]
                    ];
                }
            } catch (\Throwable $e) {
                app('sentry')->captureException($e);
            }
        }
        return $payerInfo;
    }

    public function isOld()
    {
        return !isset($this->response->paidBy);
    }

    public function getInstallmentPerMonth()
    {
        if ($this->getEmiMonth() > 0) {
            return round($this->getAmount() / $this->getEmiMonth(), 2);
        }
        return null;
    }


}
