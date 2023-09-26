<?php

namespace Sheba\ShebaPay\Requests;

use App\Http\Requests\ApiRequest;

class ShebaPayTopupStatusRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'topup_order_id'=>'required|numeric|exists:topup_orders,id'
        ];
    }
    public function getAgent(){
        return $this->auth_user->getPartner();
    }
}