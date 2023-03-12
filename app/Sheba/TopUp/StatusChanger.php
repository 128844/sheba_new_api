<?php

namespace Sheba\TopUp;

use App\Models\TopUpOrder;
use Illuminate\Support\Facades\DB;
use Sheba\Dal\TopupOrder\Statuses;
use Sheba\Dal\TopupOrder\TopUpOrderRepository;
use Sheba\Dal\TopUpOrderStatusLog\TopUpOrderStatusLogRepository;
use Sheba\ModificationFields;

class StatusChanger
{
    use ModificationFields;

    /** @var TopUpOrderRepository */
    private $orderRepo;
    /** @var TopUpOrderStatusLogRepository */
    private $statusRepo;

    /** @var TopUpOrder */
    private $order;
    /** @var TopUpOrder */
    private $oldOrder;

    public function __construct(TopUpOrderRepository $order_repo, TopUpOrderStatusLogRepository $status_repo)
    {
        $this->orderRepo = $order_repo;
        $this->statusRepo = $status_repo;
    }

    public function setOrder(TopUpOrder $order): StatusChanger
    {
        $this->order = $order;
        $this->oldOrder = clone $order;
        return $this;
    }

    /**
     * @return TopUpOrder
     */
    public function attempted(): TopUpOrder
    {
        return $this->update(Statuses::ATTEMPTED);
    }

    /**
     * @param $transaction_details
     * @param $transaction_id
     * @return TopUpOrder
     */
    public function pending($transaction_details, $transaction_id): TopUpOrder
    {
        return $this->update(Statuses::PENDING, [
            "transaction_id"      => $transaction_id,
            "transaction_details" => json_encode($transaction_details),
        ]);
    }

    /**
     * @param $transaction_details
     * @param $transaction_id
     * @return TopUpOrder
     */
    public function successful($transaction_details, $transaction_id = null): TopUpOrder
    {
        $data = ["transaction_details" => json_encode($transaction_details)];
        if ($transaction_id) {
            $data["transaction_id"] = $transaction_id;
        }

        return $this->update(Statuses::SUCCESSFUL, $data);
    }

    /**
     * @param  FailDetails  $details
     * @return TopUpOrder
     */
    public function failed(FailDetails $details): TopUpOrder
    {
        return $this->update(Statuses::FAILED, [
            "failed_reason"       => $details->getReason(),
            "failed_message"      => $details->getMessage(),
            "transaction_details" => json_encode($details->getTransactionDetails()),
        ]);
    }

    /**
     * @return TopUpOrder
     */
    public function systemError(): TopUpOrder
    {
        return $this->update(Statuses::SYSTEM_ERROR);
    }

    /**
     * @param $status
     * @param  array  $data
     * @return TopUpOrder
     */
    private function update($status, array $data = []): TopUpOrder
    {
        DB::transaction(function () use ($data, $status) {
            $data["status"] = $status;
            $this->orderRepo->update($this->order, $data);
            $this->saveLog($data);
        });

        $this->oldOrder = clone $this->order;
        return $this->order;
    }

    /**
     * @param  array  $data
     * @return void
     */
    private function saveLog(array $data = [])
    {
        $transaction_details = $data['transaction_details'] ?? $this->oldOrder->transaction_details;
        $log_data = $this->withCreateModificationField([
            "topup_order_id"      => $this->order->id,
            "from"                => $this->oldOrder->status,
            "to"                  => $this->order->status,
            "transaction_details" => $transaction_details
        ]);
        $this->statusRepo->create($log_data);
    }
}
