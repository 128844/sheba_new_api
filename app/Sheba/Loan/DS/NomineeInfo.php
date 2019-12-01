<?php

namespace Sheba\Loan\DS;
use App\Models\Partner;
use App\Models\Profile;
use Sheba\ModificationFields;

class NomineeInfo
{
    use ReflectionArray,ModificationFields;
    protected $name;
    protected $mobile;
    protected $pro_pic;
    protected $nid_front_image;
    protected $nid_back_image;

    /**
     * @throws \ReflectionException
     */
    public function create(Partner $partner) {
        $this->setModifier($partner);
        $data=$this->noNullableArray();
        $profile=new Profile($data);
        $profile->remember_token=str_random(255);
        $this->withCreateModificationField($profile);
        $profile->save();
        return $profile;
    }

    public function update() { }
}
