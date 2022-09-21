<?php

namespace App\Sheba\Payment\Methods\AamarPay;

use App\Models\Payable;
use App\Models\Payment;
use App\Sheba\Payment\Methods\AamarPay\Response\InitResponse;
use App\Sheba\Payment\Methods\AamarPay\Stores\AamarPayDynamicStore;
use App\Sheba\Payment\Methods\AamarPay\Stores\DynamicAamarPayStoreConfigurations;
use Sheba\Payment\Factory\PaymentStrategy;
use Sheba\Payment\Methods\PaymentMethod;
use Sheba\Payment\Statuses;
use Sheba\TPProxy\TPProxyClient;
use Sheba\TPProxy\TPRequest;
use Sheba\Transactions\Wallet\HasWalletTransaction;

class AamarPay extends PaymentMethod
{
    private $name;
    private $tpClient;
    private $baseUrl;
    /**
     * @var DynamicAamarPayStoreConfigurations
     */
    private $configuration;

    public function __construct(TPProxyClient $tpClient)
    {
        parent::__construct();
        $this->name = PaymentStrategy::AAMARPAY;
        $this->tpClient = $tpClient;
        $this->baseUrl = config('payment.aamarpay.base_url');
        $this->successUrl = config('payment.aamarpay.success_url');
        $this->failUrl = config('payment.aamarpay.fail_url');
        $this->cancelUrl = config('payment.aamarpay.cancel_url');
    }

    public function init(Payable $payable): Payment
    {
        if (!$payable->isPaymentLink()) throw  new \Exception('Only Payment Link payment will work');
        $this->setConfiguration($this->getCredentials($payable));
        $payment = $this->createPayment($payable, $this->name);
        $response = $this->createAamarpaySession($payment);
        $init_response = new InitResponse();
        $init_response->setResponse($response);
        if ($init_response->hasSuccess()) {
            $success = $init_response->getSuccess();
            $payment->transaction_details = json_encode($response);
            $payment->redirect_url = $success->redirect_url;
            $payment->gateway_transaction_id = $success->id;
        } else {
            $error = $init_response->getError();
            $this->paymentLogRepo->setPayment($payment);
            $this->paymentLogRepo->create([
                'to' => Statuses::INITIATION_FAILED,
                'from' => $payment->status,
                'transaction_details' => json_encode($error->details)
            ]);
            $payment->status = Statuses::INITIATION_FAILED;
            $payment->transaction_details = json_encode($error->details);
        }
        $payment->update();
        return $payment;
    }

    public function validate(Payment $payment): Payment
    {
        // TODO: Implement validate() method.
    }

    public function getMethodName()
    {
        return $this->name;
    }

    public function createAamarpaySession(Payment $payment)
    {
        $payable = $payment->payable;
        $data = [
            'store_id' => $this->configuration->getStoreId(),
            'signature_key' => $this->configuration->getSignatureKey(),
            'tran_id' => $payment->transaction_id,
            'success_url' => $this->successUrl,
            'fail_url' => $this->failUrl,
            'cancel_url' => $this->cancelUrl,
            'amount' => (double)$payable->amount,
            'currency' => 'BDT',
            'desc' => 'payment through payment link',
            'cus_name' => $payable->getName(),
            'cus_email' => $payable->getEmail() ?? 'payment@smanager.com',
            'cus_add1' => 'test',
            'cus_add2' => 'test',
            'cus_city' => 'test',
            'cus_state' => 'test',
            'cus_postcode' => 'test',
            'cus_country' => 'Bangladesh',
            'cus_phone' => $payable->getMobile(),
        ];
        $request = (new TPRequest())->setUrl($this->baseUrl . '/request.php')
            ->setMethod(TPRequest::METHOD_POST)->setInput($data);
        return $this->tpClient->call($request);
    }

    private function getReceiver(Payable $payable): HasWalletTransaction
    {
        $payment_link = $payable->getPaymentLink();
        return $payment_link->getPaymentReceiver();
    }

    private function getCredentials(Payable $payable): DynamicAamarPayStoreConfigurations
    {
        $aamarPay = new AamarPayDynamicStore();
        $aamarPay->setPartner($this->getReceiver($payable));
        $gateway_account = $aamarPay->getStoreAccount($this->name);
        return (new DynamicAamarPayStoreConfigurations())->decryptAndSetConfigurations($gateway_account->configuration);
    }

    public function setConfiguration(DynamicAamarPayStoreConfigurations $configuration): self
    {
        $this->configuration = $configuration;
        return $this;
    }
}