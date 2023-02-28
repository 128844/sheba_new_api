<?php

namespace App\Http\Controllers\Aamarpay;

use App\Exceptions\NotFoundAndDoNotReportException;
use App\Http\Controllers\Controller;
use App\Sheba\MTB\Exceptions\MtbServiceServerError;
use App\Sheba\AamarpayOnboarding\AamarpaySavePrimaryInformation;
use App\Sheba\ResellerPayment\Exceptions\MORServiceServerError;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Sheba\TPProxy\TPProxyServerError;

class AamarpayController extends Controller
{
    /** @var AamarpaySavePrimaryInformation $aamarpaySavePrimaryInformation */
    private $aamarpaySavePrimaryInformation;

    /**
     * @param  AamarpaySavePrimaryInformation  $aamarpaySavePrimaryInformation
     */
    public function __construct(AamarpaySavePrimaryInformation $aamarpaySavePrimaryInformation)
    {
        $this->aamarpaySavePrimaryInformation = $aamarpaySavePrimaryInformation;
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     * @throws MtbServiceServerError
     * @throws NotFoundAndDoNotReportException
     * @throws MORServiceServerError
     * @throws TPProxyServerError
     */
    public function apply(Request $request): JsonResponse
    {
        $partner = $request->auth_user->getPartner();
        return $this->aamarpaySavePrimaryInformation
            ->setPartner($partner)
            ->storePrimaryInformationToAamarpay($request);
    }
}
