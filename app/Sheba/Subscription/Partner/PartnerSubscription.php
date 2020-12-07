<?php namespace Sheba\Subscription\Partner;

use App\Models\Partner;
use App\Models\PartnerSubscriptionPackage;
use App\Models\PartnerSubscriptionUpdateRequest;
use Sheba\ModificationFields;
use Sheba\Subscription\Exceptions\InvalidPreviousSubscriptionRules;

class PartnerSubscription
{
    use ModificationFields;

    private $requested_package, $upgrade_request;

    private $notification = 0;

    /**
     * @var Partner
     */
    private $partner;

    public function setRequestedPackage($id = null)
    {
        $package_id = $id ? $id : config('sheba.partner_basic_packages_id');
        $this->requested_package = PartnerSubscriptionPackage::find($package_id);
        return $this;
    }

    public function setPartner($partner)
    {
        $this->partner = $partner;
        return $this;
    }

    public function setNotification($notification)
    {
        $this->notification = $notification;
        return $this;
    }

    /**
     * @param $resource
     * @return PartnerSubscription
     */
    public function createBasicSubscriptionRequest($resource)
    {
        $running_discount = $this->requested_package->runningDiscount(BillingType::MONTHLY);
        $this->setModifier($resource);
        $update_request_data = $this->withCreateModificationField([
            'partner_id' => $this->partner->id, 'old_package_id' => $this->partner->package_id ?: 1, 'new_package_id' => config('sheba.partner_basic_packages_id'), 'old_billing_type' => $this->partner->billing_type ?: BillingType::MONTHLY, 'new_billing_type' => BillingType::MONTHLY, 'discount_id' => $running_discount ? $running_discount->id : null
        ]);
        $this->upgrade_request = PartnerSubscriptionUpdateRequest::create($update_request_data);
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function updateSubscription()
    {
        if($hasCredit = $this->partner->hasCreditForSubscription($this->requested_package, BillingType::MONTHLY) && $this->upgrade_request)
            $this->partner->subscriptionUpgrade($this->requested_package, $this->upgrade_request, $this->notification);
    }

    /**
     * @param Partner $partner
     * @param $partner_subscription_package
     * @return array
     * @throws InvalidPreviousSubscriptionRules
     */
    public function formatCurrentPackageData(Partner $partner, $partner_subscription_package)
    {
        list($remaining, $wallet, $bonus_wallet, $threshold) = $partner->getCreditBreakdown();
        return [
            'current_package'            => $partner_subscription_package,
            'billing_type'               => $partner->billing_type,
            'last_billing_date'          => $partner->last_billed_date ? $partner->last_billed_date->format('Y-m-d') : null,
            'next_billing_date'          => $partner->next_billing_date ? $partner->next_billing_date: null,
            'validity_remaining_in_days' => $partner->last_billed_date ? $partner->periodicBillingHandler()->remainingDay() : null,
            'is_auto_billing_activated'  => ($partner->auto_billing_activated) ? true : false,
            'static_message'             => $partner_subscription_package->id === config('sheba.partner_lite_packages_id') ? 'প্রিমিয়াম প্যাকেজ গুলোর দুর্দান্ত সব ফিচার ব্যাবহার করে ২ গুন ব্যবসা বৃদ্ধি করুন কোন বাড়তি ঝামেলা ছাড়াই!' : '',
            'dynamic_message'            => "আপনি বর্তমানে বেসিক প্যাকেজ ব্যবহার করছেন। স্বয়ংক্রিয় নবায়ন এর জন্য ২২ নভেম্বের ৯০ টাকা বালান্স রাখুন।",
            'balance'                    => [
                'wallet'                 => $wallet + $bonus_wallet,
                'refund'                 => $remaining,
                'minimum_wallet_balance' => $threshold
            ]
        ];
    }

    public function dataFormat($package, Partner $partner = null)
    {
        $featured_package_id     = config('partner.subscription_featured_package_id');
        $package['rules']        = (json_decode($package->rules, 1));
        $package['is_published'] = $package->name == 'LITE' ? 0 : 1;
        $package['usps']         = $package->usps ? json_decode($package->usps) : ['usp' => [], 'usp_bn' => []];
        $package['features']     = $package->features ? json_decode($package->features) : [];
        $package['is_featured']  = in_array($package->id, $featured_package_id);

        if ($partner) {
            $package['is_subscribed']     = (int)($partner->package_id == $package->id);
            $package['subscription_type'] = ($partner->package_id == $package->id) ? $partner->billing_type : null;
        }
        removeRelationsAndFields($package);
    }
}