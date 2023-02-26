<?php

namespace App\Sheba\DynamicForm;

use App\Models\Partner;
use App\Sheba\DynamicForm\DataSources\AccountType;
use App\Sheba\DynamicForm\DataSources\BanksList;
use App\Sheba\MTB\Exceptions\MtbServiceServerError;
use Sheba\Dal\PartnerMefInformation\Contract as PartnerMefInformationRepo;
use Sheba\Dal\ProfileNIDSubmissionLog\Model as ProfileNIDSubmissionLog;

class FormFieldBuilder
{
    private $field;
    private $partner;
    private $partnerMefInformation;
    private $partnerBasicInformation;
    private $firstAdminProfile;
    private $basicInformation;
    private $bankInformation;
    private $accountType;

    /**
     * @param  mixed  $field
     * @return FormFieldBuilder
     */
    public function setField($field): FormFieldBuilder
    {
        $this->field = $field;
        return $this;
    }

    /**
     * @param  Partner  $partner
     * @return FormFieldBuilder
     */
    public function setPartner(Partner $partner): FormFieldBuilder
    {
        $this->partner = $partner;
        $this->partner->generatedDomain = "smanager.xyz/s/".$this->partner->sub_domain;
        $porichoy_data = null;
        $nid_information = ProfileNIDSubmissionLog::where('profile_id', $this->partner->getFirstAdminResource()->profile->id)
            ->where('verification_status', 'approved')
            ->whereNotNull('porichy_data')
            ->last();

        if (isset($nid_information->porichy_data)) {
            $porichoy_data = json_decode($nid_information->porichy_data);
        }

        $this->partner->nid = $porichoy_data ? $porichoy_data->porichoy_data->nid_no : $this->partner->getFirstAdminResource()->profile->nid_no;
        $this->partner->dob = date("Y-m-d", strtotime($this->partner->getFirstAdminResource()->profile->dob));

        return $this;
    }

    /**
     * @return FormFieldBuilder
     */
    public function setPartnerMefInformation(): FormFieldBuilder
    {
        if (!isset($this->partner->partnerMefInformation)) {
            $mefRepo = app(PartnerMefInformationRepo::class);
            $this->partner->partnerMefInformation = $mefRepo->create(["partner_id" => $this->partner->id]);
        }

        $this->partnerMefInformation = json_decode($this->partner->partnerMefInformation->partner_information);

        return $this;
    }

    public function build(): FormField
    {
        $this->bankInformation = app(BanksList::class);
        $this->accountType = app(AccountType::class);

        $form_field = (new FormField())->setFormInput(json_decode($this->field->data));

        if (($form_field->data_source) !== "") {
            $data_source = ($form_field->data_source);
            $data_source_id = ($form_field->data_source_id);

            if (!isset($this->$data_source)) {
                $function_name = "set".ucfirst($data_source);
                $this->$function_name();
            }

            if (isset($this->$data_source)) {
                $data = $this->$data_source->$data_source_id ?? "";
                $form_field->setData($data);
            }
        }

        return $form_field;
    }

    public function setPartnerBasicInformation()
    {
        $this->partnerBasicInformation = json_decode($this->partner->basicInformations->additional_information);
    }

    public function setFirstAdminProfile()
    {
        $this->firstAdminProfile = $this->partner->getFirstAdminResource()->profile;
    }

    public function setBasicInformation()
    {
        $this->basicInformation = $this->partner->basicInformations;
    }
}