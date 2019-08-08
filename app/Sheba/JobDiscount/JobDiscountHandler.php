<?php namespace Sheba\JobDiscount;

use App\Models\Category;
use App\Models\Job;
use App\Models\Partner;

use Sheba\Dal\Discount\Discount;
use Sheba\Dal\Discount\DiscountRepository;
use Sheba\Dal\Discount\DiscountRules;
use Sheba\Dal\Discount\DiscountTypes;
use Sheba\Dal\Discount\InvalidDiscountType;
use Sheba\Dal\JobDiscount\JobDiscountRepository;

class JobDiscountHandler
{
    /** @var DiscountRepository */
    private $discountRepo;
    /** @var JobDiscountRepository */
    private $jobDiscountRepo;
    private $type;
    /** @var Category */
    private $category;
    /** @var Partner */
    private $partner;
    /** @var JobDiscountCheckingParams */
    private $params;
    /** @var Discount */
    private $discount;

    public function __construct(DiscountRepository $discount_repo, JobDiscountRepository $job_discount_repo)
    {
        $this->discountRepo = $discount_repo;
        $this->jobDiscountRepo = $job_discount_repo;
    }

    /**
     * @param $type
     * @return $this
     * @throws InvalidDiscountType
     */
    public function setType($type)
    {
        DiscountTypes::checkIfValid($type);
        $this->type = $type;
        return $this;
    }

    public function setCategory(Category $category)
    {
        $this->category = $category;
        return $this;
    }

    public function setPartner(Partner $partner)
    {
        $this->partner = $partner;
        return $this;
    }

    public function setCheckingParams(JobDiscountCheckingParams $params)
    {
        $this->params = $params;
        return $this;
    }

    public function calculate()
    {
        $against = [];
        if ($this->category) $against[] = $this->category;
        if ($this->partner) $against[] = $this->partner;
        if (empty($against)) {
            $discounts = $this->discountRepo->getValidForAgainst($this->type, $against);
        } else {
            $discounts = $this->discountRepo->getValidFor($this->type);
        }

        foreach ($discounts as $discount) {
            if ($this->check($discount)) {
                $this->discount = $discount;
                break;
            }
        }
    }

    public function hasDiscount()
    {
        return !is_null($this->discount);
    }

    public function getApplicableAmount()
    {
        return $this->discount->getApplicableAmount($this->params->getDiscountableAmount());
    }

    public function getData()
    {
        return [
            'discount_id' => $this->discount->id,
            'type' => $this->discount->type,
            'amount' => $this->getApplicableAmount(),
            'original_amount' => $this->discount->amount,
            'is_percentage' => $this->discount->is_percentage,
            'cap' => $this->discount->cap,
            'sheba_contribution' => $this->discount->sheba_contribution,
            'partner_contribution' => $this->discount->partner_contribution
        ];
    }

    public function create(Job $job)
    {
        $discount_data = $this->getData();
        if (empty($discount_data)) return;
        $discount_data['job_id'] = $job->id;
        $this->jobDiscountRepo->create($discount_data);
    }

    /**
     * @param Discount $discount
     * @return bool
     */
    private function check(Discount $discount)
    {
        /** @var DiscountRules $rules */
        $rules = $discount->rules;
        if ($this->params->getOrderAmount() < $rules->getMinOrderAmount()) return false;
        return true;
    }
}
