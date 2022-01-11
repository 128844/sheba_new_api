<?php

namespace Sheba\MerchantEnrollment\MEFFormCategory\Category;

use Sheba\MerchantEnrollment\InstitutionInformation;
use Sheba\MerchantEnrollment\MEFFormCategory\CategoryGetter;
use Sheba\MerchantEnrollment\MEFFormCategory\MEFFormCategory;
use Sheba\MerchantEnrollment\Statics\FormStatics;

class Documents extends MEFFormCategory
{
    public $category_code = 'documents';

    public function completion(): array
    {
        return [
            'en' => 100,
            'bn' => 100
        ];
    }

    public function get(): CategoryGetter
    {
        $formItems = $this->getFormFields();
        $formData  = (new InstitutionInformation())->setPartner($this->partner)->setFormItems($formItems)->getByCode($this->category_code);
        return $this->getFormData($formItems, $formData);
    }

    public function post($data)
    {

    }

    public function getFormFields()
    {
        $form_fields = FormStatics::documents();
        if(count($this->exclude_form_keys)) {
            foreach ($form_fields as $key => $item) {
                if(in_array($item['id'], $this->exclude_form_keys))
                    unset($form_fields[$key]);

            }
        }
        return $form_fields;
    }
}