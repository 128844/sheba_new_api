<?php

namespace App\Http\Controllers\Mtb;

use App\Http\Controllers\Controller;
use App\Sheba\MTB\Exceptions\MtbServiceServerError;
use App\Sheba\MtbOnboarding\MtbSavePrimaryInformation;
use App\Sheba\MtbOnboarding\MtbSendOtp;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MtbController extends Controller
{
    /**
     * @var MtbSavePrimaryInformation
     */
    private $mtbSavePrimaryInformation;
    /**
     * @var MtbSendOtp
     */
    private $mtbSendOtp;

    public function __construct(MtbSavePrimaryInformation $mtbSavePrimaryInformation, MtbSendOtp $mtbSendOtp)
    {
        $this->mtbSavePrimaryInformation = $mtbSavePrimaryInformation;
        $this->mtbSendOtp = $mtbSendOtp;
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     * @throws MtbServiceServerError
     */
    public function apply(Request $request): JsonResponse
    {
        $partner = $request->auth_user->getPartner();
        return $this->mtbSavePrimaryInformation->setPartner($partner)->storePrimaryInformationToMtb($request);
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $partner = $request->auth_user->getPartner();
        $data = $this->mtbSendOtp->setPartner($partner)->sendOtp($request);
        return http_response($request, null, 200, ['message' => 'Successful', 'data' => $data]);
    }

    public function getPartnerMobileNumber(Request $request): JsonResponse
    {
        $partner = $request->auth_user->getPartner();
        $mobile = $partner->getFirstAdminResource()->profile->mobile;
        return http_response($request, null, 200, ['message' => 'Successful', 'mobile' => $mobile]);
    }

    public function statusValidate(Request $request)
    {
        $this->validate($request, ['merchant_id' => 'required|string']);
        $this->mtbSavePrimaryInformation->validateMtbAccountStatus($request->merchant_id);
        return http_response($request, null, 200, ['message' => 'Successful']);
    }
}
