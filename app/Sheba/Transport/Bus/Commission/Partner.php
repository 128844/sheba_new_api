<?php namespace Sheba\Transport\Bus\Commission;

use Sheba\AccountingEntry\Repository\JournalCreateRepository;
use Sheba\ExpenseTracker\AutomaticExpense;
use Sheba\ExpenseTracker\AutomaticIncomes;
use Sheba\ExpenseTracker\Repository\AutomaticEntryRepository;
use Sheba\AccountingEntry\Accounts\AccountTypes\AccountKeys\Asset\Cash;
use Sheba\Transport\Bus\BusTicketCommission;

class Partner extends BusTicketCommission
{
    private $partner;

    /**
     * @param \App\Models\Partner $partner
     * @return Partner
     */
    public function setPartner($partner)
    {
        $this->partner = $partner;
        return $this;
    }

    public function disburse()
    {
        $this->storeAgentsCommission();
        $this->storeIncomeExpense();
        $this->saleBusTicket();
    }

    private function storeIncomeExpense()
    {
        /** @var AutomaticEntryRepository $entry
         * @var \App\Models\Partner $agent
         */
        list($entry,$order) = $this->initEntry();
        $entry->setHead(AutomaticIncomes::BUS_TICKET)->setAmount($order->amount)->store();
        $entry->setHead(AutomaticExpense::BUS_TICKET)->setAmount($order->amount - $order->agent_amount)->store();
    }

    public function refund()
    {
        $this->deleteIncomeExpense();
    }

    private function deleteIncomeExpense()
    {
        /** @var AutomaticEntryRepository $entry
         * @var \App\Models\Partner $agent
         */
        list( $entry,$order) = $this->initEntry();
        $entry->setHead(AutomaticIncomes::BUS_TICKET)->delete();
        $entry->setHead(AutomaticExpense::BUS_TICKET)->delete();
    }

    /**
     * @return array
     */
    private function initEntry()
    {
        /** @var AutomaticEntryRepository $entry
         * @var \App\Models\Partner $agent
         */
        $agent = $this->agent;
        $order = $this->transportTicketOrder;
        $entry = app(AutomaticEntryRepository::class);
        $entry = $entry->setPartner($agent)->setSourceType(class_basename($order))->setSourceId($order->id);
        return [$entry, $order];
    }

    private function saleBusTicket()
    {
        $transaction = $this->getWalletTransaction();
        (new JournalCreateRepository())
            ->setTypeId($this->partner->id)
            ->setSource($transaction)
            ->setAmount($transaction->amount)
            ->setDebitAccountKey(Cash::CASH)
            ->setCreditAccountKey(AutomaticIncomes::BUS_TICKET)
            ->setDetails("Bus Ticket for sale.")
            ->setReference("write something")       //write
            ->store();
    }

    public function getWalletTransaction()
    {
        return $this->wallet_transaction;
    }
}
