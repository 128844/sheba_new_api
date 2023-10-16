<?php namespace Sheba\TopUp\Commission;

use App\Sheba\UserMigration\UserMigrationRepository;
use Exception;
use ReflectionException;
use Sheba\AccountingEntry\Exceptions\AccountingEntryServerError;
use Sheba\AccountingEntry\Exceptions\InvalidSourceException;
use Sheba\AccountingEntry\Exceptions\KeyNotFoundException;
use Sheba\ExpenseTracker\AutomaticExpense;
use Sheba\ExpenseTracker\AutomaticIncomes;
use Sheba\ExpenseTracker\Exceptions\ExpenseTrackingServerError;
use Sheba\ExpenseTracker\Repository\AutomaticEntryRepository;
use Sheba\AccountingEntry\Repository\JournalCreateRepository;
use Sheba\AccountingEntry\Accounts\AccountTypes\AccountKeys;
use Sheba\TopUp\TopUpCommission;

class Partner extends TopUpCommission
{
    /**
     * @throws AccountingEntryServerError
     * @throws InvalidSourceException
     * @throws ReflectionException
     * @throws KeyNotFoundException
     * @throws ExpenseTrackingServerError
     */
    public function disburse()
    {
        $this->storeAgentsCommission();
    }

    /**
     * @throws AccountingEntryServerError
     * @throws InvalidSourceException
     * @throws KeyNotFoundException
     */
    public function storeTopUpJournal()
    {
        /** @var \App\Models\Partner $partner */
        $partner = $this->agent;
        if (!$this->transaction) {
            return;
        }
        (new JournalCreateRepository())
            ->setTypeId($partner->id)
            ->setSource($this->transaction)
            ->setAmount($this->transaction->amount)
            ->setDebitAccountKey(AccountKeys\Asset\Cash::CASH)
            ->setCreditAccountKey(AccountKeys\Income\TopUp::TOP_UP)
            ->setDetails("TopUp for sale")
            ->setReference("TopUp sales amount is " . $this->transaction->amount . " tk.")
            ->setCommission($this->amount - $this->transaction->amount)
            ->setEndPoint('api/journals/top-up-sync')
            ->store();
    }

    /**
     * @throws ExpenseTrackingServerError
     * @throws Exception
     */
    private function storeExpenseIncome()
    {
        /** @var \App\Models\Partner $partner */
        $partner = $this->agent;
        if ($partner->isMigrated("expense")){
            return ;
        }
        $income = $this->amount;
        $cost = $this->amount - $this->topUpOrder->agent_commission;

        /** @var AutomaticEntryRepository $entryRepo */
        $entryRepo = app(AutomaticEntryRepository::class);

        $entryRepo = $entryRepo->setPartner($partner)->setSourceType(class_basename($this->topUpOrder))->setSourceId($this->topUpOrder->id);
        $entryRepo->setAmount($income)->setHead(AutomaticIncomes::TOP_UP)->store();
        $entryRepo->setAmount($cost)->setHead(AutomaticExpense::TOP_UP)->store();
    }

    public function refund()
    {
        $this->refundAgentsCommission();
        $this->deleteExpenseIncome();
        $this->refundTopUpJournal();
    }

    private function refundTopUpJournal()
    {
        /** @var \App\Models\Partner $partner */
        $partner = $this->agent;
        if (!$this->transaction) {
            return;
        }
        (new JournalCreateRepository())
            ->setTypeId($partner->id)
            ->setSource($this->transaction)
            ->setAmount($this->transaction->amount)
            ->setDebitAccountKey(AccountKeys\Asset\Sheba::SHEBA_ACCOUNT)
            ->setCreditAccountKey(AccountKeys\Income\Refund::GENERAL_REFUNDS)
            ->setDetails("Refund TopUp")
            ->setReference("TopUp refunds amount is" . $this->transaction->amount . " tk.")
            ->store();
    }

    private function deleteExpenseIncome()
    {

        /** @var \App\Models\Partner $partner */
        $partner = $this->agent;
        /** @var AutomaticEntryRepository $entryRepo */
        $entryRepo = app(AutomaticEntryRepository::class);
        $entryRepo = $entryRepo->setPartner($partner)->setSourceType(class_basename($this->topUpOrder))->setSourceId($this->topUpOrder->id);
        $entryRepo->delete();
    }
}
