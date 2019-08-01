<?php namespace Sheba\Pos\Order\RefundNatures;

use App\Models\PosOrder;
use Sheba\Pos\Log\Creator as LogCreator;
use Sheba\Pos\Log\Supported\Types;
use Sheba\Pos\Order\Creator;
use Sheba\Pos\Order\Updater;
use Sheba\Pos\Payment\Transfer as PaymentTransfer;
use Sheba\Pos\Product\StockManager;
use Sheba\Pos\Repositories\Interfaces\PosServiceRepositoryInterface;
use Sheba\Pos\Repositories\PosServiceRepository;

class ExchangePosItem extends RefundNature
{
    /** @var false|string */
    private $details;
    /** @var PosOrder $old_order */
    private $newOrder;
    /** @var StockManager $stockManager */
    private $stockManager;
    /** @var PosServiceRepository $serviceRepo */
    private $serviceRepo;
    /** @var PaymentTransfer $paymentTransfer */
    private $paymentTransfer;

    public function __construct(LogCreator $log_creator, Updater $updater,
                                PosServiceRepositoryInterface $service_repo, StockManager $stock_manager, PaymentTransfer $transfer)
    {
        parent::__construct($log_creator, $updater);
        $this->stockManager = $stock_manager;
        $this->serviceRepo = $service_repo;
        $this->paymentTransfer = $transfer;
    }

    public function update()
    {
        $creator = app(Creator::class);
        $this->stockRefill();
        $this->data['previous_order_id'] = $this->order->id;
        $this->newOrder = $creator->setData($this->data)->create();
        $this->generateDetails();
        $this->saveLog();
        $this->transferPaidAmount();
    }

    protected function saveLog()
    {
        $this->logCreator->setOrder($this->order)
            ->setType(Types::EXCHANGE)
            ->setLog("New Order Created for Exchange, old order id: {$this->order->id}")
            ->setDetails($this->details)
            ->create();
    }

    /**
     * GENERATE LOG DETAILS DATA
     */
    protected function generateDetails()
    {
        $details['orders']['changes'] = ['new' => $this->newOrder, 'old' => $this->order];
        $this->details = json_encode($details);
    }

    private function stockRefill()
    {
        $this->order->items->each(function ($item) {
            $partner_pos_service = $this->serviceRepo->find($item->service_id);
            $is_stock_maintainable = $this->stockManager->setPosService($partner_pos_service)->isStockMaintainable();
            if ($is_stock_maintainable) $this->stockManager->increase($item->quantity);
        });
    }

    private function transferPaidAmount()
    {
        $log = "Transfer to " . $this->newOrder->id . " from " . $this->order->id . ", as per exchange.";
        $previous_order_paid_amount = $this->order->getPaid();
        $this->paymentTransfer->setOrder($this->newOrder)
            ->setLog($log)
            ->setAmount($previous_order_paid_amount)
            ->process();
    }
}