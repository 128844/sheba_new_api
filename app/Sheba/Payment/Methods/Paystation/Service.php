<?php

namespace Sheba\Payment\Methods\Paystation;

use App\Models\Payment;
use Sheba\Payment\Methods\Paystation\Response\InitResponse;
use Sheba\Payment\Methods\Paystation\Response\ValidateResponse;
use Sheba\Payment\Methods\Response\PaymentMethodResponse;

class Service
{
    private $merchantId;
    private $password;

    /** @var Client */
    private $client;

    /** @var Payment */
    private $payment;

    public function __construct(Client $client)
    {
        $this->merchantId = config('payment.paystation.merchant_id');
        $this->password = config('payment.paystation.password');

        $this->client = $client;
    }

    /**
     * @param Payment $payment
     * @return Service
     */
    public function setPayment(Payment $payment)
    {
        $this->payment = $payment;
        return $this;
    }

    /**
     * @return string
     */
    public function grantToken()
    {
        return $this->client->post('grant-token', [], [
            'merchantId' => $this->merchantId,
            'password' => $this->password
        ])->token;
    }

    /**
     * @return array
     */
    private function buildTokenHeader()
    {
        return ['token' => $this->grantToken()];
    }

    /**
     * @return InitResponse|PaymentMethodResponse
     */
    public function createPayment()
    {
        $res = $this->client->post('/create-payment', $this->buildCreateData(), $this->buildTokenHeader());

        return (new InitResponse())->setPayment($this->payment)->setResponse($res);
    }

    private function buildCreateData()
    {
        $payable = $this->payment->payable;
        $profile = $payable->getUserProfile();

        return [
            'invoice_number' => $this->getInvoiceNumber(),
            'currency' => "BDT",
            'payment_amount' => $payable->amount,
            'reference' => $this->payment->transaction_id,
            'cust_name' => $profile->name,
            'cust_phone' => $profile->mobile,
            'cust_email' => $profile->email ?: config('sheba.email'),
            'cust_address' => $profile->address ?: config('sheba.address'),
            'callback_url' => config('payment.paystation.urls.ipn'),
            'checkout_items' => $payable->type,
            'preferred_gateway' => 2,
        ];
    }

    /**
     * @return ValidateResponse|PaymentMethodResponse
     */
    public function retrieveTransaction()
    {
        $res = $this->client->post("retrive-transaction", $this->buildValidationData(), $this->buildTokenHeader());

        return (new ValidateResponse())->setPayment($this->payment)->setResponse($res);
    }

    private function buildValidationData()
    {
        return [
            'invoice_number' => $this->getInvoiceNumber(),
            'trx_id' => $this->payment->gateway_transaction_id,
        ];
    }

    private function getInvoiceNumber()
    {
        // should be $this->payment->transaction_id, but only 20 characters supported.
        return "" . $this->payment->id;
    }
}
