<?php namespace App\Sheba\ResellerPayment;

use App\Exceptions\NotFoundAndDoNotReportException;
use App\Models\Partner;
use App\Sheba\MTB\AuthTypes;
use App\Sheba\MTB\Exceptions\MtbServiceServerError;
use App\Sheba\MTB\MtbConstants;
use App\Sheba\MTB\MtbMappedAccountStatus;
use App\Sheba\MTB\MtbServerClient;
use App\Sheba\MTB\Validation\ApplyValidation;
use App\Sheba\QRPayment\QRPaymentStatics;
use App\Sheba\ResellerPayment\Exceptions\UnauthorizedRequestFromMORException;
use Illuminate\Support\Facades\App;
use Sheba\Dal\DigitalCollectionSetting\Model as DigitalCollectionSetting;
use Sheba\Dal\PgwStore\Model as PgwStore;
use Sheba\Dal\GatewayAccount\Model as GatewayAccount;
use Sheba\Dal\QRGateway\Model as QRGateway;
use Sheba\Dal\Survey\Model as Survey;
use Sheba\EMI\Banks;
use Sheba\EMI\CalculatorForManager;
use Sheba\MerchantEnrollment\Exceptions\InvalidQRKeyException;
use Sheba\MerchantEnrollment\MerchantEnrollment;
use Sheba\MerchantEnrollment\Statics\MEFGeneralStatics;
use Sheba\MerchantEnrollment\Statics\PaymentMethodStatics;
use Sheba\ModificationFields;
use Sheba\PaymentLink\PaymentLinkStatics;
use Sheba\PaymentLink\PaymentLinkStatus;
use Sheba\PushNotificationHandler;
use Sheba\ResellerPayment\Exceptions\InvalidKeyException;
use Sheba\Payment\Methods\Ssl\Stores\DynamicSslStoreConfiguration;
use Sheba\ResellerPayment\Exceptions\ResellerPaymentException;
use Sheba\ResellerPayment\Statics\ResellerPaymentGeneralStatic;
use Sheba\Sms\BusinessType;
use Sheba\Sms\FeatureType;
use Sheba\Sms\Sms;
use Sheba\Dal\PartnerFinancialInformation\Model as PartnerFinancialInformation;

class PaymentService
{
    use ModificationFields;

    private $partner;
    private $status;
    private $pgwStatus;
    private $key;
    private $rejectReason;
    private $pgwMerchantId;
    private $newStatus;
    private $type;
    private $mtbStatus;


    /**
     * @param mixed $partner
     * @return PaymentService
     */
    public function setPartner($partner): PaymentService
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * @param mixed $status
     * @return PaymentService
     */
    public function setNewStatus($status): PaymentService
    {
        $this->newStatus = $status;
        return $this;
    }

    /**
     * @return array
     */

    /**
     * @param mixed $key
     */
    public function setKey($key): PaymentService
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @return array
     * @throws Exceptions\MORServiceServerError
     * @throws InvalidQRKeyException
     * @throws NotFoundAndDoNotReportException|MtbServiceServerError
     */
    private function getQRGatewayDetails(): array
    {
        if ($this->key == 'mtb') {
            $mtb_information = $this->partner->partnerMefInformation::where('partner_id', $this->partner->id)->first();
            if ($mtb_information->mtb_ticket_id) {
                $mtb_status = $this->getMtbAccountStatus($mtb_information);
                if ($mtb_status) $this->status = $mtb_status['status'];
            }
        }
        $qr_gateway = QRGateway::where('method_name', $this->key)->first();
        if (!$qr_gateway) throw new InvalidQRKeyException();
        $this->getResellerPaymentStatus(true);
        $account_details = json_decode($this->partner->partnerMefInformation->mtb_account_status);
        if (isset(json_decode($this->partner->partnerMefInformation->mtb_account_status)->Status)) {
            if (json_decode($this->partner->partnerMefInformation->mtb_account_status)->Status == 19) {
                return [
                    'banner' => PaymentMethodStatics::getMtbBannerURL(),
                    'faq' => PaymentMethodStatics::detailsFAQ(),
                    'status' => 'completed' ?? null,
                    'disclaimer_message' => isset($mtb_status) ? $mtb_status['description'] . $account_details->Description : 'আবেদন সফল হয়েছে !',
                    'how_to_use_link' => PaymentLinkStatics::how_to_use_webview(),
                    'payment_service_info_link' => PaymentLinkStatics::payment_setup_faq_webview(),
                    'details' => [
                        'id' => $qr_gateway->id,
                        'key' => $qr_gateway->key,
                        'name_bn' => $qr_gateway->name_bn,
                        'icon' => $qr_gateway->icon,
                        'mid' => $account_details->Mid,
                        'account_number' => $account_details->AccountNum,
                        'customer_number' => $account_details->CustomerNum,
                        'name' => $this->partner->getFirstAdminResource()->profile->name,
                        'description' => $account_details->Description
                    ]
                ];
            }
        }
        return [
            'banner' => PaymentMethodStatics::getMtbBannerURL(),
            'faq' => PaymentMethodStatics::detailsFAQ(),
            'status' => $this->status ?? null,
            'disclaimer_message' => isset($mtb_status) ? $mtb_status['description']. $account_details->Description : '',
            'how_to_use_link' => PaymentLinkStatics::how_to_use_webview(),
            'payment_service_info_link' => PaymentLinkStatics::payment_setup_faq_webview(),
            'details' => [
                'id' => $qr_gateway->id,
                'key' => $qr_gateway->key,
                'name_bn' => $qr_gateway->name_bn,
                'icon' => $qr_gateway->icon,
                'mid' => $account_details->Mid ?? null,
                'account_number' => $account_details->AccountNum ?? null,
                'customer_number' => $account_details->CustomerNum ?? null,
                'name' => $this->partner->getFirstAdminResource()->profile->name
            ]
        ];

    }


    /**
     * @throws NotFoundAndDoNotReportException
     * @throws MtbServiceServerError
     */
    public function getMtbAccountStatus($mtb_information)
    {
        if (isset($mtb_information->mtb_account_status)) {
            $mtb_status = $mtb_information;
            if (json_decode($mtb_status->mtb_account_status)->Status != '19') {
                /** @var MtbServerClient $client */
                $client = App::make(MtbServerClient::class);
                $response = $client->get(QRPaymentStatics::MTB_ACCOUNT_STATUS . $mtb_status->mtb_ticket_id, AuthTypes::BARER_TOKEN);
                $this->mtbStatus = $response["Status"];
                if (json_decode($mtb_status->mtb_account_status)->Status != $response["Status"]) {
                    $this->storeMtbAccountStatus($response);
                }
            }
            $mapped_status = (new MtbMappedAccountStatus())->setStatus($this->mtbStatus)->mapMtbAccountStatus();
            if (isset($this->partner->partnerMefinformation->mtb_account_status)) {
                if (json_decode($mtb_status->mtb_account_status)->Status == '19') {
                    $this->savePartnerFinancialInformation(json_decode($mtb_status->mtb_account_status)->Mid);
                    $this->storeGatewayAccounts();
                }
            }
            try {
                /** @var MORServiceClient $morClient */
                $morClient = app(MORServiceClient::class);
                $morClient->put("api/v1/application/users/" . $this->partner->id . "/key=mtb&new_status=" . $mapped_status['status'], null);
            } catch (\Exception $e) {
                return $mapped_status;
            }
            return $mapped_status;
        }
        return 1;
    }

    private function savePartnerFinancialInformation($mid)
    {
        $partnerFinancialInformation = PartnerFinancialInformation::where('partner_id', $this->partner->id)->first();
        if (!$partnerFinancialInformation) {
            $partnerFinancialInformation = new PartnerFinancialInformation();
            $partnerFinancialInformation->partner_id = $this->partner->id;
            $partnerFinancialInformation->mtb_merchant_id = $mid;
            $partnerFinancialInformation->save();
        }
    }

    private function storeGatewayAccounts()
    {
        $partnerGatewayAccounts = GatewayAccount::where('user_id', $this->partner->id)->where('gateway_type', 'qr')->where('gateway_type_id', 1)->where('name', 'mtb')->where('status', 1)->first();
        if (!$partnerGatewayAccounts) {
            $partnerGatewayAccounts = new GatewayAccount();
            $partnerGatewayAccounts->user_id = $this->partner->id;
            $partnerGatewayAccounts->user_type = 'partner';
            $partnerGatewayAccounts->gateway_type = 'qr';
            $partnerGatewayAccounts->gateway_type_id = 1;
            $partnerGatewayAccounts->name = 'mtb';
            $partnerGatewayAccounts->status = 1;
            $partnerGatewayAccounts->save();
        }
    }

    private function storeMtbAccountStatus($response)
    {
        $this->partner->partnerMefInformation->mtb_account_status = json_encode($response);
        $this->partner->partnerMefInformation->save();
    }

    /**
     * @return array
     * @throws Exceptions\MORServiceServerError
     * @throws ResellerPaymentException
     * @throws NotFoundAndDoNotReportException
     */
    public function getDetails(): array
    {
        if ($this->type === PaymentLinkStatics::TYPE_QR) {
            return $this->getQRGatewayDetails();
        } else {
            return $this->getPGWDetails();
        }
    }

    /**
     * @return array
     * @throws Exceptions\MORServiceServerError
     * @throws NotFoundAndDoNotReportException
     * @throws ResellerPaymentException
     */
    public function getPGWDetails(): array
    {
        $this->getResellerPaymentStatus();
        $this->getPgwStatus();
        $pgw_store = PgwStore::where('key', $this->key)->first();
        if (!$pgw_store) throw new InvalidQRKeyException();
        $status_wise_message = in_array($this->status, ['pending', 'processing', 'verified']) ? config('reseller_payment.mor_status_wise_text')[$this->key][$this->status] : null;
        if ($this->status === "rejected") {
            $status_wise_message = config('reseller_payment.mor_status_wise_text')[$this->key]["rejected_start"] .
                $this->rejectReason . config('reseller_payment.mor_status_wise_text')[$this->key]["rejected_end"];
        }
        return [
            'banner' => PaymentMethodStatics::getSslBannerURL(),
            'faq' => PaymentMethodStatics::detailsFAQ(),
            'status' => $this->status ?? null,
            'mor_status_wise_disclaimer' => $status_wise_message,
            'pgw_status' => $this->pgwStatus ?? null,
            'pgw_merchant_id' => $this->pgwMerchantId,
            'how_to_use_link' => PaymentLinkStatics::how_to_use_webview(),
            'payment_service_info_link' => PaymentLinkStatics::payment_setup_faq_webview(),
            'details' => [
                'id' => $pgw_store->id,
                'key' => $pgw_store->key,
                'name_bn' => $pgw_store->name_bn,
                'icon' => $pgw_store->icon
            ]
        ];

    }

    /**
     * @return array
     * @throws Exceptions\MORServiceServerError
     * @throws NotFoundAndDoNotReportException
     */
    public function getStatusAndBanner(): array
    {
        $this->getResellerPaymentStatus();
        $this->getPgwStatusForHomePage();

        return [
            'status' => $this->status ?? null,
            'pgw_status' => $this->pgwStatus ?? null,
            'banner' => $this->getBanner(),
            'info_link' => PaymentLinkStatics::payment_setup_faq_webview()
        ];
    }


    /**
     * @return array
     */
    public function getBannerForMtb(): array
    {
        $description = (new MtbMappedAccountStatus())->setStatus(json_decode($this->partner->partnerMefinformation->mtb_account_status)->Status)->mapMtbAccountStatus();
        return [
            'banner' => $this->getBanner(),
            'info_link' => PaymentLinkStatics::payment_setup_faq_webview(),
            "title" => "আবেদন সফল হয়েছে!",
            "body" => $description['description']
        ];
    }

    private function getBanner()
    {
        $banner = null;
        if ($this->pgwStatus === 0)
            $banner = config('reseller_payment.status_wise_home_banner')['pgw_inactive'];
        elseif ($this->status == 'verified')
            $banner = config('reseller_payment.status_wise_home_banner')['verified'];
        elseif ($this->status == 'rejected')
            $banner = config('reseller_payment.status_wise_home_banner')['rejected'];
        elseif ($this->status == 'ekyc_completed')
            $banner = config('reseller_payment.status_wise_home_banner')['ekyc_completed'];
        elseif ($this->status == 'survey_completed')
            $banner = config('reseller_payment.status_wise_home_banner')['ekyc_completed'];
        elseif ($this->status == 'mef_completed')
            $banner = config('reseller_payment.status_wise_home_banner')['completed_but_did_not_apply'];
        elseif (is_null($this->status))
            $banner = config('reseller_payment.status_wise_home_banner')['did_not_started_journey'];

        return $banner;

    }

    /**
     * @return void
     * @throws Exceptions\MORServiceServerError
     * @throws NotFoundAndDoNotReportException
     */
    private function getResellerPaymentStatus($exceptMorStatus = false)
    {
        if (!$exceptMorStatus)
            $this->getMORStatus();
        if (isset($this->status))
            return;
        $this->checkMefCompletion();
        if (isset($this->status))
            return;
        $this->getSurveyStatus();
        if (isset($this->status))
            return;
        $this->getEkycStatus();

    }

    /**
     * @param $key
     * @return mixed|null
     * @throws Exceptions\MORServiceServerError
     * @throws NotFoundAndDoNotReportException
     */
    private function getMORStatus($key = null)
    {
        /** @var MORServiceClient $morClient */
        $morClient = app(MORServiceClient::class);
        $url = $key ? 'api/v1/client/applications/status?user_id=' . $this->partner->id . '&user_type=' . MEFGeneralStatics::USER_TYPE_PARTNER . '&key=' . $key :
            'api/v1/client/applications/status?user_id=' . $this->partner->id . '&user_type=' . MEFGeneralStatics::USER_TYPE_PARTNER;

        $morResponse = $morClient->get($url);
        if (isset($morResponse['data'])) {
            $this->status = $morStatus = $morResponse['data']['application_status'];
            if ($morStatus == 'rejected')
                $this->rejectReason = $morResponse['data']['reject_reason'];
            return $morStatus;
        }
        return null;

    }

    private function checkMefCompletion(): bool
    {
        $this->key = MEFGeneralStatics::payment_gateway_keys();
        foreach ($this->key as $key) {
            $merchantEnrollment = app(MerchantEnrollment::class);
            $completion = $merchantEnrollment->setPartner($this->partner)->setKey($key)->getCompletion()->toArray();
            if ($completion['can_apply'] == 1) {
                $survey = Survey::where('user_type', get_class($this->partner))->where('user_id', $this->partner->id)->where('key', 'reseller_payment')->first();
                if ($survey)
                    $this->status = 'mef_completed';
                return true;
            }
        }
        return true;
    }

    private function getSurveyStatus()
    {
        $survey = Survey::where('user_type', get_class($this->partner))->where('user_id', $this->partner->id)->where('key', 'reseller_payment')->first();
        if ($survey)
            $this->status = 'survey_completed';
    }

    private function getEkycStatus()
    {
        if ($this->partner->isNIDVerified())
            $this->status = 'ekyc_completed';
    }

    public function getPgwStatusForHomePage(): PaymentService
    {
        $pgw_store_accounts = GatewayAccount::where('user_type', get_class($this->partner))->where('user_id', $this->partner->id)->get();

        if (!$pgw_store_accounts->isEmpty()) {
            foreach ($pgw_store_accounts as $pgw_store_account) {
                if ($pgw_store_account->status == 1) {
                    $this->pgwStatus = $pgw_store_account->status;
                    $this->pgwMerchantId = (new DynamicSslStoreConfiguration($pgw_store_account->configuration))->getStoreId();
                    return $this;
                }
            }
            $this->pgwStatus = $pgw_store_accounts->first()->status;
            $this->pgwMerchantId = (new DynamicSslStoreConfiguration($pgw_store_accounts->first()->configuration))->getStoreId();
        }

        return $this;
    }

    private function getPgwStatus()
    {
        $pgw_store_account = $this->partner->pgwGatewayAccounts()->join('pgw_stores', 'gateway_type_id', '=', 'pgw_stores.id')
            ->where('pgw_stores.key', $this->key)->first();
        if ($pgw_store_account) {
            $this->pgwStatus = $pgw_store_account->status;
            $this->pgwMerchantId = (new DynamicSslStoreConfiguration($pgw_store_account->configuration))->getStoreId();
        }

    }

    /**
     * @param $completion
     * @param $header_message
     * @param $partnerId
     * @param $banner
     * @return array
     * @throws Exceptions\MORServiceServerError
     * @throws InvalidKeyException
     * @throws NotFoundAndDoNotReportException
     */
    public function getPaymentGateways($completion, $header_message, $partnerId, $banner, $version_code): array
    {
        $pgwData = [];
        $status = '';
        $partner = Partner::where('id', $partnerId)->first();
        $pgwStores = new PgwStore();

        $pgwStores = $pgwStores->publishedForMEF()->select('id', 'name', 'key', 'name_bn', 'icon')->get();
        foreach ($pgwStores as $pgwStore) {
            $completionData = (new MerchantEnrollment())->setPartner($partner)->setKey($pgwStore->key)->getCompletion();
            $mor_status = $this->getMORStatus($pgwStore->key);
            $partner_account = $partner->pgwGatewayAccounts()->where('gateway_type_id', $pgwStore->id)->select('status')->first();
            if (!$mor_status && !$partner_account) {
                $status = PaymentLinkStatus::UNREGISTERED;
            } else if ($mor_status == "pending" && !$partner_account) {
                $status = PaymentLinkStatus::PENDING;
            } else if ($mor_status == "processing" && !$partner_account) {
                $status = PaymentLinkStatus::PROCESSING;
            } else if ($mor_status == "verified" && !$partner_account) {
                $status = PaymentLinkStatus::VERIFIED;
            } else if ($mor_status == "rejected" && !$partner_account) {
                $status = PaymentLinkStatus::REJECTED;
            } else if ($partner_account->status == 1) {
                $status = PaymentLinkStatus::ACTIVE;
            } else if ($partner_account->status == 0) {
                $status = PaymentLinkStatus::INACTIVE;
            }
            $pgwData[] = $this->makePGWGatewayData($pgwStore, $completion, $header_message, $completionData, $status);
        }
        //QR gateway off until app live
        if (intval($version_code) < 300600) {
            $allData = $pgwData;
        } else {
            $qrData = $this->getQRGateways($completion);
            $allData = array_merge($pgwData, $qrData);
        }
        return $banner ?
            array_merge(["payment_gateway_list" => $allData], ["list_banner" => MEFGeneralStatics::LIST_PAGE_BANNER]) : $allData;
    }

    private function makeQRGatewayData($qrGateway, $completion): array
    {
        if ($completion == 1)
            $completion = (new ApplyValidation())->setPartner($this->partner)->setForm($qrGateway->id)->getFormSections();
        else
            $completion = null;
        if (isset($this->partner->partnerMefinformation->mtb_account_status))
            $status = (new MtbMappedAccountStatus())->setStatus(json_decode($this->partner->partnerMefinformation->mtb_account_status)->Status)->mapMtbAccountStatus();

        return [
            'id' => $qrGateway->id,
            'name' => $qrGateway->name,
            'key' => $qrGateway->method_name,
            'name_bn' => $qrGateway->name_bn,
            'header' => null,
            'type' => "qr",
            'completion' => $completion,
            'icon' => $qrGateway->icon,
            'status' => $status['status'] ?? null
        ];
    }

    private function getQRGateways($completion): array
    {
        $qrData = array();
        $qrGateways = QRGateway::query()->select('id', 'name', 'method_name', 'name_bn', 'icon')->get();
        foreach ($qrGateways as $qrGateway) {
            $qrData[] = $this->makeQRGatewayData($qrGateway, $completion);
        }
        $this->getMtbAccountStatus($this->partner->partnerMefInformation);

        return $qrData;
    }

    private function makePGWGatewayData($pgwStore, $completion, $header_message, $completionData, $status): array
    {
        return [
            'id' => $pgwStore->id,
            'name' => $pgwStore->name,
            'key' => $pgwStore->key,
            'name_bn' => $pgwStore->name_bn,
            'header' => $pgwStore->key === 'ssl' ? $header_message : null,
            'type' => 'pgw',
            'completion' => $completion == 1 ? $completionData->getOverallCompletion()['en'] : null,
            'icon' => $pgwStore->icon,
            'status' => $status
        ];
    }

    /**
     * @param $partnerId
     * @return array
     */
    public function getServiceCharge($partnerId): array
    {
        $digitalCollection = DigitalCollectionSetting::where('partner_id', $partnerId)->select('service_charge')->first();

        $data = PaymentLinkStatics::customPaymentServiceData();
        if ($digitalCollection) {
            $data['current_percentage'] = $digitalCollection->service_charge;
        } else {
            $data['current_percentage'] = PaymentLinkStatics::SERVICE_CHARGE;
        }
        return $data;
    }

    /**
     * @param $partnerId
     * @param $currentPercentage
     */
    public function storeServiceCharge($partnerId, $currentPercentage)
    {
        $digitalCollectionSetting = new DigitalCollectionSetting();
        $partner = $digitalCollectionSetting->where('partner_id', $partnerId)->first();

        if (!$partner) {
            $digitalCollectionSetting->partner_id = $partnerId;
            $digitalCollectionSetting->service_charge = $currentPercentage;
            $this->withCreateModificationField($digitalCollectionSetting);
            $digitalCollectionSetting->save();
        } else {
            $data = ['service_charge' => $currentPercentage];
            $digitalCollectionSetting->query()->where('partner_id', $partnerId)
                ->update($this->withUpdateModificationField($data));
        }
    }

    /**
     * @param $partner
     * @param $amount
     * @return array
     */
    public function getEmiInfoForManager($partner, $amount): array
    {
        $emi_calculator = new CalculatorForManager();
        $icons_folder = getEmiBankIconsFolder(true);
        return [
            "emi" => $emi_calculator->setPartner($partner)->getCharges($amount),
            "banks" => (new Banks())->setAmount($amount)->get(),
            "minimum_amount" => number_format(config('sheba.min_order_amount_for_emi')),
            "static_info" => [
                "how_emi_works" => [
                    "EMI (Equated Monthly Installment) is one of the payment methods of online purchasing, only for the customers using any of the accepted Credit Cards on Sheba.xyz.* It allows customers to pay for their ordered services  in easy equal monthly installments.*",
                    "Sheba.xyz has introduced a convenient option of choosing up to 12 months EMI facility for customers who use Credit Cards for buying services worth BDT 5,000 or more. The duration and extent of the EMI options available will be visible on the payment page after order placement. EMI plans are also viewable on the checkout page in the EMI Banner below the bill section.",
                    "Customers wanting to avail EMI facility must have a Credit Card from any one of the banks in the list shown in the payment page.",
                    "EMI facilities available for all services worth BDT 5,000 or more.",
                    "EMI charges may vary on promotional offers.",
                    "Sheba.xyz  may charge additional convenience fee if the customer extends the period of EMI offered."
                ],
                "terms_and_conditions" => [
                    "As soon as you complete your purchase order on Sheba.xyz, you will see the full amount charged on your credit card.",
                    "You must Sign and Complete the EMI form and submit it at Sheba.xyz within 3 working days.",
                    "Once Sheba.xyz receives this signed document from the customer, then it shall be submitted to the concerned bank to commence the EMI process.",
                    "The EMI processing will be handled by the bank itself *. After 5-7 working days, your bank will convert this into EMI.",
                    "From your next billing cycle, you will be charged the EMI amount and your credit limit will be reduced by the outstanding amount.",
                    "If you do not receive an updated monthly bank statement reflecting your EMI transactions for the following month, feel free to contact us at 16516  for further assistance.",
                    "For example, if you have made a 3-month EMI purchase of BDT 30,000 and your credit limit is BDT 1, 00,000 then your bank will block your credit limit by BDT 30,000 and thus your available credit limit after the purchase will only be BDT 70,000. As and when you pay your EMI every month, your credit limit will be released accordingly.",
                    "EMI facilities with the aforesaid Banks are regulated as per their terms and conditions and these terms may vary from one bank to another.",
                    "For any query or concern please contact your issuing bank, if your purchase has not been converted to EMI by 7 working days of your transaction date."
                ]
            ]
        ];
    }

    public function sendNotificationOnStatusChange()
    {
        if ($this->newStatus !== 'processing')
            $this->sendSMS();
        $this->sendPushNotification();
    }

    public function sendSMS($message_body = null)
    {
        $body = $message_body ?: config('reseller_payment.mor_status_change_message')[$this->key][$this->newStatus];
        $mobile = $this->partner->getContactNumber();
        (new Sms())
            ->setFeatureType(FeatureType::PAYMENT_LINK)
            ->setBusinessType(BusinessType::SMANAGER)
            ->shoot($mobile, $body);
    }

    private function sendPushNotification()
    {
        $topic = config('sheba.push_notification_topic_name.manager') . $this->partner->id;
        $channel = config('sheba.push_notification_channel_name.manager');
        $sound = config('sheba.push_notification_sound.manager');

        $notification_data = [
            "title" => 'Reseller Payment Status Change',
            "message" => config('reseller_payment.mor_status_change_message')[$this->key][$this->newStatus],
            "sound" => "notification_sound",
            "channel_id" => $channel,
            "event_type" => 'reseller_payment_status_change',
            "event_id" => $this->partner->id
        ];

        (new PushNotificationHandler())->send($notification_data, $topic, $channel, $sound);
    }

    /**
     * @param $secret
     * @return void
     * @throws UnauthorizedRequestFromMORException
     */
    public function authenticateMorRequest($secret)
    {
        if ($secret !== config('reseller_payment.mor_access_token'))
            throw new UnauthorizedRequestFromMORException();
    }

    /**
     * @return mixed
     */
    public function getPgwStatusForStatusCheck()
    {
        return $this->pgwStatus;
    }

    /**
     * @param mixed $type
     * @return PaymentService
     */
    public function setType($type): PaymentService
    {
        $this->type = $type;
        return $this;
    }


}
