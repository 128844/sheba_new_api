<?php namespace Sheba\PartnerWallet;

use App\Models\Partner;
use App\Models\PartnerOrder;
use Exception;
use Sheba\Reward\BonusCredit;

class PaymentByBonusAndWallet
{
    /** @var Partner $partner */
    private $partner;
    private $partnerTransaction;
    /** @var BonusCredit */
    private $bonus;
    private $spentOn;
    public $payFromBonus = 0;
    public $payFromWallet = 0;

    /**
     * PaymentByBonusAndWallet constructor.
     * @param Partner $partner
     * @param $spent_on
     */
    public function __construct(Partner $partner, $spent_on)
    {
        $this->partner = $partner;
        $this->partnerTransaction = new PartnerTransactionHandler($partner);
        $this->spentOn = $spent_on;
        $this->bonus = app(BonusCredit::class)->setUser($partner)->setSpentModel($spent_on);
    }

    /**
     * @param $amount
     * @param $log
     * @param $tags
     * @throws Exception
     */
    public function pay($amount, $log, $tags = null)
    {
        $this->payFromBonus = $this->partner->bonus_credit ? ($amount <= $this->partner->bonus_credit ? $amount : $this->partner->bonus_credit) : 0;
        $this->payFromWallet = $amount - $this->payFromBonus;

        $bonus_deduction_log = "$this->payFromBonus CREDIT has been deducted for subscription package change";
        if ($this->payFromBonus) $this->bonus->deduct($this->payFromBonus, $bonus_deduction_log);
        if ($this->payFromWallet) {
            $log = str_replace("%d", $this->payFromWallet, $log);
            $partner_order = $this->spentOn instanceof PartnerOrder ? $this->spentOn : null;
            $this->partnerTransaction->debit($this->payFromWallet, $log, $partner_order, $tags);
        }
    }
}
