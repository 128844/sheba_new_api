<?php namespace Sheba\Subscription\Partner;


use App\Models\Partner;
use App\Models\PartnerSubscriptionPackage;
use App\Models\PartnerSubscriptionUpdateRequest;
use Illuminate\Support\Facades\DB;
use Sheba\ModificationFields;
use Sheba\Subscription\Exceptions\AlreadyRunningSubscriptionRequestException;

class PurchaseHandler
{
    use ModificationFields;

    /** @var Partner $partner */
    private $partner;
    /** @var PartnerSubscriptionPackage $newPackage */
    private $newPackage;
    private $newBillingType;
    private $modifier;
    private $grade;
    /** @var PartnerSubscriptionPackage $currentPackage */
    private $currentPackage;
    private $currentBillingType;
    /**
     * @var mixed|null
     */
    private $newPackagePrice;
    /**
     * @var mixed|null
     */
    private $runningDiscount;
    /**
     * @var array
     */
    private $balance;
    /**
     * @var PartnerSubscriptionUpdateRequest
     */
    private $newSubscriptionRequest;

    public function __construct(Partner $partner)
    {
        $this->setPartner($partner);
        $this->setCurrent();
    }

    /**
     * @param mixed $partner
     * @return PurchaseHandler
     */
    public function setPartner($partner)
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * @param PartnerSubscriptionPackage $newPackage
     * @return PurchaseHandler
     */
    public function setNewPackage(PartnerSubscriptionPackage $newPackage)
    {
        $this->newPackage = $newPackage;
        return $this;
    }

    /**
     * @param mixed $newBillingType
     * @return PurchaseHandler
     */
    public function setNewBillingType($newBillingType)
    {
        $this->newBillingType = $newBillingType;
        return $this;
    }

    public function setGrade()
    {
        $this->grade = $this->partner->subscriber()->getBilling()->findGrade($this->newPackage, $this->currentPackage, $this->newBillingType, $this->currentBillingType);
        return $this;
    }

    /**
     * @param mixed $modifier
     * @return PurchaseHandler
     */
    public function setModifier($modifier)
    {
        $this->modifier = $modifier;
        $this->setModifier($modifier);
        return $this;
    }

    private function setCurrent()
    {
        $this->currentPackage     = $this->partner->subscription;
        $this->currentBillingType = $this->partner->billing_type;
    }

    /**
     * @throws AlreadyRunningSubscriptionRequestException
     */
    private function checkIfRunning(){
        if ($this->currentPackage->id==$this->newPackage->id && $this->newBillingType==$this->currentBillingType)
            throw new AlreadyRunningSubscriptionRequestException("আপনি বর্তমানে {$this->currentPackage->name_bn} প্যকেজ ব্যবহার করছেন ,আপনার বর্তমান প্যকেজ এর মেয়াদ শেষ হলে স্বয়ংক্রিয়  ভাবে নবায়ন হয়ে যাবে");
    }
    /**
     * @return PurchaseHandler
     */
    public function createSubscriptionRequest()
    {
        $this->runningDiscount = $this->newPackage->runningDiscount($this->newBillingType);
        $request               = null;
        $data                  = [
            'partner_id'       => $this->partner->id,
            'old_package_id'   => $this->currentPackage->id ?: 1,
            'new_package_id'   => $this->newPackage->id ?: 1,
            'old_billing_type' => $this->currentBillingType ?: 'monthly',
            'new_billing_type' => $this->newBillingType ?: 'monthly',
            'discount_id'      => $this->runningDiscount ? $this->runningDiscount->id : null
        ];
        $this->newSubscriptionRequest= PartnerSubscriptionUpdateRequest::create($this->withCreateModificationField($data));
        return $this;
    }

    /**
     * @return bool
     */
    public function hasCredit()
    {
        $hasCredit     = $this->partner->hasCreditForSubscription($this->newPackage, $this->newBillingType);
        $this->balance = [
            'remaining_balance' => $this->partner->totalCreditForSubscription,
            'price'             => $this->partner->totalPriceRequiredForSubscription,
            'breakdown'         => $this->partner->creditBreakdown
        ];
        return $hasCredit;
    }
    public function checkCredit(){
        if(!$this->hasCredit()){

        }
    }
    /**
     * @return array
     */
    public function getBalance()
    {
        return $this->balance;
    }
}
