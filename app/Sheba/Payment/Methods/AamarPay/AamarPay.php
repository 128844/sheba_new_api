<?php

namespace App\Sheba\Payment\Methods\AamarPay;

use App\Models\Payable;
use App\Models\Payment;
use App\Sheba\Payment\Methods\AamarPay\Response\InitResponse;
use App\Sheba\Payment\Methods\AamarPay\Response\ValidationResponse;
use App\Sheba\Payment\Methods\AamarPay\Stores\AamarPayDynamicStore;
use App\Sheba\Payment\Methods\AamarPay\Stores\DynamicAamarPayStoreConfigurations;
use Illuminate\Support\Facades\Log;
use Sheba\Payment\Exceptions\FailedToInitiate;
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
        if ($payable->emi_month > 0 && $this->configuration->getApiKey() === null) {
            throw new FailedToInitiate('Api key not found');
        }
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
        $payable = $payment->payable;
        $this->setConfiguration($this->getCredentials($payable));
        $response = $this->getPaymentStatusFromAamarpay($payment->transaction_id);
        $validation_response = new ValidationResponse();
        $validation_response->setResponse($response)->setPayment($payment);
        $this->paymentLogRepo->setPayment($payment);
        if ($validation_response->hasSuccess()) {
            $success = $validation_response->getSuccess();
            $this->paymentLogRepo->create([
                'to' => Statuses::VALIDATED,
                'from' => $payment->status,
                'transaction_details' => $payment->transaction_details
            ]);
            $payment->gateway_transaction_id = $success->id;
            $payment->status = Statuses::VALIDATED;
            $payment->transaction_details = json_encode($success->details);

            if ($payable->emi_month > 0) {
                $this->sendEmiRequest($payment);
            }
        } else {
            $error = $validation_response->getError();
            $this->paymentLogRepo->create([
                'to' => Statuses::VALIDATION_FAILED,
                'from' => $payment->status,
                'transaction_details' => $payment->transaction_details
            ]);
            if ($error->id) {
                $payment->gateway_transaction_id = $error->id;
            }
            $payment->status = Statuses::VALIDATION_FAILED;
            $payment->transaction_details = json_encode($error->details);
        }
        $payment->update();
        return $payment;
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
            'cus_add1' => 'House #57, Road #25',
            'cus_add2' => 'Banani',
            'cus_city' => 'Dhaka',
            'cus_state' => 'Dhaka',
            'cus_postcode' => '1213',
            'cus_country' => 'Bangladesh',
            'cus_phone' => $payable->getMobile(),
        ];
        $request = (new TPRequest())->setUrl($this->baseUrl . '/request.php')
            ->setMethod(TPRequest::METHOD_POST)->setInput($data);
        return $this->tpClient->call($request);
    }

    public function getPaymentStatusFromAamarpay($transactionId)
    {
        $request = (new TPRequest())->setUrl($this->baseUrl . "/api/v1/trxcheck/request.php?request_id={$transactionId}&store_id={$this->configuration->getStoreId()}&signature_key={$this->configuration->getSignatureKey()}&type=json")
            ->setMethod(TPRequest::METHOD_GET);
        return $this->tpClient->call($request);
    }

    public function sendEmiRequest(Payment $payment)
    {
        /** @var Payable $payable */
        $payable = $payment->payable;

        $bank = $payable->emiBank;

        $monthlyAmount = $payable->amount / $payable->emi_month;
        $this->setConfiguration($this->getCredentials($payable));

        $data = [
            'api_key' => $this->configuration->getApiKey(),
            'store_id' => $this->configuration->getStoreId(),
            'pg_trxnid' => $payment->gateway_transaction_id,
            'amount' => $payable->amount,
            'tenure' => $payable->emi_month,
            'monthly_amount' => $monthlyAmount,
            'trxn_date' => date('Y-m-d H:i:s'),
            'bank_name' => $bank->name,
            'emi_details' => "{$payable->emi_month} months - BDT {$monthlyAmount} | EMI Charges Payable @ 1.6%",
        ];

        $request = (new TPRequest())->setUrl(config('payment.aamarpay.emi_process_url'))
            ->setMethod(TPRequest::METHOD_POST)
            ->setHeaders(['Content-Type:application/json'])
            ->setInput($data);
        $response = $this->tpClient->call($request);
        if ($response[0]->code != 200) {
            $response = json_encode($response);
            Log::info("aamarpay emi response: pg_id: {$payment->gateway_transaction_id} payment_id: {$payment->id} response: {$response}");
        }
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

    public function getCalculatedChargedAmount($transaction_details)
    {
        if (isset($transaction_details->status_code) && $transaction_details->status_code == ValidationResponse::SUCCESS_CODE) {
            return $transaction_details->processing_charge;
        }
    }
}