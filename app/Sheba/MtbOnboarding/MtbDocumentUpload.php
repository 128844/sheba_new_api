<?php namespace App\Sheba\MtbOnboarding;

use App\Models\Partner;
use App\Sheba\DynamicForm\PartnerMefInformation;
use App\Sheba\MTB\AuthTypes;
use App\Sheba\MTB\MtbConstants;
use App\Sheba\MTB\MtbServerClient;
use App\Sheba\QRPayment\QRPaymentStatics;
use Cassandra\Date;
use Sheba\Reports\PdfHandler;

class MtbDocumentUpload
{
    /**
     * @var MtbServerClient
     */
    private $client;
    /**
     * @var Partner
     */
    private $partner;
    /**
     * @var PartnerMefInformation
     */
    private $partnerMefInformation;

    public function __construct(MtbServerClient $client)
    {
        $this->client = $client;
    }

    public function setPartner(Partner $partner): MtbDocumentUpload
    {
        $this->partner = $partner;
        return $this;
    }

    public function setPartnerMefInformation($partnerMefInformation): MtbDocumentUpload
    {
        $this->partnerMefInformation = $partnerMefInformation;
        return $this;
    }

    private function makeData(): array
    {
        $partner = [
            "name" => $this->partner->getFirstAdminResource()->profile->name,
            "nid" => $this->partner->getFirstAdminResource()->profile->nid_no,
            "business_name" => $this->partner->name,
            "partner_id" => $this->partner->id,
            "date" => date("d/m/Y"),
            "address" => json_decode($this->partner->partnerMefInformation->partner_information)->permanentAddress
        ];
        $pdf_handler = new PdfHandler();
        $loan_application_name = 'mtb_declaration_letter_' . $this->partner->id;
        $linkToBase64 = base64_encode(file_get_contents($pdf_handler->setData($partner)->setName($loan_application_name)->setViewFile('mtb_declaration')->save(true)));
        $data = array(
            [
                'ticketId' => $this->partner->partnerMefInformation->mtb_ticket_id,
                'docRefId' => $this->partner->getFirstAdminResource()->profile->nid_no,
                'docImage' => base64_encode(file_get_contents($this->partner->getFirstAdminResource()->profile->nid_image_front)),
                'docType' => MtbConstants::NID_FRONT,
            ],
            [
                'ticketId' => $this->partner->partnerMefInformation->mtb_ticket_id,
                'docRefId' => $this->partner->getFirstAdminResource()->profile->nid_no,
                'docImage' => $linkToBase64,
                'docType' => MtbConstants::DECLARATION_LETTER,
            ],
            [
                'ticketId' => $this->partner->partnerMefInformation->mtb_ticket_id,
                'docRefId' => $this->partner->getFirstAdminResource()->profile->nid_no,
                'docImage' => base64_encode(file_get_contents($this->partner->getFirstAdminResource()->profile->nid_image_back)),
                'docType' => MtbConstants::NID_BACK,
            ],
            [
                'ticketId' => $this->partner->partnerMefInformation->mtb_ticket_id,
                'docRefId' => strval($this->partner->id),
                'docImage' => base64_encode(file_get_contents($this->partner->getFirstAdminResource()->profile->pro_pic)),
                'docType' => MtbConstants::CUSTOMER_PHOTO,
            ],
            [
                'ticketId' => $this->partner->partnerMefInformation->mtb_ticket_id,
                'docRefId' => strval($this->partner->id),
                'docImage' => base64_encode(file_get_contents(json_decode($this->partnerMefInformation->partner_information)->nominee_nid)),
                'docType' => MtbConstants::NOMINEE_NID_FRONT,
            ],
            [
                'ticketId' => $this->partner->partnerMefInformation->mtb_ticket_id,
                'docRefId' => strval($this->partner->id),
                'docImage' => base64_encode(file_get_contents(json_decode($this->partnerMefInformation->partner_information)->nominee_nid_image_back)),
                'docType' => MtbConstants::NOMINEE_NID_BACK,
            ],
            [
                'ticketId' => $this->partner->partnerMefInformation->mtb_ticket_id,
                'docRefId' => strval($this->partner->id),
                'docImage' => base64_encode(file_get_contents(json_decode($this->partnerMefInformation->partner_information)->customer_signature)),
                'docType' => MtbConstants::CUSTOMER_SIGNATURE,
            ]);
        if (json_decode($this->partnerMefInformation->partner_information)->tradeLicenseExists == 'হ্যা') {
            $data[] = [
                'ticketId' => $this->partner->partnerMefInformation->mtb_ticket_id,
                'docRefId' => strval($this->partner->id),
                'docImage' => base64_encode(file_get_contents($this->partner->basicInformations->trade_license_attachment)),
                'docType' => MtbConstants::TRADE_LICENSE,
            ];
        }
        return [
            'RequestData' => $data,
            'requestId' => strval($this->partner->id),
            'channelId' => MtbConstants::CHANNEL_ID
        ];
    }

    public function uploadDocument()
    {
        $data = $this->makeData();
        return $this->client->post(QRPaymentStatics::MTB_DOCUMENT_UPLOAD, $data, AuthTypes::BARER_TOKEN);
    }
}
