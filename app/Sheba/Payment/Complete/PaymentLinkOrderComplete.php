<?php namespace Sheba\Payment\Complete;

use App\Jobs\Partner\PaymentLink\SendPaymentLinkSms;
use App\Models\Payable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\Profile;
use App\Sheba\Pos\Order\PosOrderObject;
use DB;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Foundation\Bus\DispatchesJobs;
use LaravelFCM\Message\Exceptions\InvalidOptionsException;
use Sheba\Dal\ExternalPayment\Model as ExternalPayment;
use Sheba\Dal\POSOrder\SalesChannels;
use Sheba\ExpenseTracker\AutomaticIncomes;
use Sheba\ExpenseTracker\Repository\AutomaticEntryRepository;
use Sheba\ModificationFields;
use Sheba\PaymentLink\InvoiceCreator;
use Sheba\PaymentLink\PaymentLinkStatics;
use Sheba\PaymentLink\PaymentLinkTransaction;
use Sheba\PaymentLink\PaymentLinkTransformer;
use Sheba\Pos\Payment\Creator as PaymentCreator;
use Sheba\PushNotificationHandler;
use Sheba\Repositories\Interfaces\PaymentLinkRepositoryInterface;
use Sheba\Repositories\PaymentLinkRepository;
use Sheba\Reward\ActionRewardDispatcher;
use Sheba\Transactions\Wallet\HasWalletTransaction;
use Sheba\Usage\Usage;
use Throwable;

class PaymentLinkOrderComplete extends PaymentComplete
{
    use DispatchesJobs;
    use ModificationFields;

    /** @var PaymentLinkRepository */
    private $paymentLinkRepository;
    /** @var PaymentLinkTransformer $paymentLink */
    private $paymentLink;
    private $paymentLinkCommission;
    /** @var InvoiceCreator $invoiceCreator */
    private $invoiceCreator;
    private $target;
    private $payment_receiver;
    private $paymentLinkTax;
    /** @var PaymentLinkTransaction $transaction */
    private $transaction;

    public function __construct()
    {
        parent::__construct();
        $this->paymentLinkRepository = app(PaymentLinkRepositoryInterface::class);
        $this->invoiceCreator        = app(InvoiceCreator::class);
        $this->paymentLinkCommission = PaymentLinkStatics::get_payment_link_commission();
        $this->paymentLinkTax        = PaymentLinkStatics::get_payment_link_tax();
    }

    /**
     * @return Payment
     * @throws Throwable
     */
    public function complete()
    {
        try {
            $this->payment->reload();
            if ($this->payment->isComplete())
                return $this->payment;
            $this->paymentLink      = $this->getPaymentLink();
            $this->payment_receiver = $this->paymentLink->getPaymentReceiver();
            DB::transaction(function () {
                $this->paymentRepository->setPayment($this->payment);
                $payable = $this->payment->payable;
                $this->setModifier($customer = $payable->user);
                $this->completePayment();
                $this->processTransactions($this->payment_receiver);
            });
        } catch (Throwable $e) {
            $this->failPayment();
            throw $e;
        }
        try {
            $this->clearTarget();
            $this->storeEntry();
            $this->saveInvoice();
            $this->dispatchReward();
            $this->createUsage($this->payment_receiver, $this->payment->payable->user);
            $this->notify();

        } catch (Throwable $e) {
            logError($e);
        }
        $this->payment->reload();
        return $this->payment;
    }

    private function storeEntry()
    {

        $payable = $this->payment->payable;
        /** @var AutomaticEntryRepository $entry_repo */
        $entry_repo = app(AutomaticEntryRepository::class)
            ->setPartner($this->payment_receiver)
            ->setAmount($this->transaction->getEntryAmount())
            ->setHead(AutomaticIncomes::PAYMENT_LINK)
            ->setEmiMonth($this->transaction->getEmiMonth())
            ->setAmountCleared($this->transaction->getEntryAmount())
            ->setInterest($this->transaction->getInterest())
            ->setBankTransactionCharge($this->transaction->getFee());
        if ($this->target) {
            $entry_repo->setCreatedAt($this->target->created_at);
            $entry_repo->setSourceType($this->getSourceType());
            $entry_repo->setSourceId($this->target->id);
        }
        $payer = $this->paymentLink->getPayer();
        if (empty($payer)) {
            $payer = $payable->getUserProfile();
        }
        if ($payer instanceof Profile) {
            $entry_repo->setParty($payer);
        }
        $entry_repo->setPaymentMethod($this->payment->paymentDetails->last()->readable_method)
            ->setPaymentId($this->payment->id)
            ->setIsPaymentLink(1)
            ->setIsDueTrackerPaymentLink($this->paymentLink->isDueTrackerPaymentLink());
        if ($this->target instanceof PosOrder) {
            $entry_repo->setIsWebstoreOrder($this->target->sales_channel == SalesChannels::WEBSTORE ? 1 : 0);
            $entry_repo->updateFromSrc();
        } else {
            $entry_repo->store();
        }
    }

    private function getSourceType()
    {
        if ($this->target instanceof PosOrder) return 'PosOrder';
        if ($this->target instanceof ExternalPayment) return 'ExternalPayment';
        return null;
    }

    private function notify()
    {
        if ($this->target) {
            $payment      = $this->payment;
            $payment_link = $this->paymentLink;
            dispatch(new SendPaymentLinkSms($payment, $payment_link));
            $this->notifyManager($this->payment, $this->paymentLink);
        }
    }

    private function dispatchReward()
    {
        $payable = $this->payment->payable;
        app(ActionRewardDispatcher::class)->run('payment_link_usage', $this->payment_receiver, $this->payment_receiver, $payable);
    }

    /**
     * @return PaymentLinkTransformer
     */
    private function getPaymentLink()
    {
        try {
            return $this->paymentLinkRepository->find($this->payment->payable->type_id);
        } catch (RequestException $e) {
            throw $e;
        }
    }

    /**
     * @param HasWalletTransaction $payment_receiver
     */
    private function processTransactions(HasWalletTransaction $payment_receiver)
    {
        $this->transaction = (new PaymentLinkTransaction($this->payment, $this->paymentLink))->setReceiver($payment_receiver)->create();

    }


    private function clearTarget()
    {
        $this->target = $this->paymentLink->getTarget();
        if ($this->target instanceof PosOrderObject) {
            $payment_data    = [
                'pos_order_id' => $this->target->getId(),
                'amount'       => $this->transaction->getEntryAmount(),
                'method'       => $this->payment->payable->type,
                'emi_month'    => $this->transaction->getEmiMonth(),
                'interest'     => $this->transaction->isPaidByPartner() ? $this->transaction->getInterest() : 0
            ];
            /** @var PaymentCreator $payment_creator */
            $payment_creator = app(PaymentCreator::class);
            $payment_creator->credit($payment_data, $this->target->getType());
            if ($this->transaction->isPaidByCustomer()) {
                $this->target->update(['interest' => 0, 'bank_transaction_charge' => 0]);
            }
        }
        if ($this->target instanceof ExternalPayment) {
            $this->target->payment_id = $this->payment->id;
            $this->target->update();
            $this->paymentLinkRepository->statusUpdate($this->paymentLink->getLinkID(), 0);
        }
    }

    private function createUsage($payment_receiver, $modifier)
    {
        (new Usage())->setUser($payment_receiver)->setType(Usage::Partner()::PAYMENT_LINK)->create($modifier);
    }

    protected function saveInvoice()
    {
        try {
            $this->payment->invoice_link = $this->invoiceCreator->setPaymentLink($this->paymentLink)->setPayment($this->payment)->save();
            $this->payment->update();
        } catch (Throwable $e) {
            logError($e);
        }
        return $this->payment;
    }

    /**
     * @param Payment $payment
     * @param PaymentLinkTransformer $payment_link
     * @throws InvalidOptionsException
     */
    private function notifyManager(Payment $payment, PaymentLinkTransformer $payment_link)
    {
        $partner          = $payment_link->getPaymentReceiver();
        $topic            = config('sheba.push_notification_topic_name.manager') . $partner->id;
        $channel          = config('sheba.push_notification_channel_name.manager');
        $sound            = config('sheba.push_notification_sound.manager');
        $formatted_amount = number_format($this->transaction->getAmount(), 2);
        $event_type       = $this->target && $this->target instanceof PosOrderObject && $this->target->getSalesChannel() == SalesChannels::WEBSTORE ? 'WebstoreOrder' : (class_basename($this->target) instanceof PosOrderObject ? 'PosOrder' : class_basename($this->target));
        /** @var Payable $payable */
        $payable = Payable::find($this->payment->payable_id);
        (new PushNotificationHandler())->send([
            "title"      => 'Order Successful',
            "message"    => "$formatted_amount Tk has been collected from {$payable->getName() } by order link- {$payment_link->getLinkID()}",
            "event_type" => $event_type,
            "event_id"   => $this->target->getId(),
            "sound"      => "notification_sound",
            "channel_id" => $channel
        ], $topic, $channel, $sound);
    }
}
