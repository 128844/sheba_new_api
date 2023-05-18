<?php namespace App\Sheba\Payment\Methods\Nagad;

use App\Models\Affiliate;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\Payable;
use Sheba\Payment\Methods\Nagad\Stores\AffiliateStore;
use Sheba\Payment\Methods\Nagad\Stores\DefaultStore;
use Sheba\Payment\Methods\Nagad\Nagad;
use Sheba\Payment\Methods\Nagad\Stores\MarketplaceStore;
use Sheba\Payment\PayableUser;

class NagadBuilder
{
    /**
     * @param Payable $payable
     * @return Nagad
     */
    public static function get(Payable $payable): Nagad
    {
        /** @var Nagad $nagad */
        $nagad = app(Nagad::class);
        $nagad->setStore(self::getStore($payable));
        return $nagad;
    }

    /**
     * @param Payable $payable
     * @return AffiliateStore|MarketplaceStore|DefaultStore
     */
    public static function getStore(Payable $payable)
    {
        /** @var PayableUser $user */
        $user = $payable->user;
        $type = $payable->readable_type;
        if ($user instanceof Affiliate) return new AffiliateStore();
        if ($user instanceof Partner && $user->isMxPartner()) return new MarketplaceStore();
        if ($user instanceof Customer && $type != 'payment_link') return new MarketplaceStore();
        return new DefaultStore();
    }

    /**
     * @param $store_name
     * @return Nagad
     */
    public static function getByStoreName($store_name): Nagad
    {
        /** @var Nagad $nagad */
        $nagad = app(Nagad::class);
        $nagad->setStore(self::getStoreByName($store_name));
        return $nagad;
    }

    /**
     * @param $name
     * @return AffiliateStore|DefaultStore|MarketplaceStore
     */
    public static function getStoreByName($name)
    {
        if ($name == AffiliateStore::NAME) return new AffiliateStore();
        if ($name == MarketplaceStore::NAME) return new MarketplaceStore();
        return new DefaultStore();
    }

    /**
     * @param Payable $payable
     * @return bool
     */
    public static function isPortWalletFailed(Payable $payable): bool
    {
        return $payable->payments()->initiationFailed()->count() > 0;
    }
}
