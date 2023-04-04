<?php

namespace App\Sheba\AamarpayOnboarding;

use App\Exceptions\NotFoundAndDoNotReportException;
use App\Models\Partner;
use App\Sheba\Aamarpay\AamarpayConstants;
use App\Sheba\MerchantEnrollment\PersonalInformation;
use App\Sheba\Aamarpay\Validation\ApplyValidation;
use App\Sheba\ResellerPayment\Exceptions\MORServiceServerError;
use App\Sheba\ResellerPayment\MORServiceClient;
use App\Sheba\ResellerPayment\PaymentService;
use Illuminate\Http\JsonResponse;
use Sheba\MerchantEnrollment\Statics\MEFGeneralStatics;
use Sheba\Payment\Factory\PaymentStrategy;
use Sheba\Dal\ProfileNIDSubmissionLog\Model as ProfileNIDSubmissionLog;

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
        $aamarpay_specific_data = $this->aamarpaySpecificData();
        $application_data = array_merge($application_data, $aamarpay_specific_data);

        return [
            'user_name'        => $this->mutateName($this->partner->getFirstAdminResource()->profile->name),
            'user_mobile'      => $this->partner->mobile,
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
        $morClient->post("api/v1/clients/applications/store", $data);

        return http_response($request, null, 200, ['message' => 'Successful', 'data' => $bannerAamarpay]);
    }

    private function aamarpaySpecificData(): array
    {
        $first_admin_profile = $this->partner->getFirstAdminResource()->profile;
        list($nid_image_front_url, $nid_image_back_url) = $this->getNidImages($first_admin_profile);
        return [
            'tradingName'         => "smanager.xyz/s/".$this->partner->sub_domain,
            'mobile'              => $this->partner->mobile,
            'email'               => $first_admin_profile->email,
            'monthlyIncome'       => json_decode($this->partner->basicInformations->additional_information)->monthly_transaction_amount,
            'nidOrPassport'       => $this->getNidOrPassport($first_admin_profile),
            'dob'                 => date("Y-m-d", strtotime($first_admin_profile->dob)),
            'salesItems'          => $this->partner->business_type,
            'nid_image_front_url' => $nid_image_front_url,
            'nid_image_back_url'  => $nid_image_back_url
        ];
    }

    /**
     * @param $first_admin_profile
     * @return mixed
     */
    private function getNidOrPassport($first_admin_profile)
    {
        $porichoy_data = null;
        $nid_information = $this->getNidInformationFromProfileNIDSubmissionLog($first_admin_profile);

        if (isset($nid_information->porichy_data)) {
            $porichoy_data = json_decode($nid_information->porichy_data);
        }

        return $porichoy_data ? $porichoy_data->porichoy_data->nid_no : $first_admin_profile->nid_no;
    }

    /**
     * @param $first_admin_profile
     * @return array
     */
    private function getNidImages($first_admin_profile): array
    {
        $nid_information = $this->getNidInformationFromProfileNIDSubmissionLog($first_admin_profile);

        if ($nid_information && isset($nid_information->nid_ocr_data)) {
            $nid_ocr_data = json_decode($nid_information->nid_ocr_data);

            return [$nid_ocr_data->id_front_image, $nid_ocr_data->id_back_image];
        }

        return [$first_admin_profile->nid_image_front, $first_admin_profile->nid_image_back];
    }

    /**
     * @param $first_admin_profile
     * @return mixed
     */
    public function getNidInformationFromProfileNIDSubmissionLog($first_admin_profile)
    {
        return ProfileNIDSubmissionLog::where('profile_id', $first_admin_profile->id)
            ->where('verification_status', 'approved')
            ->whereNotNull('porichy_data')
            ->last();
    }
}
