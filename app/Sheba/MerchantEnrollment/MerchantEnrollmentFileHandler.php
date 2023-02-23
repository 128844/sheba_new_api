<?php

namespace Sheba\MerchantEnrollment;

use Sheba\FileManagers\CdnFileManager;
use Sheba\FileManagers\FileManager;

class MerchantEnrollmentFileHandler
{
    use FileManager, CdnFileManager;

    private $partner;
    private $uploadedUrl;
    private $partner_basic_information;
    private $first_admin_profile;

    /**
     * @param mixed $partner
     * @return MerchantEnrollmentFileHandler
     */
    public function setPartner($partner): MerchantEnrollmentFileHandler
    {
        $this->partner = $partner;
        $this->partner_basic_information = $partner->basicInformations;
        $this->first_admin_profile = $this->partner->getFirstAdminResource()->profile;
        return $this;
    }

    /**
     * @param $file
     * @param $form_field
     * @return $this
     */
    public function uploadDocument($file, $form_field): MerchantEnrollmentFileHandler
    {
        if (!empty($this->{$form_field['data_source']}->{$form_field['data_source_id']})) {
            $this->deleteFile($this->{$form_field['data_source']}->{$form_field['data_source_id']});
        }

        list($file, $filename) = $this->makeAttachment($file, $form_field['data_source_id'] . "-" . $this->partner->id);

        if (!isset($form_field['upload_folder'])) {
            $this->uploadedUrl = $this->saveFileToCDN($file, 'getPartnerDocumentsFolder', $filename);
        } else {
            $this->uploadedUrl = $this->saveFileToCDN($file, $form_field['upload_folder'](), $filename);
        }

        return $this;

    }

    /**
     * @return mixed
     */
    public function getUploadedUrl()
    {
        return $this->uploadedUrl;
    }
}
