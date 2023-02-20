<?php

namespace App\Sheba\DynamicForm;

use Illuminate\Contracts\Support\Arrayable;
use ReflectionClass;
use Sheba\Helpers\BasicGetter;

class PartnerMefInformation implements Arrayable
{
    use BasicGetter;

    private $fatherName;
    private $motherName;
    private $nomineeDOB;
    private $nomineeName;
    private $nomineePhone;
    private $presentAddress;
    private $businessStartDt;
    private $nomineeRelation;
    private $presentDistrict;
    private $presentDivision;
    private $presentPostCode;
    private $permanentAddress;
    private $nomineeFatherName;
    private $nomineeMotherName;
    private $tradeLicenseExists;
    private $permanentPostCode;
    private $nominee_nid;
    private $nominee_nid_image_back;
    private $customer_signature;
    private $trade_license;
    private $userDesignation;
    private $shopOwnerName;
    private $shopName;
    private $shopClass;
    private $nomineeNid;
    private $nomineePresentPostCode;
    private $nomineePresentAddress;
    private $nomineePermanentPostCode;
    private $nomineePermanentAddress;
    private $yearsInBusiness;
    private $organizationPostalCode;
    private $accountHolderName;
    private $bankName;
    private $branchName;
    private $bankAccountNumber;
    private $bankRoutingNumber;
    private $branchCode;
    private $userRecentPhoto;
    private $bankChequePhoto;
    private $businessVisitingCard;
    private $spouseName;

    public function setProperty($input): PartnerMefInformation
    {
        foreach ($input as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        return $this;
    }

    public function __set($property, $value)
    {
        $this->$property = $value;
    }

    public function toArray(): array
    {
        $reflection_class = new ReflectionClass($this);
        $data = [];
        foreach ($reflection_class->getProperties() as $item) {
            $data[$item->name] = $this->{$item->name};
        }
        return $data;
    }

    public function getAvailable(): array
    {
        $reflection_class = new ReflectionClass($this);
        $data = [];
        foreach ($reflection_class->getProperties() as $item) {
            if (isset($this->{$item->name})) {
                $data[$item->name] = $this->{$item->name};
            }
        }

        return $data;
    }
}
