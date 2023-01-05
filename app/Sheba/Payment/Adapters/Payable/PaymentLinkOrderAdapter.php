<?php namespace App\Sheba\Payment\Adapters\Payable;

use App\Models\Payable;
use App\Models\PosOrder;
use App\Sheba\Pos\Order\PosOrderObject;
use Carbon\Carbon;
use Sheba\Dal\POSOrder\SalesChannels;
use Sheba\Payment\Adapters\Payable\PayableAdapter;
use Sheba\PaymentLink\PaymentLinkTransformer;

class PaymentLinkOrderAdapter implements PayableAdapter
{
    /** @var PaymentLinkTransformer */
    private $paymentLink;
    private $amount;
    private $payableUser;
    private $description;
    private $emiBankId = null;


    public function setPaymentLink(PaymentLinkTransformer $paymentLinkTransformer)
    {
        $this->paymentLink = $paymentLinkTransformer;
        return $this;
    }

    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    public function setDescription($purpose)
    {
        $this->description = $purpose;
        return $this;
    }

    /**
     * @param $user
     * @return $this
     */
    public function setPayableUser($user)
    {
        $this->payableUser = $user;
        return $this;
    }

    public function setEmiBankId($emiBankId)
    {
        $this->emiBankId = $emiBankId;

        return $this;
    }

    /**
     * @return Payable
     */
    public function getPayable(): Payable
    {
        $this->resolveDescription();
        $receiver                 = $this->paymentLink->getPaymentReceiver();
        $payable                  = new Payable();
        $payable->type            = 'payment_link';
        $payable->type_id         = $this->paymentLink->getLinkID();
        $payable->user_id         = $this->payableUser->id;
        $payable->user_type       = "App\\Models\\" . class_basename($this->payableUser);
        $payable->amount          = $this->getAmount();
        $payable->description     = $this->description;
        $payable->completion_type = "payment_link";
        $payable->success_url     = $this->resolveSuccessOrFailUrl();
        $payable->fail_url        = $this->resolveSuccessOrFailUrl();
        $payable->created_at      = Carbon::now();
        $payable->emi_month       = $this->paymentLink->getEmiMonth();
        $payable->emi_bank_id     = $this->emiBankId;
        $payable->created_by      = $receiver ? $receiver->id : 0;
        $payable->created_by_name = $receiver ? class_basename($receiver) : "";
        $payable->payee_id        = $receiver ? $receiver->id : null;
        $payable->payee_type      = $receiver ? strtolower(class_basename($receiver)) : null;
        $payable->save();
        return $payable;
    }

    private function getAmount()
    {
        $amount = $this->paymentLink->getAmount();
        return $amount ? (double)$amount : $this->amount;
    }

    private function resolveDescription()
    {
        $reason            = $this->paymentLink->getReason();
        $this->description = $reason ? $reason : $this->description;
    }

    public function setModelForPayable($model)
    {
        // TODO: Implement setModelForPayable() method.
    }

    public function setEmiMonth($month)
    {
        // TODO: Implement setEmiMonth() method.
    }

    public function canInit(): bool
    {
        return true;
    }

    private function resolveSuccessOrFailUrl(): string
    {
        $target = $this->paymentLink->getTarget();
        if ($target && $target instanceof PosOrderObject && $target->sales_channel == SalesChannels::WEBSTORE) {
            if ($target->is_migrated) {
                return config('sheba.new_webstore_url') . '/' . $target->partner->sub_domain . '/orders/' . $target->id . '/success';
            } else {
                return config('sheba.webstore_url') . '/' . $target->partner->sub_domain . '/redirect-after-payment/' . $target->id;
            }
        } else {
            return config('sheba.payment_link_web_url') . '/' . $this->paymentLink->getLinkIdentifier() . '/success';
        }
    }
}
