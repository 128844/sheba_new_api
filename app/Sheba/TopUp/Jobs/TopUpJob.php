<?php namespace Sheba\TopUp\Jobs;

use App\Models\TopUpOrder;
use App\Models\TopUpVendor;
use Exception;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Sheba\QueueMonitor\MonitoredJob;
use Sheba\TopUp\TopUpRechargeManager;
use Sheba\TopUp\TopUpAgent;
use Sheba\TopUp\TopUpCompletedEvent;
use Sheba\Usage\Usage;
use Throwable;

class TopUpJob extends MonitoredJob implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /** @var TopUpRechargeManager */
    protected $topUp;
    /** @var FailedJobProviderInterface */
    private $failedJobLogger;

    /** @var TopUpAgent */
    protected $agent;
    /** @var TopUpOrder */
    protected $topUpOrder;

    public function __construct(TopUpOrder $top_up_order)
    {
        $this->topUpOrder = $top_up_order;
        $this->agent = $this->topUpOrder->agent;
        $this->connection = QueueConnectionManager::getConnectionName($this->agent);
        $this->queue = $this->connection;
        parent::__construct();
    }

    /**
     * Execute the job.
     *
     * @param TopUpRechargeManager $top_up
     * @param FailedJobProviderInterface|null $logger
     * @return void
     * @throws Throwable
     */
    public function handle(TopUpRechargeManager $top_up, FailedJobProviderInterface $logger = null)
    {
        if ($this->attempts() > 1) return;

        $this->topUp = $top_up;
        $this->failedJobLogger = $logger;

        try {
            $this->_handle();
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * @throws Exception|Throwable
     */
    private function _handle()
    {
        $this->topUp->setTopUpOrder($this->topUpOrder)->recharge();

        event(new TopUpCompletedEvent([
            'id' => $this->topUpOrder->id,
            'agent_id' => $this->topUpOrder->agent_id,
            'agent_type' => $this->topUpOrder->agent_type,
            'status' => $this->topUpOrder->status,
            'bulk_request_id' => $this->topUpOrder->bulk_request_id,
        ]));

        if ($this->topUp->isNotSuccessful()) {
            $this->takeUnsuccessfulAction();
        } else {
            (new Usage())->setUser($this->agent)->setType(Usage::Partner()::TOPUP_COMPLETE)->create();
            $this->takeSuccessfulAction();
        }
    }

    /**
     * @throws Exception
     */
    protected function takeUnsuccessfulAction()
    {
        $this->notifyAgentAboutFailure();
    }

    /**
     * @throws Exception
     */
    protected function takeSuccessfulAction()
    {
        //
    }

    /**
     * @throws Exception
     */
    private function notifyAgentAboutFailure()
    {
        notify($this->agent)->send([
            "title" => 'Your top up to ' . $this->topUpOrder->payee_mobile . ' has been failed.',
            "link" => '',
            "type" => notificationType('Danger')
        ]);
    }

    public function getVendor(): TopUpVendor
    {
        return $this->topUpOrder->vendor;
    }

    public function getAgent(): TopUpAgent
    {
        return $this->topUpOrder->agent;
    }

    private function handleException(Exception $e)
    {
        $payload = $this->job->getRawBody();
        $id = $this->failedJobLogger->log($this->connection, $this->queue, $payload);
        logErrorWithExtra($e, [
            config('queue.failed.table') . ".id" => $id
        ]);
    }

    protected function getTitle(): string
    {
        $agent = $this->getAgent();
        return "Top up to " . $this->topUpOrder->payee_mobile . " by " . class_basename($agent) . "#" . $agent->id;
    }
}
