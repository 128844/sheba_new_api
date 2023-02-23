<?php

namespace App\Sheba\AamarpayOnboarding;

use App\Exceptions\NotFoundAndDoNotReportException;
use App\Models\Partner;
use App\Sheba\Aamarpay\AamarpayConstants;
use App\Sheba\MerchantEnrollment\PersonalInformation;
use App\Sheba\MTB\Exceptions\MtbServiceServerError;
use App\Sheba\Aamarpay\Validation\ApplyValidation;
use App\Sheba\ResellerPayment\Exceptions\MORServiceServerError;
use App\Sheba\ResellerPayment\MORServiceClient;
use App\Sheba\ResellerPayment\PaymentService;
use Illuminate\Http\JsonResponse;
use Sheba\MerchantEnrollment\Statics\MEFGeneralStatics;
use Sheba\Payment\Factory\PaymentStrategy;
use Sheba\TPProxy\TPProxyServerError;

class AamarpaySavePrimaryInformation
{
    /** @var Partner $partner */
    private $partner;

    public function __construct()
    {
    }

    /**
     * @param  Partner  $partner
     * @return $this
     */
    public function setPartner(Partner $partner): AamarpaySavePrimaryInformation
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * @param $name
     * @return mixed|string
     */
    public function mutateName($name)
    {
        $name = rtrim(ltrim($name));
        if (count(explode(' ', $name)) < 2) {
            return 'Mr/Ms '.ucfirst($name);
        }
        return $name;
    }

    private function makeDataForMorService(): array
    {
        $application_data = json_decode($this->partner->partnerMefInformation->partner_information, true);
        $other_data = (new PersonalInformation())->setPartner($this->partner)->getPersonalPhoto();
        $application_data = array_merge($application_data, $other_data);

        return [
            'key'              => MEFGeneralStatics::USER_TYPE_PARTNER,
            'user_name'        => $this->mutateName($this->partner->getFirstAdminResource()->profile->name),
            'user_mobile'      => $this->partner->getFirstAdminResource()->profile->mobile,
            "application_data" => json_encode($application_data),
            "user_type"        => MEFGeneralStatics::USER_TYPE_PARTNER,
            "user_id"          => $this->partner->id,
            "pgw_store_key"    => PaymentStrategy::AAMARPAY,
            "survey_data"      => $this->getSurvey(),
            "request_type"     => $application_data["presentValueOfTotalMonthlyTransaction"] < 100000 ? "PRA" : "Regular"
        ];
    }

    /**
     * @return void
     */
    private function getSurvey()
    {
        if ($this->partner->survey->first()) {
            return $this->partner->survey->first()->result;
        }

        return null;
    }

    /**
     * @param $request
     * @return JsonResponse
     * @throws MORServiceServerError
     * @throws NotFoundAndDoNotReportException
     */
    public function storePrimaryInformationToAamarpay($request): JsonResponse
    {
        $completion_percentage = (new ApplyValidation())
            ->setPartner($this->partner)
            ->setForm(AamarpayConstants::AAMARPAY_FORM_ID)
            ->getFormSections();

        if ($completion_percentage != 100) {
            return http_response($request, null, 403, ['message' => 'Please fill Up all the fields, Your form is '.$completion_percentage." completed"]);
        }

        $data = $this->makeDataForMorService();
        $bannerAamarpay = (new PaymentService())->setPartner($this->partner)->getBannerForAamarpay();

        /** @var MORServiceClient $morClient */
        $morClient = app(MORServiceClient::class);
        $morClient->post("api/v1/application/users/".$this->partner->id, $data);

        return http_response($request, null, 200, ['message' => 'Successful', 'data' => $bannerAamarpay]);
    }
}
