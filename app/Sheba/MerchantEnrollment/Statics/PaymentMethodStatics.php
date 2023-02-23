<?php

namespace Sheba\MerchantEnrollment\Statics;

use Sheba\ResellerPayment\Exceptions\InvalidKeyException;

class PaymentMethodStatics
{
    const SSL_BANNER_URL = "https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/ssl_banner.png";
    const MTB_BANNER_URL = "https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/partner/reseller_payment/mtb_banner.png";

    const APPLY_SUCCESS_MESSAGE = [
        "body"  => "আবেদন যাচাই করতে ১০ কার্যদিবস সময় লাগতে পারে অনুগ্রহ করে অপেক্ষা করুন।",
        "title" => "আবেদন সফল হয়েছে!"
    ];

    public static function classMap(): array
    {
        return [
            'ssl'       => 'SslGateway',
            'bkash'     => 'BkashGateway',
            'shurjopay' => 'ShurjoPayGateway',
            'aamarpay'  => 'AamarPayGateway',
        ];
    }

    /**
     * @param $paymentGatewayCode
     * @return mixed
     * @throws InvalidKeyException
     */
    public static function paymentGatewayCategoryList($paymentGatewayCode)
    {
        $categoryList = config('reseller_payment.category_list');
        if (isset($categoryList[$paymentGatewayCode])) {
            return $categoryList[$paymentGatewayCode];
        }

        throw new InvalidKeyException();
    }

    public static function categoryTitles($category_code)
    {
        $titles = config('reseller_payment.category_titles');
        if (isset($titles[$category_code])) {
            return $titles[$category_code];
        }
        return ['en' => '', 'bn' => ''];
    }

    /**
     * @param $paymentGatewayKey
     * @return mixed
     * @throws InvalidKeyException
     */
    public static function paymentMethodWiseExcludedKeys($paymentGatewayKey)
    {
        $categoryList = config('reseller_payment.exclude_form_keys');
        if (isset($categoryList[$paymentGatewayKey])) {
            return $categoryList[$paymentGatewayKey];
        }
        throw new InvalidKeyException();
    }

    public static function completionPageMessage(): array
    {
        return [
            "incomplete_message" => "SSL পেমেন্ট সার্ভিস সচল করতে প্রয়োজনীয় তথ্য প্রদান করুন।",
            "completed_message"  => "প্রয়োজনীয় তথ্য দেয়া সম্পন্ন হয়েছ, SSL পেমেন্ট সার্ভিস সচল করতে আবেদন করুন।"
        ];
    }

    public static function detailsFAQ(): array
    {
        return [
            'আপনার ব্যবসার প্রোফাইল সম্পন্ন করুন',
            'পেমেন্ট সার্ভিসের জন্য আবেদন করুন',
            'পেমেন্ট সার্ভিস কনফিগার করুন'
        ];
    }

    public static function getSslBannerURL(): string
    {
        return self::SSL_BANNER_URL;
    }

    public static function getMtbBannerURL(): string
    {
        return self::MTB_BANNER_URL;
    }

    public static function dynamicCompletionPageMessage($key): array
    {
        return isset(config('reseller_payment.completion_message')[$key]) ? config('reseller_payment.completion_message')[$key] : [];
    }
}
