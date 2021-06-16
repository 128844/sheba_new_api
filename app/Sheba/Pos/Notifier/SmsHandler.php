<?php namespace Sheba\Pos\Notifier;

use App\Models\Partner;
use App\Models\PosOrder;
use App\Repositories\SmsHandler as SmsHandlerRepo;
use App\Sheba\Sms\BusinessType;
use App\Sheba\Sms\FeatureType;
use Exception;
use Sheba\FraudDetection\TransactionSources;
use Sheba\Transactions\Types;
use Sheba\Transactions\Wallet\WalletTransactionHandler;

class SmsHandler
{
    /** @var PosOrder */
    private $order;

    public function setOrder(PosOrder $order)
    {
        $this->order = $order->calculate();
        return $this;
    }

    /**
     * @throws Exception
     */
    public function handle()
    {
        /** @var Partner $partner */
        $partner = $this->order->partner;
        $partner->reload();
        if (empty($this->order->customer)) return;

        $service_break_down = [];
        $this->order->items->each(function ($item) use (&$service_break_down) {
            $service_break_down[$item->id] = $item->service_name . ': ' . $item->getTotal();
        });

        $service_break_down = implode(',', $service_break_down);
        $sms                = $this->getSms($service_break_down);
        $sms_cost           = $sms->estimateCharge();
        if ((double)$partner->wallet < $sms_cost) return;

        try {
            $sms->setBusinessType(BusinessType::SMANAGER)
                ->setFeatureType(FeatureType::POS)
                ->shoot();
        } catch(\Throwable $e) {
        }

        (new WalletTransactionHandler())
            ->setModel($partner)
            ->setAmount($sms_cost)
            ->setType(Types::debit())
            ->setLog($sms_cost . " BDT has been deducted for sending pos order details sms (order id: {$this->order->id})")
            ->setTransactionDetails([])
            ->setSource(TransactionSources::SMS)
            ->store();
    }

    /**
     * @param $service_break_down
     * @return SmsHandlerRepo
     * @throws Exception
     */
    private function getSms($service_break_down)
    {
        $message_data = [
            'order_id'           => $this->order->partner_wise_order_id,
            'service_break_down' => $service_break_down,
            'total_amount'       => $this->order->getNetBill(),
            'partner_name'       => $this->order->partner->name
        ];

        if ($this->order->getDue() > 0) {
            $sms_handler = (new SmsHandlerRepo('pos-due-order-bills'));
            $message_data['total_due_amount'] = $this->order->getDue();
        } else {
            $sms_handler = (new SmsHandlerRepo('pos-order-bills'));
        }

        return $sms_handler
            ->setMobile($this->order->customer->profile->mobile)
            ->setFeatureType(FeatureType::POS)
            ->setBusinessType(BusinessType::SMANAGER)
            ->setMessage($message_data);
    }
}
