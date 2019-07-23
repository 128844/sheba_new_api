<?php namespace Sheba\Checkout\Requests;

use App\Models\Customer;
use App\Models\CustomerDeliveryAddress;
use App\Models\HyperLocal;
use Carbon\Carbon;

class SubscriptionOrderRequest extends PartnerListRequest
{
    private $salesChannel;
    private $geo;
    /** @var $address CustomerDeliveryAddress */
    private $address;
    /** @var $customer Customer */
    private $customer;
    /** @var $billingCycleStart Carbon */
    private $billingCycleStart;
    /** @var $billingCycleEnd Carbon */
    private $billingCycleEnd;
    protected $location;
    private $deliveryName;
    private $deliveryMobile;
    private $additionalInfo;

    public function __get($name)
    {
        return $this->$name;
    }

    public function prepareObject()
    {
        $this->setCustomer();
        $this->setAddress();
        $this->setSalesChannel();
        $this->setDeliveryName();
        $this->setDeliveryMobile();
        $this->setAdditionalInfo();
        $this->setGeo($this->geo->lat, $this->geo->lng);
        parent::prepareObject();
        $this->calculateBillingCycle();
    }

    private function setCustomer()
    {
        $this->customer = $this->request->has('customer') ? $this->request->customer : Customer::find($this->request->customer_id);
    }

    private function setAddress()
    {
        $this->address = CustomerDeliveryAddress::withTrashed()
            ->where('id', $this->request->address_id)
            ->where('customer_id', $this->customer->id)
            ->first();

        $this->decodeGeo();
        $this->calculateLocation();
    }

    private function calculateLocation()
    {
        if ($this->address->location_id) $this->location = $this->address->location_id;
        else {
            $hyper_local = HyperLocal::insidePloygon($this->geo->lat, $this->geo->lng)->first();
            $this->location = $hyper_local->location_id;
        }
    }

    private function decodeGeo()
    {
        $this->geo = json_decode($this->address->geo_informations);
    }

    private function setSalesChannel()
    {
        $this->salesChannel = $this->request->sales_channel;
    }

    private function setDeliveryName()
    {
        $this->deliveryName = $this->request->name;
    }

    private function setDeliveryMobile()
    {
        $this->deliveryMobile = $this->request->mobile;
    }

    private function setAdditionalInfo()
    {
        $this->additionalInfo = $this->request->additional_info;
    }

    private function calculateBillingCycle()
    {
        $this->billingCycleStart = Carbon::now();
        $this->billingCycleEnd = $this->getBillingCycleEnd();
    }

    private function getBillingCycleEnd()
    {
        $days = $this->isWeeklySubscription() ? 7 : 30;
        return $this->billingCycleStart->copy()->addDays($days);
    }
}
