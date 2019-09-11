<?php namespace Sheba\Subscription\Partner;

use App\Jobs\PartnerRenewalSMS;
use App\Models\Partner;
use App\Models\PartnerSubscriptionPackage;
use App\Models\Tag;
use App\Sheba\Subscription\Partner\PartnerSubscriptionCharges;
use Carbon\Carbon;
use DB;
use Exception;
use Sheba\PartnerWallet\PartnerTransactionHandler;
use Sheba\PartnerWallet\PaymentByBonusAndWallet;
use Sheba\SmsHandler;

class PartnerSubscriptionBilling
{
    /** @var Partner $partner */
    public $partner;
    public $runningCycleNumber;
    private $partnerTransactionHandler;
    public $partnerBonusHandler;
    public $today;
    public $refundAmount;
    public $packagePrice;
    public $packageFrom;
    public $packageTo;
    private $isCollectAdvanceSubscriptionFee = false;

    /**
     * PartnerSubscriptionBilling constructor.
     * @param Partner $partner
     */
    public function __construct(Partner $partner)
    {
        $this->partner = $partner;
        $this->partnerTransactionHandler = new PartnerTransactionHandler($this->partner);
        $this->partnerBonusHandler = new PaymentByBonusAndWallet($this->partner, $this->partner->subscription);
        $this->today = Carbon::today();
        $this->refundAmount = 0;
        $this->isCollectAdvanceSubscriptionFee = $this->partner->isAlreadyCollectedAdvanceSubscriptionFee();
    }

    public function runUpfrontBilling()
    {
        $this->runningCycleNumber = 1;
        $this->partner->billing_start_date = $this->today;
        $this->packagePrice = $this->getSubscribedPackageDiscountedPrice();
        $this->billingDatabaseTransactions($this->packagePrice);
        if (!$this->isCollectAdvanceSubscriptionFee) {
            (new PartnerSubscriptionCharges($this))->shootLog(constants('PARTNER_PACKAGE_CHARGE_TYPES')['Renewed']);
        }
    }

    public function runSubscriptionBilling()
    {
        $this->runningCycleNumber = $this->calculateRunningBillingCycleNumber();
        $this->packagePrice = $this->getSubscribedPackageDiscountedPrice();
        $this->billingDatabaseTransactions($this->packagePrice);
        if (!$this->isCollectAdvanceSubscriptionFee) {
            (new PartnerSubscriptionCharges($this))->shootLog(constants('PARTNER_PACKAGE_CHARGE_TYPES')['Renewed']);
        }
        dispatch((new PartnerRenewalSMS($this->partner))->setPackage($this->partner->subscription)->setSubscriptionAmount($this->packagePrice));
    }

    /**
     * @param PartnerSubscriptionPackage $old_package
     * @param PartnerSubscriptionPackage $new_package
     * @param $old_billing_type
     * @param $new_billing_type
     * @param $discount_id
     * @throws Exception
     */
    public function runUpgradeBilling(PartnerSubscriptionPackage $old_package, PartnerSubscriptionPackage $new_package, $old_billing_type, $new_billing_type, $discount_id)
    {

        $discount = 0;
        $this->packageFrom = $old_package;
        $this->packageTo = $new_package;
        dd($old_package);
        $remaining_credit = $this->remainingCredit($old_package, $old_billing_type);
        if ($discount_id) $discount = $new_package->discountPriceFor($discount_id);
        $this->packagePrice = ($new_package->originalPrice($new_billing_type) - $discount) - $remaining_credit;
        if ($this->packagePrice < 0) {
            $this->refundRemainingCredit(abs($this->packagePrice));
            $this->packagePrice = 0;
        }
        $this->partner->billing_start_date = $this->today;
        $this->billingDatabaseTransactions($this->packagePrice);
        if (!$this->isCollectAdvanceSubscriptionFee) {
            (new PartnerSubscriptionCharges($this))->shootLog(constants('PARTNER_PACKAGE_CHARGE_TYPES')[$this->findGrade($new_package, $old_package)]);
        }
        $this->sendSmsForSubscriptionUpgrade($old_package, $new_package, $old_billing_type, $new_billing_type);
    }

    public function runAdvanceSubscriptionBilling()
    {
        $this->runningCycleNumber = $this->calculateRunningBillingCycleNumber();
        $this->packagePrice = $this->getSubscribedPackageDiscountedPrice();
        $this->advanceBillingDatabaseTransactions($this->packagePrice);
        (new PartnerSubscriptionCharges($this))->shootLog(constants('PARTNER_PACKAGE_CHARGE_TYPES')['Renewed']);
    }
    
    private function calculateRunningBillingCycleNumber()
    {
        if (!$this->partner->billing_start_date) return 1;
        if ($this->partner->billing_type == BillingType::MONTHLY) {
            $diff = $this->today->month - $this->partner->billing_start_date->month;
            $yearDiff = ($this->today->year - $this->partner->billing_start_date->year);
            return $diff + ($yearDiff * 12) + 1;
        } elseif ($this->partner->billing_type == BillingType::YEARLY) {
            return ($this->today->year - $this->partner->billing_start_date->year) + 1;
        } elseif ($this->partner->billing_type == BillingType::HALF_YEARLY) {
            return round((($this->today->year - $this->partner->billing_start_date->year) + 1) / 2, 0);
        } else {
            return 1;
        }
    }

    private function getSubscribedPackageDiscountedPrice()
    {
        $original_price = $this->partner->subscription->originalPrice($this->partner->billing_type);
        $discount = $this->calculateSubscribedPackageDiscount($this->runningCycleNumber, $original_price);
        return $original_price - $discount;
    }

    /**
     * @param $package_price
     */
    private function billingDatabaseTransactions($package_price)
    {
        DB::transaction(function () use ($package_price) {
            if (!$this->isCollectAdvanceSubscriptionFee) $this->partnerTransactionForSubscriptionBilling($package_price);
            $this->partner->last_billed_date = $this->today;
            $this->partner->last_billed_amount = $package_price;
            $this->partner->update();
        });
    }

    /**
     * @param $package_price
     */
    private function advanceBillingDatabaseTransactions($package_price)
    {
        DB::transaction(function () use ($package_price) {
            $this->partnerTransactionForSubscriptionBilling($package_price);
        });
    }

    /**
     * @param $package_price
     * @throws Exception
     */
    private function partnerTransactionForSubscriptionBilling($package_price)
    {
        $package_price = number_format($package_price, 2, '.', '');
        $this->partnerBonusHandler->pay($package_price, '%d BDT has been deducted for subscription package', [$this->getSubscriptionTag()->id]);
    }

    public function remainingCredit(PartnerSubscriptionPackage $old_package, $old_billing_type)
    {

        $dayDiff = $this->partner->last_billed_date ? $this->partner->last_billed_date->diffInDays($this->today) + 1 : 0;
        $used_credit = $old_package->originalPricePerDay($old_billing_type) * $dayDiff;
        $remaining_credit = ($this->partner->last_billed_amount?:0) - $used_credit;
        return $remaining_credit < 0 ? 0 : $remaining_credit;
    }

    /**
     * @param $refund_amount
     * @throws Exception
     */
    private function refundRemainingCredit($refund_amount)
    {
        $refund_amount = number_format($refund_amount, 2, '.', '');
        $this->partnerTransactionHandler->credit($refund_amount, $refund_amount . ' BDT has been refunded due to subscription package upgrade', null, [$this->getSubscriptionTag()->id]);
        $this->refundAmount = $refund_amount;
    }

    /**
     * @param $running_bill_cycle_no
     * @param $original_price
     * @return float|int
     */
    private function calculateSubscribedPackageDiscount($running_bill_cycle_no, $original_price)
    {
        if ($this->partner->discount_id) {
            $subscription_discount = $this->partner->subscriptionDiscount;
            $discount_billing_cycles = json_decode($subscription_discount->applicable_billing_cycles);
            if (empty($discount_billing_cycles) || in_array($running_bill_cycle_no, $discount_billing_cycles)) {
                if ($subscription_discount->is_percentage) {
                    return $original_price * ($subscription_discount->amount / 100);
                } else {
                    return (double)$subscription_discount->amount;
                }
            }
        }
        return 0;
    }

    private function getSubscriptionTag()
    {
        return Tag::where('name', 'Subscription fee')->where('taggable_type', 'App\\Models\\PartnerTransaction')->first();
    }

    /**
     * @param PartnerSubscriptionPackage $old_package
     * @param PartnerSubscriptionPackage $new_package
     * @param $old_billing_type
     * @param $new_billing_type
     * @throws Exception
     */
    private function sendSmsForSubscriptionUpgrade(PartnerSubscriptionPackage $old_package, PartnerSubscriptionPackage $new_package, $old_billing_type, $new_billing_type)
    {
        if ((int)env('PARTNER_SUBSCRIPTION_SMS') == 1) {
            (new SmsHandler('upgrade-subscription'))->send($this->partner->getContactNumber(), [
                'old_package_name' => $old_package->show_name_bn,
                'new_package_name' => $new_package->show_name_bn,
                'subscription_amount' => $this->packagePrice,
                'old_package_type' => $old_billing_type,
                'new_package_type' => $new_billing_type
            ]);
        }
    }

    public function findGrade($new, $old)
    {
        if ($old->id < $new->id) {
            return 'Upgrade';
        } else if ($old->id > $new->id) {
            return 'Downgrade';
        } else {
            return 'Renewed';
        }
    }
}
