<?php namespace Sheba\Payment\Complete;

use App\Models\Partner;
use DB;
use Illuminate\Database\QueryException;
use ReflectionException;
use Sheba\AccountingEntry\Accounts\Accounts;
use Sheba\AccountingEntry\Exceptions\AccountingEntryServerError;
use Sheba\AccountingEntry\Exceptions\InvalidSourceException;
use Sheba\AccountingEntry\Repository\JournalCreateRepository;
use Sheba\Dal\PaymentGateway\Contract as PaymentGatewayRepo;
use Sheba\Reward\ActionRewardDispatcher;
use Sheba\Transactions\Types;
use Sheba\Transactions\Wallet\HasWalletTransaction;
use Sheba\Transactions\Wallet\WalletTransactionHandler;

class RechargeComplete extends PaymentComplete
{
    private $transaction;

    public function complete()
    {
        try {
            $this->paymentRepository->setPayment($this->payment);
            DB::transaction(function () {
                $this->storeTransaction();
                $this->completePayment();
                $payable      = $this->payment->payable;
                $payable_user = $payable->user;
                if ($payable_user instanceof Partner) {
                    app(ActionRewardDispatcher::class)->run('partner_wallet_recharge', $payable_user, $payable_user, $payable);
                    $this->storeJournal();
                }
                $this->storeCommissionTransaction();
            });
        } catch (QueryException $e) {
            $this->failPayment();
            throw $e;
        }
        return $this->payment;
    }

    private function storeTransaction()
    {
        /** @var HasWalletTransaction $user */
        $user              = $this->payment->payable->user;
        $this->transaction = (new WalletTransactionHandler())->setModel($user)->setAmount((double)$this->payment->payable->amount)->setType(Types::credit())->setLog('Credit Purchase')->setTransactionDetails($this->payment->getShebaTransaction()->toArray())->setSource($this->payment->paymentDetails->last()->method)->store();
    }

    protected function saveInvoice()
    {
        // TODO: Implement saveInvoice() method.
    }

    private function storeCommissionTransaction()
    {
        /** @var HasWalletTransaction $user */
        $user = $this->payment->payable->user;

        $payment_gateways = app(PaymentGatewayRepo::class);
        $payment_gateway  = $payment_gateways->builder()
                                             ->where('service_type', $this->payment->created_by_type)
                                             ->where('method_name', $this->payment->paymentDetails->last()->method)
                                             ->where('status', 'Published')
                                             ->first();

        if ($payment_gateway && $payment_gateway->cash_in_charge > 0) {
            (new WalletTransactionHandler())->setModel($user)
                                            ->setAmount((double)(($payment_gateway->cash_in_charge * $this->payment->payable->amount) / 100))
                                            ->setType(Types::debit())
                                            ->setLog('Credit Purchase Gateway Charge')
                                            ->setTransactionDetails($this->payment->getShebaTransaction()->toArray())
                                            ->setSource($this->payment->paymentDetails->last()->method)
                                            ->store();
        }
    }

    /**
     * @throws ReflectionException
     * @throws AccountingEntryServerError
     * @throws InvalidSourceException
     */
    private function storeJournal()
    {
        $payable = $this->payment->payable;
        (new JournalCreateRepository())->setTypeId($payable->user->id)->setSource($this->transaction)->setAmount($payable->amount)->setDebitAccountKey((new Accounts())->asset->sheba::SHEBA_ACCOUNT)->setCreditAccountKey($this->payment->paymentDetails->last()->method)->setDetails("Entry For Wallet Transaction")->setReference($this->payment->id)->store();
    }
}
