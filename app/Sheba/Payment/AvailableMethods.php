<?php namespace Sheba\Payment;

use App\Models\Partner;
use Exception;
use Sheba\Payment\Factory\PaymentStrategy;
use Sheba\Payment\Presenter\PaymentMethodDetails;
use Sheba\PaymentLink\Exceptions\InvalidPaymentLinkIdentifierException;
use Sheba\Repositories\Interfaces\PaymentLinkRepositoryInterface;

class AvailableMethods
{
    /**
     * @param $payable_type
     * @param $payable_type_id
     * @param $version_code
     * @param $platform_name
     * @param $user_type
     * @return PaymentMethodDetails[]
     * @throws Exception
     */
    public static function getDetails($payable_type, $payable_type_id, $version_code, $platform_name, $user_type)
    {
        $methods = self::getMethods($payable_type, $payable_type_id, $user_type);

        $details = [];
        foreach ($methods as $method) {
            $detail = new PaymentMethodDetails($method, $payable_type);
            if ($method == PaymentStrategy::CBL) {
                $detail->setIsPublished(self::getCblStatus($version_code, $platform_name));
            }
            $details[] = $detail;
        }

        return $details;
    }

    /**
     * @param $payable_type
     * @param $payable_type_id
     * @param $user_type
     * @return array
     * @throws Exception
     */
    public static function getMethods($payable_type, $payable_type_id, $user_type)
    {
        $payable_type = $payable_type ?: "order";

        switch ($payable_type) {
            case 'order':
                $methods = self::getRegularPayments();
                break;
            case 'subscription':
                $methods = self::getSubscriptionPayments();
                break;
            case 'voucher':
                $methods = self::getVoucherPayments();
                break;
            case 'movie_ticket':
            case 'transport_ticket':
                $methods = self::getTicketsPayments($user_type);
                break;
            case 'business':
                $methods = self::getBusinessPayments();
                break;
            case 'utility':
                $methods = self::getUtilityPayments();
                break;
            case 'payment_link':
                $methods = self::getPaymentLinkPayments($payable_type_id);
                break;
            case 'wallet_recharge':
                $methods = self::getWalletRechargePayments();
                break;
            case 'loan_repayment':
                $methods = self::getLoanRepaymentPayments();
                break;
            case 'bondhu_point':
                $methods = self::getBondhuPointPayments();
                break;
            default:
                throw new Exception('Invalid Payable Type');
        }

        return $methods;
    }

    public static function getRegularPayments()
    {
        return [
            PaymentStrategy::WALLET,
            PaymentStrategy::BKASH,
            PaymentStrategy::NAGAD,
            PaymentStrategy::ONLINE,
            PaymentStrategy::CBL,
        ];
    }

    public static function getSubscriptionPayments()
    {
        return [
            PaymentStrategy::WALLET,
            PaymentStrategy::BKASH,
            PaymentStrategy::CBL,
            PaymentStrategy::ONLINE,
        ];
    }

    public static function getVoucherPayments()
    {
        return [
            PaymentStrategy::CBL,
            PaymentStrategy::ONLINE,
        ];
    }

    public static function getWalletRechargePayments()
    {
        return [
            PaymentStrategy::CBL,
            PaymentStrategy::ONLINE,
            PaymentStrategy::BKASH,
            PaymentStrategy::OK_WALLET,
            PaymentStrategy::NAGAD,
            PaymentStrategy::UPAY
        ];
    }

    public static function getTicketsPayments($user_type)
    {
        if (isset($user_type) && $user_type !== 'customer') {
            return [
                PaymentStrategy::WALLET
            ];
        }

        return [
            PaymentStrategy::CBL,
            PaymentStrategy::ONLINE,
            PaymentStrategy::BKASH,
            PaymentStrategy::WALLET
        ];
    }

    public static function getBusinessPayments()
    {
        return [
            PaymentStrategy::CBL,
            PaymentStrategy::ONLINE,
            PaymentStrategy::BKASH
        ];
    }

    public static function getUtilityPayments()
    {
        return [
            PaymentStrategy::CBL,
            PaymentStrategy::ONLINE,
            PaymentStrategy::BKASH,
            PaymentStrategy::WALLET
        ];
    }

    /**
     * @param $payment_link_identifier
     * @return array
     * @throws InvalidPaymentLinkIdentifierException
     */
    public static function getPaymentLinkPayments($payment_link_identifier)
    {

        /** @var PaymentLinkRepositoryInterface $repo */
        $repo = app(PaymentLinkRepositoryInterface::class);
        $payment_link = $repo->findByIdentifier($payment_link_identifier);
        if(!isset($payment_link)) throw new InvalidPaymentLinkIdentifierException();

        $receiver = ($payment_link->getPaymentReceiver());
        if($receiver instanceof Partner) return (new AvailableMethods())->getPublishedPartnerPaymentGateways($receiver);

        return [
            PaymentStrategy::BKASH,
            PaymentStrategy::NAGAD,
            PaymentStrategy::CBL,
            PaymentStrategy::EBL,
            PaymentStrategy::ONLINE,
            PaymentStrategy::SSL_DONATION
        ];
    }

    /**
     * @param $partner
     * @return array
     */
    public function getPublishedPartnerPaymentGateways($partner): array
    {
        $payment_methods = array();
        $partnerStoreAccounts = $partner->pgwGatewayAccounts()->published()->with('gateway')->get();
        foreach ($partnerStoreAccounts as $storeAccount) {
            $name = $storeAccount->gateway->key;
            $payment_methods[] = $name;
        }
        return $payment_methods;
    }


    public static function getLoanRepaymentPayments()
    {
        return [
            PaymentStrategy::NAGAD,
        ];
    }

    public static function getBondhuPointPayments()
    {
        return [
            PaymentStrategy::NAGAD,
            PaymentStrategy::BKASH,
            PaymentStrategy::ONLINE
        ];
    }

    /**
     * @param $version_code
     * @param $platform_name
     * @return bool
     */
    private static function getCblStatus($version_code, $platform_name)
    {
        if (!$version_code) return true;

        return $platform_name && $platform_name == 'ios' ? true : ($version_code > 30112);
    }
}
