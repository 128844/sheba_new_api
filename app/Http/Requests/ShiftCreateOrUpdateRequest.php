<?php

namespace App\Http\Requests;

use Sheba\Business\ShiftSetting\Requester as ShiftSettingRequest;


class ShiftCreateOrUpdateRequest extends ApiRequest
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if ($this->isColorRequest()) {
            return [
                'color' => 'required|string|regex:/^#[a-zA-Z0-9]{6}/'
            ];
        }

        return [
            'name' => 'required|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'is_checkin_grace_allow' => 'required|in:0,1',
            'is_checkout_grace_allow' => 'required|in:0,1',
            'checkin_grace_time' => 'required_if:is_checkin_grace_allow, == , 1',
            'checkout_grace_time' => 'required_if:is_checkout_grace_allow, == , 1',
            'is_half_day' => 'required|in:0,1'
        ];
    }

    private function isColorRequest()
    {
        return ends_with($this->path(), "/color");
    }

    public function buildRequest()
    {
        $request = new ShiftSettingRequest();

        if ($this->isColorRequest()) {
            $request->setColor($this->input('color'));

            return $request;
        }

        $request
            ->setName($this->input('name'))
            ->setTitle($this->input('title'))
            ->setStartTime($this->input('start_time'))
            ->setEndTime($this->input('end_time'))
            ->setIsCheckInGraceAllowed($this->input('is_checkin_grace_allow'))
            ->setIsCheckOutGraceAllowed($this->input('is_checkout_grace_allow'))
            ->setCheckInGraceTime($this->input('checkin_grace_time'))
            ->setCheckOutGraceTime($this->input('checkout_grace_time'))
            ->setIsHalfDayActivated($this->input('is_half_day'));

        return $request;
    }
}
