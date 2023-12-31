<?php namespace Sheba\Payment\Complete;

use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use ReflectionException;
use Sheba\AccountingEntry\Accounts\Accounts;
use Sheba\AccountingEntry\Exceptions\AccountingEntryServerError;
use Sheba\AccountingEntry\Exceptions\InvalidSourceException;
use Sheba\AccountingEntry\Exceptions\KeyNotFoundException;
use Sheba\AccountingEntry\Repository\JournalCreateRepository;
use Sheba\Dal\PaymentGateway\Contract as PaymentGatewayRepo;
use Sheba\Reward\ActionRewardDispatcher;
use Sheba\Transactions\Types;
use Sheba\Transactions\Wallet\HasWalletTransaction;
use Sheba\Transactions\Wallet\WalletDebitForbiddenException;
use Sheba\Transactions\Wallet\WalletTransactionHandler;

class RechargeComplete extends PaymentComplete
{
    private $transaction;
    private $paymentGateway;

    public function complete()
    {
        try {
            $this->payment->reload();
            if ($this->payment->isComplete()) return $this->payment;
            $this->paymentRepository->setPayment($this->payment);
            DB::transaction(function () {
                $this->storeTransaction();
                if ($this->transaction) {
                    $this->completePayment();
                    $this->storeCommissionTransaction();
                }
            });
            $payable = $this->payment->payable;
            $payable_user = $payable->user;

            app(ActionRewardDispatcher::class)->run('wallet_recharge', $payable_user, $payable_user, $payable);

            if ($payable_user instanceof Partner) {
                $this->storeJournal();
            }
            return $this->payment;
        } catch (QueryException $e) {
            $this->failPayment();
            throw $e;
        } catch (WalletDebitForbiddenException $e) {
            $this->failPayment();
            throw $e;
        }
    }

    /**
     * @throws WalletDebitForbiddenException
     */
    private function storeTransaction()
    {
        /** @var HasWalletTransaction $user */
        $user = $this->payment->payable->user;
        $this->transaction = (new WalletTransactionHandler())->setModel($user)->setAmount((double)$this->payment->payable->amount)->setType(Types::credit())->setLog('Credit Purchase')->setTransactionDetails($this->payment->getShebaTransaction()->toArray())->setSource($this->payment->paymentDetails->last()->method)->store();
    }

    protected function saveInvoice()
    {
        // TODO: Implement saveInvoice() method.
    }

    /**
     * @param $charge
     * @return float
     */
    private function calculateCommission($charge): float
    {
        if ($this->payment->payable->user instanceof Partner) return round(($this->payment->payable->amount / (100 + $charge)) * $charge, 2);
        return (double)round(($charge * $this->payment->payable->amount) / 100, 2);
    }

    private function storeCommissionTransaction()
    {
        /** @var HasWalletTransaction $user */
        $user = $this->payment->payable->user;

        $payment_gateways = app(PaymentGatewayRepo::class);
        $this->paymentGateway = $payment_gateways->builder()
            ->where('service_type', $this->payment->created_by_type)
            ->where('method_name', $this->payment->paymentDetails->last()->method)
            ->where('status', 'Published')
            ->first();

        if ($this->paymentGateway && $this->paymentGateway->cash_in_charge > 0) {
            $amount = $this->calculateCommission($this->paymentGateway->cash_in_charge);
            (new WalletTransactionHandler())->setModel($user)
                ->setAmount($amount)
                ->setIsNegativeDebitAllowed(true)
                ->setType(Types::debit())
                ->setLog($amount . ' BDT has been deducted as a gateway charge for SHEBA credit recharge')
                ->setTransactionDetails($this->payment->getShebaTransaction()->toArray())
                ->setSource($this->payment->paymentDetails->last()->method)
                ->store();
        }
    }

    /**
     * @throws AccountingEntryServerError
     * @throws InvalidSourceException|KeyNotFoundException
     */
    private function storeJournal()
    {
        $payable = $this->payment->payable;
        $commission = isset($this->paymentGateway) ? $this->calculateCommission($this->paymentGateway->cash_in_charge) : 0;
        (new JournalCreateRepository())->setTypeId($payable->user->id)
            ->setSource($this->transaction)->setAmount($payable->amount)
            ->setDebitAccountKey((new Accounts())->asset->sheba::SHEBA_ACCOUNT)
            ->setCreditAccountKey($this->payment->paymentDetails->last()->method)
            ->setDetails("Entry For Wallet Transaction")
            ->setCommission($commission)->setEndPoint("api/journals/wallet")
            ->setReference($this->payment->id)->store();
    }
}
