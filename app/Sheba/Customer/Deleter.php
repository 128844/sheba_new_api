<?php

namespace Sheba\Customer;

use App\Models\Customer;
use App\Models\DeletedUser;
use App\Models\Partner;
use App\Models\Profile;
use App\Models\Resource;
use App\Sheba\Customer\Exception\CustomerDeleteException;
use Sheba\OAuth2\AccountServerClient;

class Deleter
{
    /** @var Profile $profile */
    private $profile;
    /** @var Partner $partner */
    private $partner;
    /** @var Customer $customer */
    private $customer;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
        $this->profile  = $this->customer->profile;
    }

    /**
     * @throws CustomerDeleteException
     */
    private function checkPartner()
    {
        /** @var Resource $resource */
        $resource = $this->profile->resource;
        if (!empty($resource)) {
            /** @var Partner[] $partners */
            $partners = $resource->associatePartners();
            foreach ($partners as $partner) {
                /** Parner  */
                $orders = $partner->partner_orders()->ongoing()->count();
                if ($orders>0){
                    throw  new CustomerDeleteException("Customer Has Pending Orders");
                }
            }
        }
        return $this;
    }

    private function invalidateJWT()
    {
        (app(AccountServerClient::class))->post("/api/v1/logout-from-all/admin", ["profile_id" => $this->profile->id]);
        return $this;
    }
    private function deleteProfile(){

        $this->profile->update(['mobile'=>null,'email'=>null,'remember_token'=>str_random(255)]);
        DeletedUser::create(['profile_id'=>$this->profile->id,'mobile'=>$this->profile->mobile,'email'=>$this->profile->email]);
        return $this;
    }

    /**
     * @throws CustomerDeleteException
     */
    public function delete()
    {
        $this->checkPartner()->deleteProfile()->invalidateJWT();

    }
}