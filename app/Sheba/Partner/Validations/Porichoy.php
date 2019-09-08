<?php namespace Sheba\Partner\Validations;


use App\Sheba\Partner\Validations\NidValidationResponse;
use Sheba\Partner\Validations\NidValidator;

class Porichoy extends NidValidator
{
    private $baseUrl;
    private $key;

    /**
     * Porichoy constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->baseUrl = env('PORICHOY_URL', 'https://kyc24nme.portal.azure-api.net');
        $index = in_array(env('APP_ENV'), ['local', 'development']) ? rand(0, 100)%2 : 0;
        $this->baseUrl .= '/' . explode(',', env('PORICHOY_TEST_URLS', 'testkyc,testkyc-fail'))[$index] . '/';
        $this->key = env('PORICHOY_KEY');
    }

    /**
     * @param $nid
     * @param null $fullName
     * @param null $dob
     * @return NidValidationResponse
     */
    function check($nid, $fullName = null, $dob = null): NidValidationResponse
    {
        $response = $this->client->post($this->baseUrl . 'check-person',
            ['form_params' => ['national_id' => $nid, 'person_fullname' => $fullName, 'person_dob' => $dob], 'headers' => ['Ocp-Apim-Subscription-Key' => $this->key]])
            ->getBody()->getContents();
        $responseType = (new NidValidationResponse())->setFromStringResponse($response,'passKyc','errorCode');
        $responseType->setStatus($responseType->getError()?0:1);
        return $responseType;
    }
}
