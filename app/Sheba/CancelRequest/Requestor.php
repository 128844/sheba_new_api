<?php namespace Sheba\CancelRequest;

use App\Models\Job;

use Sheba\Dal\Payable\Types;
use Sheba\Dal\Payment\PaymentRepositoryInterface;
use Sheba\Repositories\CancelRequestRepository;
use Sheba\Repositories\JobRepository;
use Sheba\UserAgentInformation;

abstract class Requestor
{
    /** @var Job */
    protected $job;
    private $reason;
    private $cancelRequests;
    private $jobRepo;
    private $isEscalated;
    /** @var PaymentRepositoryInterface */
    private $paymentRepository;
    /** @var UserAgentInformation */
    private $userAgentInformation;

    public function __construct(CancelRequestRepository $cancel_requests, JobRepository $job_repo, PaymentRepositoryInterface $paymentRepository)
    {
        $this->cancelRequests = $cancel_requests;
        $this->jobRepo = $job_repo;
        $this->paymentRepository = $paymentRepository;
    }

    public function hasError()
    {
        $this->setJob($this->job->fresh());
        if ($this->job->isClosed()) return ['code' => 422, 'msg' => 'You are not authorized to send cancel request to this stage.'];
        if ($this->cancelRequests->isDuplicatedRequest($this->job)) return ['code' => 422, 'msg' => 'Already send a cancelled request'];
        if ($this->hasOngoingPayment()) return ['code' => 422, 'msg' => 'Customer is trying to pay for this order.'];
        return false;
    }

    public function setJob(Job $job)
    {
        $this->job = $job;
        return $this;
    }

    public function setReason($reason)
    {
        $this->reason = $reason;
        return $this;
    }

    public function setEscalatedStatus($escalated_status)
    {
        $this->isEscalated = $escalated_status;
        return $this;
    }

    public function setUserAgentInformation($userAgentInformation)
    {
        $this->userAgentInformation = $userAgentInformation;
        return $this;
    }

    abstract function request();

    abstract protected function getUserType();

    abstract protected function notify();

    protected function saveToDB()
    {
        $data = [
            'job_id' => $this->job->id,
            'cancel_reason' => $this->reason,
            'from_status' => $this->job->status,
            'is_escalated' => $this->isEscalated,
            'portal_name' => $this->userAgentInformation->getPortalName(),
            'ip' => $this->userAgentInformation->getIp(),
            'user_agent' => $this->userAgentInformation->getUserAgent(),
            'created_by_type' => $this->getUserType()
        ];
        $this->cancelRequests->create($data);
    }

    protected function freeResource()
    {
        if (!empty($this->job->resource)) {
            scheduler($this->job->resource)->release($this->job);
            $this->jobRepo->update($this->job, ['resource_id' => null]);
        }
    }

    /**
     * @return bool
     */
    private function hasOngoingPayment()
    {
        return $this->paymentRepository->getOngoingPaymentsFor(Types::PARTNER_ORDER, $this->job->partner_order_id)->count() > 0;
    }
}