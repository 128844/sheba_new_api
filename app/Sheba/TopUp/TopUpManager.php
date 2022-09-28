<?php namespace Sheba\TopUp;


use App\Models\Affiliate;
use App\Models\Partner;
use App\Models\TopUpOrder;
use Exception;
use Illuminate\Support\Facades\DB;
use Sheba\PushNotificationHandler;
use Sheba\TopUp\Vendor\VendorFactory;
use Throwable;

abstract class TopUpManager
{
    /** @var StatusChanger */
    protected $statusChanger;

    /** @var TopUpOrder */
    protected $topUpOrder;

    public function __construct(StatusChanger $status_changer)
    {
        $this->statusChanger = $status_changer;
    }

    /**
     * @param TopUpOrder $order
     * @return $this
     */
    public function setTopUpOrder(TopUpOrder $order)
    {
        $this->topUpOrder = $order;
        $this->statusChanger->setOrder($this->topUpOrder);
        return $this;
    }

    /**
     * @param $action
     * @throws Throwable
     */
    protected function doTransaction($action)
    {
        try {
            DB::transaction($action);
        } catch (Throwable $e) {
            $this->markOrderAsSystemError($e);
            throw $e;
        }
    }

    protected function markOrderAsSystemError(Throwable $e)
    {
        logErrorWithExtra($e, ['topup' => $this->topUpOrder->getDirty()]);
        $this->statusChanger->systemError();
    }

    protected function refund()
    {
        $this->topUpOrder->agent->getCommission()->setTopUpOrder($this->topUpOrder)->refund();
    }

    /**
     * @return Vendor\Vendor
     * @throws Exception
     */
    protected function getVendor()
    {
        return (new VendorFactory())->getById($this->topUpOrder->vendor_id);
    }

    protected function sendPushNotification($title, $message)
    {
        try {
            $agent = $this->topUpOrder->agent;
            if ($agent instanceof Partner) {
                $topic = config('sheba.push_notification_topic_name.manager') . $agent->id;
                $channel = config('sheba.push_notification_channel_name.manager');
            } else if ($agent instanceof Affiliate) {
                $topic = config('sheba.push_notification_topic_name.affiliate') . $agent->id;
                $channel = config('sheba.push_notification_channel_name.affiliate');
            } else return;

            $notification_data = [
                "title" => $title,
                "message" => $message,
                "event_type" => 'TopUp',
                "sound" => "notification_sound",
                "channel_id" => $channel
            ];
            (new PushNotificationHandler())->send($notification_data, $topic, $channel);
        } catch (Exception $e){
            logError($e);
        }
    }
}
