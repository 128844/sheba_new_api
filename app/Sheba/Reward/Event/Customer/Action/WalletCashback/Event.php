<?php namespace Sheba\Reward\Event\Customer\Action\WalletCashback;

use App\Models\Job;
use App\Models\PartnerOrderPayment;

use Sheba\Reward\AmountCalculator;
use Sheba\Reward\Event\Action;
use Sheba\Reward\Exception\ParameterTypeMismatchException;
use Sheba\Reward\Exception\RulesTypeMismatchException;
use Sheba\Reward\Event\Rule as BaseRule;

class Event extends Action implements AmountCalculator
{
    public function setRule(BaseRule $rule)
    {
        if (!($rule instanceof Rule))
            throw new RulesTypeMismatchException("Wallet cashback event must have a cashback event rule");

        return parent::setRule($rule);
    }

    /**
     * @return bool
     */
    public function isEligible()
    {
        return $this->rule->check($this->params) && $this->filterConstraints();
    }

    private function filterConstraints()
    {
        $category_id = $this->params[1]->order->lastJob()->category_id;

        foreach ($this->reward->constraints->groupBy('constraint_type') as $key => $type) {
            $ids = $type->pluck('constraint_id')->toArray();

            if ($key == 'App\Models\Category') {
                return in_array($category_id, $ids);
            }
        }

        return true;
    }

    /**
     * @return float|int|mixed
     */
    public function calculateAmount()
    {
        $payment_amount = $this->params[0];
        $amount = ($payment_amount * $this->reward->amount) / 100;

        return ($this->reward->cap && ($amount > $this->reward->cap)) ? $this->reward->cap : $amount;
    }
}