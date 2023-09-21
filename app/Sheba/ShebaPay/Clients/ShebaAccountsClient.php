<?php

namespace Sheba\ShebaPay\Clients;

use Sheba\OAuth2\AccountServerAuthenticationError;
use Sheba\OAuth2\AccountServerClient;
use Sheba\OAuth2\AccountServerNotWorking;
use Sheba\OAuth2\WrongPinError;

class ShebaAccountsClient
{
    private $mobile;
    private $apiKey;
    /** @var AccountServerClient */
    private $accountClient;

    public function __construct()
    {
        $this->apiKey = config('sheba_pay.accounts.api_key');
        $this->accountClient = app(AccountServerClient::class);
    }

    /**
     * @param mixed $mobile
     */
    public function setMobile($mobile): ShebaAccountsClient
    {
        $this->mobile = $mobile;
        return $this;
    }

    /**
     * @throws AccountServerAuthenticationError
     * @throws WrongPinError
     * @throws AccountServerNotWorking
     */
    public function login()
    {
        $res = $this->accountClient->post('api/v3/profile/login-for-shebapay', ['mobile' => $this->mobile], ['x-api-key' => $this->apiKey]);
        return $res['token'];
    }
}