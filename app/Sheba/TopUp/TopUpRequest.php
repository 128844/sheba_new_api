<?php namespace Sheba\TopUp;

use Carbon\Carbon;
use Sheba\TopUp\Vendor\Vendor;
use Sheba\TopUp\Vendor\VendorFactory;

class TopUpRequest
{
    private $mobile;
    private $amount;
    private $type;
    private $agent;
    private $vendorId;
    /** @var Vendor */
    private $vendor;
    private $vendorFactory;
    private $errorMessage;
    private $name;
    private $bulk_id;
    const MINIMUM_TOPUP_INTERVAL_BETWEEN_TWO_TOPUP_IN_SECOND = 20;

    public function __construct(VendorFactory $vendor_factory)
    {
        $this->vendorFactory = $vendor_factory;
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

    public function hasError()
    {
        if ($this->agent->wallet < $this->amount) {
            $this->errorMessage = "You don't have sufficient balance to recharge.";
            return 1;
        }
        if (!$this->vendor->isPublished()) {
            $this->errorMessage = "Sorry, we don't support this operator at this moment.";
            return 1;
        }
        if (get_class($this->agent) == "App\Models\Partner") {
            $this->errorMessage = "Temporary turned off.";
            return 1;
        }
        if ($last_topup = $this->agent->topups()->select('id', 'created_at')->orderBy('id', 'desc')->first()) {
            if ($last_topup->created_at->diffInSeconds(Carbon::now()) < self::MINIMUM_TOPUP_INTERVAL_BETWEEN_TWO_TOPUP_IN_SECOND) {
                $this->errorMessage = "Please wait a minutes to request topup.";
                return 1;
            }
        }

        return 0;
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
}
