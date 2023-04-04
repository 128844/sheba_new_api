<?php

namespace App\Sheba\MerchantEnrollment;

use App\Models\Partner;
use App\Models\Profile;
use Sheba\MerchantEnrollment\PartnerAllInformation;
use Sheba\ModificationFields;

class PersonalInformation extends PartnerAllInformation
{
    use ModificationFields;

    public function setPartner(Partner $partner): PersonalInformation
    {
        $this->partner = $partner;
        if ($operation_resource = $this->partner->operationResources()->first()) {
            $this->partner_resource_profile = $operation_resource->profile;
        } else {
            $this->partner_resource_profile = $this->partner->admins()->first()->profile;
        }

        return $this;
    }

    public function personal_get(): array
    {
        return $this->getFormFieldValues();
    }

    public function personal_post($post_data)
    {
        $post_data = json_decode($post_data, true);
        foreach ($this->formItems as $item) {
            if ($item['is_editable']) {
                if (isset($post_data[$item['id']])) {
                    $key = $item['id'];
                    if (isset($item['data_source'])) {
                        $this->{$item['data_source']}->{$item['data_source_id']} = $post_data[$key];
                    }
                }
            }
        }
        $this->partner_resource_profile->save();
    }

    /**
     * @param $category_code  = "institution" | "personal" | ""
     * @return array
     */
    public function getByCode($category_code): array
    {
        return $this->personal_get();
    }

    public function postByCode($category_code, $post_data)
    {
        $this->personal_post($post_data);
    }

    protected function getFormFieldValues(): array
    {
        $values = [];
        foreach ($this->formItems as $formItem) {
            if (isset($formItem['data_source'])) {
                $this->mapNonJsonFormField($formItem, $values);
            }
        }
        return $values;
    }

    public function isNidVerified(): bool
    {
        return !!($this->partner_resource_profile->nid_verified);
    }

    public function getPersonalPhoto(): array
    {
        return ["personal_photo" => $this->partner_resource_profile->pro_pic];
    }

    public function checkIfUniqueEmail($email): bool
    {
        $count = Profile::query()->where('id', '!=', $this->partner_resource_profile->id)->where('email', $email)->count();
        return !($count > 0);
    }
}