<?php

namespace Sheba\ShebaPay\Helpers;

use App\Models\TopUpOrder;
use Sheba\TopUp\TopUpFailedReason;

class OrderData
{
    /**
     * @var TopUpOrder
     */
    private $order;

    public function __construct(TopUpOrder $order)
    {
        $order->reload();
        $this->order=$order;
    }

    /**
     * @return array
     */
    public function get(): array
    {
        return [
            'id'=>$this->order->id,
            'payee_mobile' => $this->order->payee_mobile,
            'payee_name' => $this->order->payee_name ?: 'No Name',
            'amount' => $this->order->amount,
            'operator' => $this->order->vendor->name,
            'status' => $this->order->getStatusForAgent(),
            'transaction_id' => $this->order->transaction_id,
            'payee_mobile_type' => $this->order->payee_mobile_type,
            'failed_reason' => (new TopUpFailedReason())->setTopup($this->order)->getFailedReason(),
            'created_at' => $this->order->created_at->format('jS M, Y h:i A'),
            'created_at_raw' => $this->order->created_at->format('Y-m-d H:i:s')
        ];

    }

}