<?php

namespace Sheba\Payment\Methods;

use App\Models\Partner;

trait DynamicStore
{
    /**
     * @var Partner
     */
    protected $partner;

    public function setPartner($receiver)
    {
        $this->partner = $receiver;
    }

    protected function getStoreAccount($payment_method)
    {
        return $this->partner->pgwStoreAccounts()->join('pgw_stores', 'pgw_store_id', '=', 'pgw_stores.id')
            ->where('pgw_stores.name', $payment_method)->first();
    }
}