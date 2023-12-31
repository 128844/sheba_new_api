<?php

namespace Sheba\ShebaPay\Requests;

use App\Http\Requests\ApiRequest;
use Sheba\Gender\Gender;

class ShebaPayRegistrationRequest extends ApiRequest
{
    public function __construct(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null)
    {
        parent::__construct($query, $request, $attributes, $cookies, $files, $server, $content);
        $this->merge($this->all());
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => 'required|string|mobile:bd',
            'company_name' => 'required|string|min:4',
            'merchant_code' => 'required|string',
            'name' => 'sometimes|string',
            'package_id' => 'exists:partner_subscription_packages,id',
            'billing_type' => 'in:monthly,yearly',
            'has_webstore' => 'sometimes|numeric|between:0,1',
            'address' => 'sometimes|string',
            'gender' => 'sometimes|string|in:' . Gender::implode(),
            'business_type' => 'sometimes|string',
            'geo' => 'sometimes|string'
        ];
    }

    public function all(): array
    {
        $all = parent::all();
        $all['mobile'] = $this->has('mobile') ? formatMobile($this->input('mobile')) : null;
        $all = array_merge($all, ['phone' => $all['mobile'], 'number' => $all['mobile']]);
        $all['registration_channel'] = 'ShebaPay';
        $all['billing_type'] = $this->has('billing_type') ? $this->get('billing_type') : 'monthly';
        $all['package_id'] = $this->has('package_id') ? $this->get('package_id') : config('sheba.partner_lite_packages_id');
        $all['from'] = 'ShebaPay';
        $all['portal_name'] = 'ShebaPay';
        return $all;
    }

    public function get($key, $default = null)
    {
        $all = $this->all();
        if (array_key_exists($key, $all)) return $all[$key];
        return parent::get($key, $default);
    }

    public function __get($key)
    {
        $all = $this->all();
        if (array_key_exists($key, $all)) return $all[$key];
        return parent::__get($key);
    }

}