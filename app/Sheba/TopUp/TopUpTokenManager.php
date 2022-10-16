<?php namespace Sheba\TopUp;

use App\Models\Partner;
use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Sheba\OAuth2\AuthUser;
use Sheba\ShebaAccountKit\Requests\AccessTokenRequest;
use Sheba\ShebaAccountKit\ShebaAccountKit;
use Sheba\TopUp\Exception\InvalidTopUpTokenException;
use Sheba\TopUp\Exception\UnauthorizedTokenCreationException;

class TopUpTokenManager
{
    /** @var Partner */
    private $partner;

    public function setPartner(Partner $agent)
    {
        $this->partner = $agent;
        return $this;
    }

    /**
     * @param $authorization_code
     * @return string
     * @throws UnauthorizedTokenCreationException
     */
    public function generate($authorization_code)
    {
        $otp_number = $this->getOtpNumber($authorization_code);
        $resource_number = $this->partner->getContactNumber();
        if ($otp_number != $resource_number) throw new UnauthorizedTokenCreationException();

        $time_since_midnight_after_one_week = time() - strtotime("midnight". " +1 week");
        $remaining_time = (24 * 3600) - $time_since_midnight_after_one_week;

        $payload = [
            'iss' => "topup-jwt",
            'sub' => $this->partner->id,
            'iat' => time(),
            'exp' => time() + $remaining_time
        ];

        return JWT::encode($payload, config('jwt.secret'));
    }

    private function getOtpNumber($authorization_code)
    {
        $access_token_request = (new AccessTokenRequest())->setAuthorizationCode($authorization_code);

        /** @var ShebaAccountKit $sheba_account_kit */
        $sheba_account_kit = app(ShebaAccountKit::class);
        return $sheba_account_kit->getMobile($access_token_request);
    }

    /**
     * @throws InvalidTopUpTokenException
     */
    public function validate($token)
    {
        try {
            $payload = JWT::decode($token, config('jwt.secret'), ['HS256']);

            if ($payload->sub != $this->partner->id) {
                throw new InvalidTopUpTokenException("Not a valid partner");
            }
        } catch (ExpiredException $e) {
            throw new InvalidTopUpTokenException($e->getMessage(), 410, $e);
        } catch (Exception $e) {
            throw new InvalidTopUpTokenException($e->getMessage(), 406, $e);
        }
    }
}