<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sheba\Pos\Order\OrderPaymentStatuses;

class PosOrder extends Model
{
    protected $guarded = ['id'];

    /**
     * @var string
     */
    private $paymentStatus;
    /**
     * @var float
     */
    private $paid;
    /**
     * @var float
     */
    private $due;
    /**
     * @var float|int
     */
    private $totalPrice;
    /**
     * @var number
     */
    private $totalVat;
    /**
     * @var float|int
     */
    private $totalItemDiscount;
    /**
     * @var float|int|number
     */
    private $totalBill;
    /**
     * @var float|int
     */
    private $totalDiscount;
    /**
     * @var float|int|number
     */
    private $appliedDiscount;
    /**
     * @var float|int|number
     */
    private $netBill;
    /**
     * @var bool
     */
    public $isCalculated;

    public function calculate()
    {
        $this->_calculateThisItems();
        $this->totalDiscount = $this->totalItemDiscount + $this->discount;
        $this->appliedDiscount = (double)($this->totalDiscount > $this->totalBill) ? $this->totalBill : $this->totalDiscount;
        $this->netBill = $this->totalBill - $this->appliedDiscount;
        $this->_calculatePaidAmount();
        $this->paid = $this->paid ?: 0;
        $this->due = $this->netBill - $this->paid;
        $this->_setPaymentStatus();
        $this->isCalculated = true;
        $this->_formatAllToTaka();

        return $this;
    }

    private function _setPaymentStatus()
    {
        $this->paymentStatus = ($this->due) ? OrderPaymentStatuses::DUE : OrderPaymentStatuses::PAID;
        return $this;
    }

    public function customer()
    {
        return $this->belongsTo(PosCustomer::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function items()
    {
        return $this->hasMany(PosOrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(PosOrderPayment::class);
    }

    private function _calculateThisItems()
    {
        $this->_initializeTotalsToZero();
        foreach ($this->items as $item) {
            /** @var PosOrderItem $item */
            $item = $item->calculate();
            $this->_updateTotalPriceAndCost($item);
        }
        return $this;
    }

    private function _initializeTotalsToZero()
    {
        $this->totalPrice = 0;
        $this->totalVat = 0;
        $this->totalItemDiscount = 0;
        $this->totalBill = 0;
    }

    private function _updateTotalPriceAndCost(PosOrderItem $item)
    {
        $this->totalPrice += $item->getPrice();
        $this->totalVat += $item->getVat();
        $this->totalItemDiscount += $item->getDiscountAmount();
        $this->totalBill += $item->getTotal();
    }

    private function _formatAllToTaka()
    {
        $this->totalPrice = formatTakaToDecimal($this->totalPrice);
        $this->totalVat = formatTakaToDecimal($this->totalVat);
        $this->totalItemDiscount = formatTakaToDecimal($this->totalItemDiscount);
        $this->totalBill = formatTakaToDecimal($this->totalBill);

        return $this;
    }

    private function _calculatePaidAmount()
    {
        $this->paid = 0;
        foreach ($this->payments as $payment) {
            $this->paid += $payment->amount;
        }
    }

    /**
     * @return string
     */
    public function getPaymentStatus()
    {
        return $this->paymentStatus;
    }

    /**
     * @return float
     */
    public function getPaid()
    {
        return $this->paid;
    }

    /**
     * @return float
     */
    public function getDue()
    {
        return $this->due;
    }

    /**
     * @return float|int
     */
    public function getTotalPrice()
    {
        return $this->totalPrice;
    }

    /**
     * @return number
     */
    public function getTotalVat()
    {
        return $this->totalVat;
    }

    /**
     * @return float|int
     */
    public function getTotalItemDiscount()
    {
        return $this->totalItemDiscount;
    }

    /**
     * @return float|int|number
     */
    public function getTotalBill()
    {
        return $this->totalBill;
    }

    /**
     * @return float|int
     */
    public function getTotalDiscount()
    {
        return $this->totalDiscount;
    }

    /**
     * @return float|int|number
     */
    public function getAppliedDiscount()
    {
        return $this->appliedDiscount;
    }

    /**
     * @return float|int|number
     */
    public function getNetBill()
    {
        return $this->netBill;
    }
}
