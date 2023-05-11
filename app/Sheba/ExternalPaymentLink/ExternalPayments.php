<?php


namespace Sheba\ExternalPaymentLink;

use App\Models\PartnerPosCustomer;
use App\Models\PosCustomer;
use App\Models\Profile;
use App\Sheba\ResellerPayment\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Sheba\Dal\PaymentClientAuthentication\Model as PaymentClientAuthentication;
use Sheba\ExternalPaymentLink\Exceptions\InvalidEmiMonthException;
use Sheba\ExternalPaymentLink\Exceptions\InvalidTransactionIDException;
use Sheba\ExternalPaymentLink\Exceptions\PaymentLinkInitiateException;
use Sheba\ExternalPaymentLink\Exceptions\TransactionIDNotFoundException;
use Sheba\ExternalPaymentLink\Statics\ExternalPaymentStatics;
use Sheba\Helpers\Formatters\BDMobileFormatter;
use Sheba\ModificationFields;
use Sheba\Payment\AvailableMethods;
use Sheba\PaymentLink\PaymentLinkStatics;
use Sheba\Pos\Repositories\PartnerPosCustomerRepository;
use Sheba\Pos\Repositories\PosCustomerRepository;
use Sheba\Repositories\ProfileRepository;
use Sheba\RequestIdentification;
use Sheba\PaymentLink\Creator;
use Sheba\Dal\GatewayAccount\Model as GatewayAccount;

class ExternalPayments
{
    use ModificationFields;

    /** @var Creator $creator */
    private $creator;
    /** @var ProfileRepository $profileRepo */
    private $profileRepo;
    /** @var PosCustomerRepository $posCustomerRepo */
    private $posCustomerRepo;
    /** @var PartnerPosCustomerRepository $partnerPosCustomerRepo */
    private $partnerPosCustomerRepo;
    /** @var PaymentClientAuthentication $client */
    private $client;
    private $transactionID;
    private $data;
    private $agent;

    public function __construct()
    {
        /** @var Creator creator */
        $this->creator                = app(Creator::class);
        $this->profileRepo            = app(ProfileRepository::class);
        $this->posCustomerRepo        = app(PosCustomerRepository::class);
        $this->partnerPosCustomerRepo = app(PartnerPosCustomerRepository::class);
    }

    /**
     * @param Request $request
     * @return ExternalPayments
     * @throws InvalidTransactionIDException
     */
    public function setData(Request $request)
    {
        $this->data = $request->only(ExternalPaymentStatics::dataFields());
        $this->setAgent();
        $this->data['emi_month'] = (int)$request->get('emi_month');
        if (!isset($this->data['transaction_id'])) throw new InvalidTransactionIDException();
        $this->setTransactionID($this->data['transaction_id']);
        return $this;
    }

    private function setAgent()
    {
        $this->data = array_merge($this->data, (new RequestIdentification())->get());
        return $this;
    }

    /**
     * @param PaymentClientAuthentication $client
     * @return ExternalPayments
     */
    public function setClient(PaymentClientAuthentication $client)
    {
        $this->client = $client;
        $this->setModifier($this->client->partner);
        return $this;
    }

    /**
     * @param mixed $transactionID
     * @return ExternalPayments
     */
    private function setTransactionID($transactionID)
    {
        $this->transactionID = $transactionID;
        return $this;
    }

    /**
     * @return $this
     * @throws InvalidTransactionIDException
     */
    public function beforeCreateValidate()
    {
        $already = $this->client->payments()->byTransactionID($this->transactionID)->first();
        if (!empty($already)) throw new InvalidTransactionIDException();
        return $this;
    }

    /**
     * @param $transaction_id
     * @return mixed
     * @throws TransactionIDNotFoundException
     */
    public function getPaymentDetails($transaction_id)
    {
        $this->setTransactionID($transaction_id);
        $payment = $this->client->payments()->byTransactionID($this->transactionID)->first();
        if (empty($payment)) throw new TransactionIDNotFoundException();
        return $this->formatData($payment);
    }

    private function formatData($external_payment)
    {
        return [
            "amount"                      => $external_payment->amount,
            "success_url"                 => html_entity_decode($external_payment->success_url),
            "fail_url"                    => html_entity_decode($external_payment->fail_url),
            "customer_mobile"             => $external_payment->customer_mobile,
            "customer_name"               => $external_payment->customer_name,
            "emi_month"                   => $external_payment->emi_month,
            "purpose"                     => $external_payment->purpose,
            "client_id"                   => $this->client->client_id,
            "client_name"                 => $this->client->name,
            "transaction_id"              => $external_payment->transaction_id,
            "payment_status"              => $external_payment->payment ? $external_payment->payment->status : "pending",
            "payment_details"             => $this->payment_details($external_payment->payment)
        ];
    }

    /**
     * @param $payment_details
     * @return array|null
     */
    private function payment_details($payment_details)
    {
        return $payment_details ? [
            "method"              => $payment_details->paymentDetails()->first()->readableMethod,
            "transaction_details" => json_decode($payment_details->transaction_details),
            "payment_at"          => Carbon::parse($payment_details->created_at)->toDateTimeString(),
            "invoice_link"        => $payment_details->invoice_link
        ] : null;
    }

    /**
     * @return array|null
     * @throws InvalidEmiMonthException
     * @throws PaymentLinkInitiateException
     */
    public function create()
    {
        $available_methods = (new AvailableMethods())->getPublishedPartnerPaymentGateways($this->client->partner);
        if (!count($available_methods)) throw new PaymentLinkInitiateException("No configured/published pgw store account found", 404);
        $response = null;
        DB::transaction(function () use (&$response) {
            $this->processData();
            $payment  = $this->createPaymentRequest();
            $response = $this->createPaymentLink($payment);
        });
        return $response;

    }

    private function processData()
    {
        $this->data['customer_mobile'] = $this->data['customer_mobile'] ? BDMobileFormatter::format($this->data['customer_mobile']) : '';
        $this->data['customer_name']   = $this->data['customer_name'] ?: '';
        $this->data['purpose']         = ExternalPaymentStatics::getPurpose($this->data, $this->client);
    }

    private function createPaymentRequest()
    {

        $data               = $this->data;
        $data['partner_id'] = $this->client->partner->id;
        return $this->client->payments()->create($this->withCreateModificationField($data));
    }

    /**
     * @param $payment
     * @return array
     * @throws InvalidEmiMonthException|PaymentLinkInitiateException
     */
    private function createPaymentLink($payment)
    {
        $emi_month_invalid = Creator::validateEmiMonth($this->data);
        if ($emi_month_invalid !== false) throw new InvalidEmiMonthException($emi_month_invalid);
        $this->creator->setIsDefault(0)
                      ->setAmount($this->data['amount'])
                      ->setReason($this->data['purpose'])
                      ->setUserName($this->client->partner->name)
                      ->setUserId($this->client->partner->id)
                      ->setUserType('partner')->setPaidBy(
                array_key_exists('interest_paid_by',$this->data) ?$this->data['interest_paid_by']: PaymentLinkStatics::paidByTypes()[$this->data["emi_month"] ? 1 : 0]
            )
                      ->setTargetId($payment->id)
                      ->setTargetType('external_payment')
                      ->setEmiMonth((int)$this->data['emi_month'])
                      ->calculate();
        $this->setCustomer();
        $external_link = $this->creator->save();
        if (!$external_link) throw new PaymentLinkInitiateException();
        return $this->creator->getPaymentLinkData();


    }

    private function setCustomer()
    {
        if (!empty($this->data['customer_mobile'])) {
            $posCustomer = PosCustomer::query()->getByMobile($this->data['customer_mobile'])->first();
            if (!$posCustomer) {
                $posCustomer = $this->createPosCustomer();
            }
            $pPosCustomer = PartnerPosCustomer::where('partner_id', $this->client->partner->id)->where('customer_id', $posCustomer->id)->first();
            if (!$pPosCustomer) {
                $this->partnerPosCustomerRepo->save(['partner_id' => $this->client->partner->id, 'customer_id' => $posCustomer->id, 'nick_name' => $this->data['customer_name']] + (new RequestIdentification())->get());
            }
            $this->creator->setPayerId($posCustomer->id)->setPayerType('pos_customer');

        }
    }

    private function createPosCustomer()
    {
        $profile = Profile::where('mobile', $this->data['customer_mobile'])->first();
        if (!$profile) {
            $profile = $this->profileRepo->store($this->withCreateModificationField(['mobile' => $this->data['customer_mobile'], 'name' => isset($this->data['customer_name']) ? $this->data['customer_name'] : '']));
        }
        return $this->posCustomerRepo->save(['profile_id' => $profile->id]);

    }

    /**
     * @param null $partner
     * @return bool
     */
    public function getGatewayStatus($partner = null): bool
    {
        if(!$partner && !$this->client) return false;
        $partner = $partner ? : $this->client->partner;
        return (bool)$partner->pgwGatewayAccounts()->published()->first();
    }

    /**
     * @param $partner
     * @param int $for_emi
     * @return bool
     */
    public function getPaymentLinkStatus($partner, $for_emi = 0): bool
    {
        if(!isset($partner)) return false;
        $partner_store_accounts = $partner->pgwGatewayAccounts()->published()->get();
        if (isset($partner_store_accounts) && count($partner_store_accounts)) {
            foreach ($partner_store_accounts as $account) {
                $pgw_store = $account->gateway;
                if ($for_emi === 1 && $pgw_store->is_emi_enabled) return true;
            }
            return !$for_emi;
        }
        return false;
    }

    /**
     * @param $partner
     * @return array
     */
    public function getPaymentGatewayStatus($partner): array
    {
        $payment_service=(new PaymentService())->setPartner($partner);
        $pgw_status = $payment_service->getPgwStatusForHomePage()->getPgwStatusForStatusCheck();
        $card_payment_status=$payment_service->isCardPaymentAvailable();
        $regular_payment_link = $this->getPaymentLinkStatus($partner);
        $emi_payment_link = $this->getPaymentLinkStatus($partner, 1);

        return [
            "regular_payment_link" => $regular_payment_link,
            "pgw_status" => $pgw_status,
            "emi_payment_link" => $emi_payment_link,
            "card_payment_status"=>$card_payment_status,
            "message" => "Successful",
            "code" => 200
        ];
    }
}
