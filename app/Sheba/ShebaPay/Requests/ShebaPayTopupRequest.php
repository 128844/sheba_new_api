<?php

namespace Sheba\ShebaPay\Requests;

use App\Http\Requests\ApiRequest;

class ShebaPayTopupRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $agent = $this->request->user;
        return [
            'mobile' => 'required|string|mobile:bd',
            'connection_type' => 'required|in:prepaid,postpaid',
            'vendor_id' => 'required|exists:topup_vendors,id',
            'amount' => 'required|numeric|min:10',
            'lat' => 'sometimes|numeric',
            'long' => 'sometimes|numeric',
            'is_otf_allow' => 'sometimes|numeric|between:0,1',
            'transaction_id' => 'required|string',
            'msisdn' => 'required|string',
            'merchant_code' => 'required|string',
            'callback_url' => 'required|string'
        ];
    }
}