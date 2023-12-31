<?php namespace App\Sheba\Partner\Delivery;


use App\Models\PosOrder;
use App\Sheba\PosOrderService\Services\OrderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;
use Sheba\Dal\POSOrder\OrderStatuses;
use Sheba\Pos\Repositories\PosOrderRepository;

class OrderPlace
{

    private $partner;
    private $logisticPartnerId;
    private $weight;
    private $codAmount;
    private $customerName;
    private $customerPhone;
    private $deliveryAddress;
    private $deliveryThana;
    private $deliveryDistrict;
    private $partnerName;
    private $partnerPhone;
    private $pickupAddress;
    private $pickupThana;
    private $pickupDistrict;
    private $posOrder;
    /**
     * @var PosOrderRepository
     */
    private $posOrderRepository;
    private $token;
    /**
     * @var OrderService
     */
    private $orderService;
    private $posOrderId;

    public function __construct(DeliveryServerClient $client, PosOrderRepository $posOrderRepository, OrderService $orderService)
    {
        $this->client = $client;
        $this->posOrderRepository = $posOrderRepository;
        $this->orderService = $orderService;
    }

    public function setPartner($partner)
    {
        $this->partner = $partner;
        return $this;
    }

    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    public function setWeight($weight)
    {
        $this->weight = $weight;
        return $this;
    }

    public function setCodAmount($codAmount)
    {
        $this->codAmount = $codAmount;
        return $this;
    }

    public function setCustomerName($customerName)
    {
        $this->customerName = $customerName;
        return $this;
    }

    public function setCustomerPhone($customerPhone)
    {
        $this->customerPhone = $customerPhone;
        return $this;
    }

    public function setPartnerName($partnerName)
    {
        $this->partnerName = $partnerName;
        return $this;
    }

    public function setPartnerPhone($partnerPhone)
    {
        $this->partnerPhone = $partnerPhone;
        return $this;
    }

    public function setDeliveryAddress($deliveryAddress)
    {
        $this->deliveryAddress = $deliveryAddress;
        return $this;
    }

    public function setDeliveryThana($deliveryThana)
    {
        $this->deliveryThana = $deliveryThana;
        return $this;
    }

    public function setDeliveryDistrict($deliveryDistrict)
    {
        $this->deliveryDistrict = $deliveryDistrict;
        return $this;
    }

    public function setPickupAddress($pickupAddress)
    {
        $this->pickupAddress = $pickupAddress;
        return $this;
    }

    public function setPickupThana($pickupThana)
    {
        $this->pickupThana = $pickupThana;
        return $this;
    }

    public function setPickupDistrict($pickupDistrict)
    {
        $this->pickupDistrict = $pickupDistrict;
        return $this;
    }

    public function setPosOrder($posOrderId)
    {
        $this->posOrderId = $posOrderId;
        $this->posOrder  = PosOrder::find($posOrderId);
        return $this;
    }

    /**
     * @return mixed
     */
    public function orderPlace()
    {
        $data = $this->makeData();
        return $this->client->setToken($this->token)->post('orders', $data);
    }


    /**
     * @param $info
     * @return array|Model|object|string|null
     */
    public function storeDeliveryInformation($info)
    {
        $data = [
            'delivery_vendor_name' => Methods::SDELIVERY,
            'address' => $info['delivery_address']['address'],
            'delivery_district' => $info['delivery_address']['district'],
            'delivery_thana' => $info['delivery_address']['thana'],
            'delivery_status' => $info['status'],
            'delivery_request_id' => $info['uid'],
            'status' => OrderStatuses::SHIPPED
        ];
        if ($this->posOrder && !$this->posOrder->is_migrated) return $this->posOrderRepository->update($this->posOrder, $data);
        return $this->orderService->setPartnerId($this->partner->id)->setOrderId($this->posOrderId)->storeDeliveryInformation($data);
    }


    private function makeData()
    {
        return [
            'logistic_partner_id' => 1,
            'weight' => $this->weight,
            'cod_amount' => $this->codAmount,
            'pick_up' => [
                'person_name' => $this->partnerName,
                'contact_phone' => $this->partnerPhone,
                'address' => $this->pickupAddress,
                'thana' => $this->pickupThana,
                'district' => $this->pickupDistrict
            ],
            'delivery' => [
                'person_name' => $this->customerName,
                'contact_phone' => $this->customerPhone,
                'address' => $this->deliveryAddress,
                'thana' => $this->deliveryThana,
                'district' => $this->deliveryDistrict
            ]
        ];
    }


}