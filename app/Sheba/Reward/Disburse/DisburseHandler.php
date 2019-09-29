<?php namespace Sheba\Reward\Disburse;

use App\Models\Customer;
use App\Models\Partner;
use App\Models\Reward;

use Sheba\CustomerWallet\CustomerTransactionHandler;
use Sheba\PartnerWallet\PartnerTransactionHandler;
use Sheba\Repositories\BonusRepository;
use Sheba\Repositories\CustomerRepository;
use Sheba\Repositories\PartnerRepository;
use Sheba\Repositories\RewardLogRepository;
use Sheba\Reward\Event;
use Sheba\Reward\Rewardable;

class DisburseHandler
{
    /** @var RewardLogRepository */
    private $rewardRepo;
    /** @var Reward */
    private $reward;
    /** @var BonusRepository */
    private $bonusRepo;
    /** @var Event $event */
    private $event;

    public function __construct(RewardLogRepository $log_repository, BonusRepository $bonus_repository)
    {
        $this->rewardRepo = $log_repository;
        $this->bonusRepo  = $bonus_repository;
    }

    public function setReward(Reward $reward)
    {
        $this->reward = $reward;
        return $this;
    }

    public function setEvent(Event $event)
    {
        $this->event = $event;
        return $this;
    }

    /**
     * @param Rewardable $rewardable
     * @throws \Exception
     */
    public function disburse(Rewardable $rewardable)
    {
        $amount = $this->reward->getAmount();
        if ($amount > 0) {
            if ($this->reward->isValidityApplicable()) {
                $this->bonusRepo->storeFromReward($rewardable, $this->reward, $amount);
            } else {
                if ($this->reward->isCashType()) {
                    $log = $amount . " BDT credited for " . $this->reward->name . " reward";

                    if ($rewardable instanceof Partner) {
                        (new PartnerTransactionHandler($rewardable))->credit($amount, $log);
                    } elseif ($rewardable instanceof Customer) {
                        (new CustomerTransactionHandler($rewardable))->credit($amount, $log);
                    }
                } elseif ($this->reward->isPointType()) {
                    if ($rewardable instanceof Partner) {
                        (new PartnerRepository(new Partner()))->updateRewardPoint($rewardable, $amount);
                    } elseif ($rewardable instanceof Customer) {
                        (new CustomerRepository())->updateRewardPoint($rewardable, $amount);
                    }
                }
            }
        }

        $reward_log = $this->event->getLogEvent();
        $this->storeRewardLog($rewardable, $reward_log);
    }

    /**
     * @param $rewardable
     * @param $log
     */
    private function storeRewardLog($rewardable, $log)
    {
        $this->rewardRepo->storeLog($this->reward, $rewardable->id, $log);
    }
}