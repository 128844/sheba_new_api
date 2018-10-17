<?php namespace Sheba\Payment\Complete;

use App\Models\PartnerOrder;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Sheba\RequestIdentification;

class OrderComplete extends PaymentComplete
{
    public function complete()
    {
        $has_error = false;
        try {
            if ($this->payment->isComplete()) return $this->payment;
            $client = new Client();
            $payable = $this->payment->payable;
            $partner_order = PartnerOrder::find((int)$payable->type_id);
            $customer = $payable->user;
            foreach ($this->payment->paymentDetails as $paymentDetail) {
                $res = $client->request('POST', config('sheba.admin_url') . '/api/partner-order/' . $partner_order->id . '/collect',
                    [
                        'form_params' => array_merge([
                            'customer_id' => $customer->id,
                            'remember_token' => $customer->remember_token,
                            'sheba_collection' => (double)$paymentDetail->amount,
                            'payment_method' => $paymentDetail->method,
                            'created_by_type' => 'App\\Models\\Customer',
                            'transaction_detail' => $this->payment->transaction_details
                        ], (new RequestIdentification())->get())
                    ]);
                $response = json_decode($res->getBody());
                if ($response->code == 200) {
                    if (strtolower($paymentDetail->method) == 'wallet') dispatchReward()->run('wallet_cashback', $customer, $paymentDetail->amount, $partner_order);
                } else {
                    $has_error = true;
                }
            }

        } catch (RequestException $e) {
            $this->payment->status = 'failed';
            $this->payment->update();
            throw $e;
        }
        if ($has_error) {
            $this->payment->status = 'completed';
            $this->payment->update();
        }
        return $this->payment;
    }
}