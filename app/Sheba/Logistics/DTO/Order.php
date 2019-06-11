<?php namespace Sheba\Logistics\DTO;

use Carbon\Carbon;
use Sheba\Helpers\BasicGetter;

class Order 
{
    use BasicGetter;
    
    private $id;
    /** @var Carbon */
    private $schedule;
    /** @var Point */
    private $pickUp;
    /** @var Point */
    private $dropOff;
    private $customerProfileId;
    private $parcelType;
    private $successUrl;
    private $pickedUrl;
    private $failureUrl;
    private $collectionUrl;
    /** @var VendorOrder */
    private $vendorOrder;
    private $paidAmount;
    private $isInstant;
    private $collectableAmount;
    private $discount;
    private $isDiscountInPercentage;
    private $code;

    /**
     * @param mixed $code
     * @return Order
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }
    /**
     * @param int $id
     *
     * @return Order
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param Carbon $schedule
     *
     * @return Order
     */
    public function setSchedule(Carbon $schedule)
    {
        $this->schedule = $schedule;
        return $this;
    }

    /**
     * @param Point $pick_up
     *
     * @return Order
     */
    public function setPickUp(Point $pick_up)
    {
        $this->pickUp = $pick_up;
        return $this;
    }

    /**
     * @param Point $drop_off
     *
     * @return Order
     */
    public function setDropOff($drop_off)
    {
        $this->dropOff = $drop_off;
        return $this;
    }

    /**
     * @param int $customer_profile_id
     *
     * @return Order
     */
    public function setCustomerProfileId($customer_profile_id)
    {
        $this->customerProfileId = $customer_profile_id;
        return $this;
    }

    /**
     * @param  $parcel_type
     *
     * @return Order
     */
    public function setParcelType($parcel_type)
    {
        $this->parcelType = $parcel_type;
        return $this;
    }

    /**
     * @param string $success_url
     *
     * @return Order
     */
    public function setSuccessUrl($success_url)
    {
        $this->successUrl = $success_url;
        return $this;
    }

    /**
     * @param string $picked_url
     *
     * @return Order
     */
    public function setPickedUrl($picked_url)
    {
        $this->pickedUrl = $picked_url;
        return $this;
    }

    /**
     * @param string $failure_url
     *
     * @return Order
     */
    public function setFailureUrl($failure_url)
    {
        $this->failureUrl = $failure_url;
        return $this;
    }

    /**
     * @param string $collection_url
     *
     * @return Order
     */
    public function setCollectionUrl($collection_url)
    {
        $this->collectionUrl = $collection_url;
        return $this;
    }

    /**
     * @param VendorOrder $vendor_order
     *
     * @return Order
     */
    public function setVendorOrder(VendorOrder $vendor_order)
    {
        $this->vendorOrder = $vendor_order;
        return $this;
    }

    /**
     * @param float $paid_amount
     *
     * @return Order
     */
    public function setPaidAmount($paid_amount)
    {
        $this->paidAmount = $paid_amount;
        return $this;
    }

    /**
     * @param bool $is_instant
     *
     * @return Order
     */
    public function setIsInstant($is_instant)
    {
        $this->isInstant = $is_instant;
        return $this;
    }

    /**
     * @param float $collectable_amount
     *
     * @return Order
     */
    public function setCollectableAmount($collectable_amount)
    {
        $this->collectableAmount = $collectable_amount;
        return $this;
    }

    /**
     * @param float $discount
     *
     * @return Order
     */
    public function setDiscount($discount)
    {
        $this->discount = $discount;
        return $this;
    }
    
    /**
     * @param array $discount
     *
     * @return Order
     */
    public function setDiscountByArray(array $discount)
    {
        $this->setDiscount($discount['amount'])->setIsDiscountInPercentage($discount['is_percentage']);
        return $this;
    }

    /**
     * @param bool $is_discount_in_percentage
     *
     * @return Order
     */
    public function setIsDiscountInPercentage($is_discount_in_percentage)
    {
        $this->isDiscountInPercentage = $is_discount_in_percentage;
        return $this;
    }
    
    /**
     * @return array
     */
    public function toArray() 
    {
        return [
            'customer_profile_id'   => $this->customerProfileId,
            'date'                  => $this->schedule->toDateString(),
            'time'                  => $this->schedule->toTimeString(),
            'pickup_name'           => $this->pickUp->name,
            'pickup_image'          => $this->pickUp->image,
            'pickup_mobile'         => $this->pickUp->mobile,
            'pickup_address'        => $this->pickUp->address,
            'pickup_address_geo'    => $this->pickUp->coordinate->toJson(),
            'delivery_name'         => $this->dropOff->name,
            'delivery_image'        => $this->dropOff->image,
            'delivery_mobile'       => $this->dropOff->mobile,
            'delivery_address'      => $this->dropOff->address,
            'delivery_address_geo'  => $this->dropOff->coordinate->toJson(),
            'parcel_type'           => $this->parcelType,
            'success_url'           => $this->successUrl,
            'picked_url'            => $this->pickedUrl,
            'failure_url'           => $this->failureUrl,
            'collection_url'        => $this->collectionUrl,
            'vendor_order_detail'   => $this->vendorOrder->toJson(),
            'paid_amount'           => $this->paidAmount,
            'is_instant'            => $this->isInstant,
            'collectable_amount'    => $this->collectableAmount,
            'discount'              => $this->discount,
            'is_percentage'         => $this->isDiscountInPercentage,
            'id'                    => $this->id,
            'code'                  => $this->code
        ];
    }
}
