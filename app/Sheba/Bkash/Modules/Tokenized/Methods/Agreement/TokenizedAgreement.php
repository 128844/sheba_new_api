<?php namespace Sheba\Bkash\Modules\Tokenized\Methods\Agreement;


use Sheba\Bkash\Modules\Tokenized\Methods\Agreement\Responses\CreateResponse;
use Sheba\Bkash\Modules\Tokenized\Methods\Agreement\Responses\ExecuteResponse;
use Sheba\Bkash\Modules\Tokenized\TokenizedModule;

class TokenizedAgreement extends TokenizedModule
{

    /**
     * @param $payer_reference
     * @param $callback_url
     * @return CreateResponse
     */
    public function create($payer_reference, $callback_url)
    {
        $create_pay_body = json_encode(array(
            'payerReference' => (string)$payer_reference,
            'callbackURL' => $callback_url,
        ));
        $curl = curl_init($this->bkashAuth->url . '/checkout/agreement/create');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->getHeader());
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $create_pay_body);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        $result_data = curl_exec($curl);
        if (curl_errno($curl) > 0) throw new \InvalidArgumentException('Bkash create API error.');
        curl_close($curl);
        return (new CreateResponse())->setResponse(json_decode($result_data));
    }

    public function execute($payment_id)
    {
        $post_fields = json_encode(['paymentID' => $payment_id]);
        $curl = curl_init($this->bkashAuth->url . '/checkout/agreement/execute');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->getHeader());
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        $result_data = curl_exec($curl);
        if (curl_errno($curl) > 0) throw new \InvalidArgumentException('API error.');
        curl_close($curl);
        return (new ExecuteResponse())->setResponse(json_decode($result_data));
    }

    /**
     * @return array
     */
    private function getHeader()
    {
        $header = array(
            'Content-Type:application/json',
            'authorization:' . $this->getToken(),
            'x-app-key:' . $this->bkashAuth->appKey);
        return $header;
    }
}