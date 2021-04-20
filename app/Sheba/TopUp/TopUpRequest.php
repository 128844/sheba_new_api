<?php namespace Sheba\TopUp;

use App\Models\Affiliate;
use App\Models\Business;
use App\Models\Partner;
use Carbon\Carbon;
use Exception;
use Sheba\Dal\TopUpBlacklistNumber\Contract;
use Sheba\TopUp\Events\TopUpRequestOfBlockedNumber;
use Sheba\TopUp\Vendor\Vendor;
use Sheba\TopUp\Vendor\VendorFactory;
use Event;

class TopUpRequest
{
    const MINIMUM_INTERVAL_BETWEEN_TWO_TOPUP_IN_SECOND = 10;

    private $mobile;
    private $amount;
    private $type;
    /** @var TopUpAgent */
    private $agent;
    private $vendorId;
    /** @var Vendor */
    private $vendor;
    private $vendorFactory;
    private $errorMessage;
    private $name;
    private $bulk_id;
    private $isFromRobiTopUpWallet;
    private $walletType;
    private $topUpBlockNumberRepository;
    /** @var array $blockedAmountByOperator */
    private $blockedAmountByOperator = [];
    protected $userAgent;
    private $lat;
    private $long;

    public function __construct(VendorFactory $vendor_factory, Contract $top_up_block_number_repository)
    {
        $this->vendorFactory = $vendor_factory;
        $this->topUpBlockNumberRepository = $top_up_block_number_repository;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     * @return TopUpRequest
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    public function getAgent()
    {
        return $this->agent;
    }

    public function setAgent(TopUpAgent $agent)
    {
        $this->agent = $agent;
        return $this;
    }

    /**
     * @param $vendor_id
     * @return $this
     * @throws Exception
     */
    public function setVendorId($vendor_id)
    {
        $this->vendorId = $vendor_id;
        $this->vendor = $this->vendorFactory->getById($this->vendorId);
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return (double)$this->amount;
    }

    /**
     * @param mixed $amount
     * @return TopUpRequest
     */
    public function setAmount($amount)
    {
        $this->amount = (double)$amount;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMobile()
    {
        return $this->mobile;
    }

    /**
     * @param mixed $mobile
     * @return TopUpRequest
     */
    public function setMobile($mobile)
    {
        $this->mobile = formatMobile($mobile);
        return $this;
    }

    /**
     * @param $from_robi_topup_wallet
     * @return TopUpRequest
     */
    public function setRobiTopupWallet($from_robi_topup_wallet)
    {
        $this->isFromRobiTopUpWallet = $from_robi_topup_wallet;
        return $this;
    }

    /**
     * @return mixed
     */
    public function isRobiTopUpWallet()
    {
        return $this->isFromRobiTopUpWallet;
    }

    /**
     * @return Vendor
     */
    public function getVendor()
    {
        return $this->vendor;
    }

    /**
     * @return mixed
     */
    public function getOriginalMobile()
    {
        return getOriginalMobileNumber($this->mobile);
    }

    /**
     * @param array $blocked_amount_by_operator
     * @return TopUpRequest
     */
    public function setBlockedAmount(array $blocked_amount_by_operator = [])
    {
        $this->blockedAmountByOperator = $blocked_amount_by_operator;
        return $this;
    }

    public function hasError()
    {
        if ($this->doesAgentNotHaveBalance()) {
            $this->errorMessage = "You don't have sufficient balance to recharge.";
            return 1;
        }

        if (!$this->vendor->isPublished()) {
            $this->errorMessage = "Sorry, we don't support this operator at this moment.";
            return 1;
        }

        if ($this->isAgentNotVerified()) {
            $this->errorMessage = "You are not verified to do this operation.";
            return 1;
        }
        if ($this->isCanTopUpNo()) {
            $this->errorMessage = "টপ-আপ সফল হয়নি, sManager কতৃক আপনার টপ-আপ সার্ভিস বন্ধ করা হয়েছে। বিস্তারিত জানতে কল করুন ১৬৫১৬ নাম্বারে।";
            return 1;
        }

        if ($this->agent instanceof Business && $this->isAmountBlocked()) {
            $this->errorMessage = "The recharge amount is blocked due to OTF activation issue.";
            return 1;
        }

        if ($this->agent instanceof Business && $this->isPrepaidAmountLimitExceed($this->agent)) {
            $this->errorMessage = "The amount exceeded your topUp prepaid limit.";
            return 1;
        }

        if ($this->topUpBlockNumberRepository->findByMobile($this->mobile)) {
            Event::fire(new TopUpRequestOfBlockedNumber($this));
            $this->errorMessage = "You can't recharge to a blocked number.";
            return 1;
        }

        return 0;
    }

    private function doesAgentNotHaveBalance()
    {
        return ($this->isFromRobiTopUpWallet == 1 && $this->agent->robi_topup_wallet < $this->amount) ||
            ($this->isFromRobiTopUpWallet != 1 && $this->agent->wallet < $this->amount);
    }

    private function isAgentNotVerified()
    {
        return ($this->agent instanceof Partner && (!$this->agent->isNIDVerified() || !$this->agent->canTopUp())) ||
            ($this->agent instanceof Affiliate && $this->agent->isNotVerified());
    }
    private function isCanTopUpNo()
    {
        return ($this->agent instanceof Partner && (!$this->agent->canTopUp()));
    }

    /**
     * @return bool
     */
    private function isAmountBlocked()
    {
        if (empty($this->blockedAmountByOperator)) return false;
        if ($this->vendorId == VendorFactory::GP) return in_array($this->amount, $this->blockedAmountByOperator[TopUpSpecialAmount::GP]);
        if ($this->vendorId == VendorFactory::BANGLALINK) return in_array($this->amount, $this->blockedAmountByOperator[TopUpSpecialAmount::BANGLALINK]);
        if ($this->vendorId == VendorFactory::ROBI) return in_array($this->amount, $this->blockedAmountByOperator[TopUpSpecialAmount::ROBI]);
        if ($this->vendorId == VendorFactory::AIRTEL) return in_array($this->amount, $this->blockedAmountByOperator[TopUpSpecialAmount::AIRTEL]);
        if ($this->vendorId == VendorFactory::TELETALK) return in_array($this->amount, $this->blockedAmountByOperator[TopUpSpecialAmount::TELETALK]);

        return false;
    }

    /**
     * @param Business $business
     * @return bool
     */
    private function isPrepaidAmountLimitExceed(Business $business)
    {
        if ($this->type == ConnectionType::PREPAID && ($this->amount > $business->topup_prepaid_max_limit)) return true;
        return false;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     * @return TopUpRequest
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBulkId()
    {
        return $this->bulk_id;
    }

    /**
     * @param mixed $bulk_id
     * @return TopUpRequest
     */
    public function setBulkId($bulk_id)
    {
        $this->bulk_id = $bulk_id;
        return $this;
    }

    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getUserAgent()
    {
        return $this->userAgent;
    }

    public function setLat($lat)
    {
        $this->lat = $lat;
        return $this;
    }

    public function setLong($long)
    {
        $this->long = $long;
        return $this;
    }

    public function getLat()
    {
        return $this->lat;
    }

    public function getLong()
    {
        return $this->long;
    }
}
