<?php

namespace Sheba\ShebaPay\Requests;

use App\Http\Requests\ApiRequest;
use Sheba\TopUp\ConnectionType;

class ShebaPayTopupRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => 'required|string|mobile:bd',
            'connection_type' => 'required|in:prepaid,postpaid',
            'vendor_id' => 'required|exists:topup_vendors,id',
            'amount' => $this->getAmountValidation(),
            'lat' => 'sometimes|numeric',
            'long' => 'sometimes|numeric',
            'is_otf_allow' => 'sometimes|numeric|between:0,1',
            'transaction_id' => 'required|string',
            'msisdn' => 'required|string',
            'merchant_code' => 'sometimes|string',
            'callback_url' => 'required|string'
        ];
    }
    public function isPrepaid(): bool
    {
        return $this->connection_type == ConnectionType::PREPAID;
    }

    public function isPostpaid(): bool
    {
        if ($this->connection_type == ConnectionType::POSTPAID) return true;
        return false;
    }
    private function getAmountValidation(): string
    {
        if ($this->isPostpaid()) {
            return 'required|numeric|min:10';
        } else {
            return 'required|numeric|min:10|max:1000';
        }
    }
    public function getAgent(){
        return $this->auth_user->getPartner();
    }
}