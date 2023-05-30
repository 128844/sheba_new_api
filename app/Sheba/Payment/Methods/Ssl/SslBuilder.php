<?php namespace Sheba\Payment\Methods\Ssl;

use App\Models\Customer;
use App\Models\Partner;
use App\Models\Payable;
use Sheba\Payment\Exceptions\InvalidPaymentMethod;
use Sheba\Payment\Exceptions\StoreNotFoundException;
use Sheba\Payment\Factory\PaymentStrategy;
use Sheba\Payment\Methods\Ssl\Stores\DefaultStore;
use Sheba\Payment\Methods\Ssl\Stores\Donation;
use Sheba\Payment\Methods\Ssl\Stores\DynamicSslStore;
use Sheba\Payment\Methods\Ssl\Stores\MarketPlace;
use Sheba\Payment\Methods\Ssl\Stores\SslStore;
use Sheba\Payment\PayableUser;


class SslBuilder
{
    /**
     * @param Payable $payable
     * @return Ssl
     * @throws InvalidPaymentMethod
     */
    public static function get(Payable $payable)
    {
        /** @var Ssl $ssl */
        $ssl = app(Ssl::class);
        $ssl->setStore(self::getStore($payable));
        return $ssl;
    }

    /**
     * @return Ssl
     */
    public static function getForDonation()
    {
        /** @var Ssl $ssl */
        $ssl = app(Ssl::class);
        $ssl->setStore(new Donation())->forDonation();
        return $ssl;
    }

    /**
     * @param Payable $payable
     * @return SslStore
     * @throws InvalidPaymentMethod
     * @throws StoreNotFoundException
     */
    public static function getStore(Payable $payable)
    {
        /** @var PayableUser $user */
        $user = $payable->user;

        if ($payable->isPaymentLink()) return self::getStoreForPaymentLink($payable);

        if ($user instanceof Customer) return new MarketPlace();
        if ($user instanceof Partner&&$user->isMxPartner()) return new MarketPlace();
        return new DefaultStore();
    }

    /**
     * @param $store_name
     * @return Ssl
     */
    public static function getByStoreName($store_name)
    {
        /** @var Ssl $ssl */
        $ssl = app(Ssl::class);
        $ssl->setStore(self::getStoreByName($store_name));
        return $ssl;
    }

    public static function getStoreByName($name)
    {
        if ($name == MarketPlace::NAME) return new MarketPlace();

        if ($name == Donation::NAME) return new Donation();

        return new DefaultStore();
    }

    /**
     * @param Payable $payable
     * @return bool
     */
    public static function shouldUseForPaymentLink(Payable $payable)
    {
        try {
            self::getStoreForPaymentLink($payable);
            return true;
        } catch (InvalidPaymentMethod $e) {
            return false;
        }
    }

    /**
     * @param Payable $payable
     * @return SslStore
     * @throws InvalidPaymentMethod|StoreNotFoundException
     */
    public static function getStoreForPaymentLink(Payable $payable)
    {
        $payment_link = $payable->getPaymentLink();
        $receiver = ($payment_link->getPaymentReceiver());

        if ($payment_link->isForMissionSaveBangladesh()) return new Donation();

        if ($receiver instanceof Partner) return (new DynamicSslStore($receiver))->set();

        return new DefaultStore();

    }

    /**
     * @param Payable $payable
     * @return bool
     */
    public static function isPortWalletFailed(Payable $payable)
    {
        return $payable->payments()->initiationFailed()->count() > 0;
    }
}
