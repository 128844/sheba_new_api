<?php namespace App\Sheba\MtbOnboarding;

use App\Models\Partner;
use App\Sheba\MTB\AuthTypes;
use App\Sheba\MTB\MtbServerClient;
use App\Sheba\QRPayment\QRPaymentStatics;

class MtbAccountStatus
{
    /** @var Partner $partner */
    private $partner;
    /** @var MtbServerClient $client */
    private $client;

    /**
     * @param  MtbServerClient  $client
     */
    public function __construct(MtbServerClient $client)
    {
        $this->client = $client;
    }

    /**
     * @param  Partner  $partner
     * @return $this
     */
    public function setPartner(Partner $partner): MtbAccountStatus
    {
        $this->partner = $partner;
        return $this;
    }

    public function checkAccountStatus()
    {
        $response = $this->client->get(QRPaymentStatics::MTB_ACCOUNT_STATUS . $this->partner->partnerMefInformation->mtb_ticket_id, AuthTypes::BARER_TOKEN);
        $this->partner->partnerMefInformation->mtb_account_status = json_encode($response);
        return $this->partner->partnerMefInformation->save();
    }
}
