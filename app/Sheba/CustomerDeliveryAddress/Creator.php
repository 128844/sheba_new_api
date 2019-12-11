<?php namespace Sheba\CustomerDeliveryAddress;

use App\Models\Customer;
use App\Models\HyperLocal;
use Sheba\Location\Geo;
use Sheba\Repositories\Interfaces\Customer\CustomerDeliveryAddressInterface;

class Creator
{
    private $customerDeliveryAddressRepository;
    private $data;
    private $houseNo;
    private $roadNo;
    private $blockNo;
    private $sectorNo;
    private $city;
    private $addressText;
    /** @var Customer */
    private $customer;
    /** @var Geo */
    private $geo;

    public function __construct(CustomerDeliveryAddressInterface $customer_delivery_address_repository)
    {
        $this->customerDeliveryAddressRepository = $customer_delivery_address_repository;
    }

    public function setHouseNo($house_no)
    {
        $this->houseNo = $house_no;
        return $this;
    }

    public function setRoadNo($road_no)
    {
        $this->roadNo = $road_no;
        return $this;
    }

    public function setBlockNo($block_no)
    {
        $this->blockNo = $block_no;
        return $this;
    }

    public function setSectorNo($sector_no)
    {
        $this->sectorNo = $sector_no;
        return $this;
    }

    public function setCity($city)
    {
        $this->city = $city;
        return $this;
    }

    public function setCustomer(Customer $customer)
    {
        $this->customer = $customer;
        return $this;
    }

    public function setAddressText($address_text)
    {
        $this->addressText = $address_text;
        return $this;
    }

    public function setGeo(Geo $geo)
    {
        $this->geo = $geo;
        return $this;
    }

    public function create()
    {
        $this->makeData();
        return $this->customerDeliveryAddressRepository->create($this->data);
    }

    private function makeData()
    {
        $hyper_local = HyperLocal::insidePolygon($this->geo->getLat(), $this->geo->getLng())->first();
        if (!$hyper_local) return null;
        $this->data = [
            'customer_id' => $this->customer->id,
            'address' => $this->addressText,
            'road_no' => $this->roadNo,
            'house_no' => $this->houseNo,
            'block_no' => $this->blockNo,
            'sector_no' => $this->sectorNo,
            'city' => $this->city,
            'name' => $this->customer->profile->name,
            'mobile' => $this->customer->profile->mobile,
            'geo_informations' => json_encode(['lat' => $this->geo->getLat(), 'lng' => $this->geo->getLng()]),
            'location_id' => $hyper_local->location_id
        ];
    }
}