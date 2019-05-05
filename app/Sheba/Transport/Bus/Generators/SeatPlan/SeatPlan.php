<?php namespace Sheba\Transport\Bus\Generators\SeatPlan;

class SeatPlan
{
    /** @var BusBdSeatPlan $busBdSeatPlan */
    private $busBdSeatPlan;
    /** @var PekhomSeatPlan $pekhomSeatPlan */
    private $pekhomSeatPlan;

    private $pickupAddressId = null;
    private $destinationAddressId = null;
    private $date = null;
    private $vendorId = null;
    private $coachId = null;


    public function __construct(BusBdSeatPlan $bus_bd_seat_plan, PekhomSeatPlan $pekhom_seat_plan)
    {
        $this->busBdSeatPlan = $bus_bd_seat_plan;
        $this->pekhomSeatPlan = $pekhom_seat_plan;
    }

    /**
     * @param $pickup_address_id
     * @return $this
     */
    public function setPickupAddressId($pickup_address_id)
    {
        $this->pickupAddressId = $pickup_address_id;
        return $this;
    }

    /**
     * @param $destination_address_id
     * @return $this
     */
    public function setDestinationAddressId($destination_address_id)
    {
        $this->destinationAddressId = $destination_address_id;
        return $this;
    }

    /**
     * @param $date
     * @return SeatPlan
     */
    public function setDate($date)
    {
        $this->date = $date;
        return $this;
    }

    /**
     * @param $vendor_id
     * @return SeatPlan
     */
    public function setVendorId($vendor_id)
    {
        $this->vendorId = $vendor_id;
        return $this;
    }

    /**
     * @param $coach_id
     * @return SeatPlan
     */
    public function setCoachId($coach_id)
    {
        $this->coachId = $coach_id;
        return $this;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function getSeatPlan()
    {
        $seatPlan = $this->resolveSeatPlan();
        return $seatPlan;
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    private function resolveSeatPlan()
    {
        switch ($this->vendorId) {
            case 1:
                // Bus Bd
                return $this->busBdSeatPlan->setVendorId($this->vendorId)->setCoachId($this->coachId)->getSeatPlan();
            case 2:
                // Pekhom

                break;
            default:
                throw new \Exception('Invalid Vendor');
                break;
        }
    }
}