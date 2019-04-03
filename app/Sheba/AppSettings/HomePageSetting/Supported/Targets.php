<?php namespace Sheba\AppSettings\HomePageSetting\Supported;

use Sheba\AppSettings\HomePageSetting\Exceptions\UnsupportedTarget;

class Targets extends Constants
{
    const MASTER_CATEGORY = "master_category";
    const SECONDARY_CATEGORY = "secondary_category";
    const SERVICE = "service";
    const CATEGORY_GROUP = "category_group";
    const VOUCHER = "voucher";
    const TOP_UP = "top_up";
    const FAVOURITES = "favourites";
    const OFFER_LIST = "offer_list";
    const OFFER = "offer";
    const SUBSCRIPTION_LIST = "subscription_list";
    const SUBSCRIPTION_SERVICE = "subscription_service";
    const EXTERNAL_PROJECT = 'external_project';
    const REFERRALS = 'referral';
    const INFO_CALL = 'info_call';
    const NEAR_BY_PARTNERS = 'near_by_partners';

    /**
     * @param $target
     * @throws UnsupportedTarget
     */
    protected static function throwException($target)
    {
        throw new UnsupportedTarget($target);
    }
}