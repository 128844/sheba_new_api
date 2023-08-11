<?php

namespace Sheba\Payment\Factory;

use App\Models\Customer;
use App\Models\Partner;
use App\Models\Payable;
use App\Sheba\Payment\Methods\AamarPay\AamarPay;
use App\Sheba\Payment\Methods\Nagad\NagadBuilder;
use App\Sheba\QRPayment\Methods\MTB\MtbQr;
use Illuminate\Foundation\Application;
use Sheba\Helpers\ConstGetter;
use Sheba\Payment\Exceptions\InvalidPaymentMethod;
use Sheba\Payment\Methods\Bkash\Bkash;
use Sheba\Payment\Methods\BondhuBalance;
use Sheba\Payment\Methods\Cbl\Cbl;
use Sheba\Payment\Methods\Ebl\EblBuilder;
use Sheba\Payment\Methods\OkWallet\OkWallet;
use Sheba\Payment\Methods\PartnerWallet;
use Sheba\Payment\Methods\PaymentMethod;
use Sheba\Payment\Methods\Paystation\Paystation;
use Sheba\Payment\Methods\PortWallet\PortWallet;
use Sheba\Payment\Methods\ShurjoPay\ShurjoPay;
use Sheba\Payment\Methods\Ssl\SslBuilder;
use Sheba\Payment\Methods\Upay\UpayBuilder;
use Sheba\Payment\Methods\Wallet;
use Sheba\Payment\PayableUser;

class PaymentStrategy
{
    use ConstGetter;

    const BKASH          = "bkash";
    const ONLINE         = "online";
    const SSL            = "ssl";
    const WALLET         = "wallet";
    const CBL            = "cbl";
    const PARTNER_WALLET = "partner_wallet";
    const BONDHU_BALANCE = "bondhu_balance";
    const OK_WALLET      = 'ok_wallet';
    const SSL_DONATION   = "ssl_donation";
    const PORT_WALLET    = "port_wallet";
    const NAGAD          = 'nagad';
    const EBL            = 'ebl';
    const MTB            = 'mtb';
    const SHURJOPAY      = 'shurjopay';
    const UPAY           = 'upay';
    const AAMARPAY       = 'aamarpay';
    const PAYSTATION     = 'paystation';

    public static function getDefaultOnlineMethod()
    {
        return self::SSL;
    }

    /**
     * @param         $method
     * @param Payable $payable
     * @return PaymentMethod
     * @throws InvalidPaymentMethod
     */
    public static function getMethod($method, Payable $payable)
    {
        if (!self::isValid($method)) throw new InvalidPaymentMethod();

        if ($method == self::ONLINE) $method = self::getRealOnlineMethod($payable);

        switch ($method) {
            case self::SSL:
                return SslBuilder::get($payable);
            case self::SSL_DONATION:
                return SslBuilder::getForDonation();
            case self::BKASH:
                return app(Bkash::class);
            case self::WALLET:
                return app(Wallet::class);
            case self::CBL:
                return app(Cbl::class);
            case self::PARTNER_WALLET:
                return app(PartnerWallet::class);
            case self::BONDHU_BALANCE:
                return app(BondhuBalance::class);
            case self::OK_WALLET:
                return app(OkWallet::class);
            case self::PORT_WALLET:
                return app(PortWallet::class);
            case self::NAGAD:
                return NagadBuilder::get($payable);
            case self::EBL:
                return EblBuilder::get($payable);
            case self::SHURJOPAY:
                return app(ShurjoPay::class);
            case self::UPAY:
                return UpayBuilder::get($payable);
            case self::AAMARPAY:
                return app(AamarPay::class);
            case self::PAYSTATION:
                return app(Paystation::class);
        }
    }

    /**
     * @param $method
     * @return Application|mixed|void
     * @throws InvalidPaymentMethod
     */
    public static function getQRMethod($method)
    {
        if (!self::isValid($method)) throw new InvalidPaymentMethod();
        if ($method == self::MTB) {
            return app(MtbQr::class);
        }
    }

    /**
     * @param Payable $payable
     * @return string
     */
    private static function getRealOnlineMethod(Payable $payable)
    {
        /** @var PayableUser $user */
        $user = $payable->user;

        if ($payable->isPaymentLink()) {
            return SslBuilder::shouldUseForPaymentLink($payable) ? self::SSL : self::PORT_WALLET;
        }

        if ($user instanceof Customer) return self::SSL;
        else if ($user instanceof Partner) return self::PORT_WALLET;
    }
}
