<?php

use Sheba\Helpers\Formatters\BDMobileFormatter;
use Sheba\Helpers\Formatters\TakaFormatter;

if (!function_exists('formatMobile')) {
    /**
     * Format Mobile number with +88 .
     *
     * 01. Mobile starts with '+88'
     * 02. When mobile starts with '88' replace it with '+88'
     * 03. Real mobile no add '+880' at the start
     *
     * @param  $number
     * @return string
     */
    function formatMobile($number)
    {
        return BDMobileFormatter::format($number);
    }
}

if (!function_exists('formatMobileAux')) {
    /**
     * Format mobile number, add +88 & remove space.
     * This function should be removed at refactoring.
     *
     * @param $mobile
     * @return mixed
     */
    function formatMobileAux($mobile)
    {
        return BDMobileFormatter::formatAux($mobile);
    }
}

if (!function_exists('formatMobileReverse')) {
    /**
     * Format mobile number, remove +88.
     *
     * @param $mobile
     * @return mixed
     */
    function formatMobileReverse($mobile)
    {
        return BDMobileFormatter::reverse($mobile);
    }
}

if (!function_exists('getOriginalMobileNumber')) {
    /**
     * Format Mobile number without +88 .
     *
     * @param  $number
     * @return string
     */
    function getOriginalMobileNumber($number)
    {
        return BDMobileFormatter::getOriginal($number);
    }
}

if (!function_exists('commaSeparate')) {
    /**
     * Format comma separated number.
     *
     * @param  $amount
     * @param  $decimal
     * @param  $format
     * @return string
     */
    function commaSeparate($amount, $decimal = 0, $format = "BDT")
    {
        return TakaFormatter::commaSeparate($amount, $decimal, $format);
    }
}

if (!function_exists('formatTaka')) {
    /**
     * Format integer amount of taka into decimal.
     *
     * @param  $amount
     * @param  $comma_separation
     * @param  $comma_separation_format
     * @return string
     */
    function formatTaka($amount, $comma_separation = false, $comma_separation_format = "BDT")
    {
        return TakaFormatter::toString($amount, $comma_separation, $comma_separation_format);
    }
}

if (!function_exists('formatTakaToDecimal')) {
    /**
     * Format integer amount of taka into decimal.
     *
     * @param  $amount
     * @param  $comma_separation
     * @param  $comma_separation_format
     * @return string
     */
    function formatTakaToDecimal($amount, $comma_separation = false, $comma_separation_format = "BDT")
    {
        return TakaFormatter::toDecimal($amount, $comma_separation, $comma_separation_format);
    }
}

if (!function_exists('currencyShortenFormat')) {
    /**
     * Return shorthand currency format.
     *
     * @param $amount
     * @param $precision = 1
     * @return string
     */
    function currencyShortenFormat($amount, $precision = 1)
    {
        return TakaFormatter::currencyShortenFormat($amount, $precision);
    }
}

if (!function_exists('trim_phone_number')) {
    /**
     * @param $number
     * @param string $index_number
     * @return string
     */
    function trim_phone_number($number, $index_number = '0')
    {
        return strstr($number, $index_number);
    }
}

if (!function_exists('en2bnNumber')) {
    /**
     * @param  $number
     * @return string
     */
    function en2bnNumber($number)
    {
        $search_array  = [ "1", "2", "3", "4", "5", "6", "7", "8", "9", "0", ".", "," ];
        $replace_array = [ "১", "২", "৩", "৪", "৫", "৬", "৭", "৮", "৯", "০", ".", "," ];
        return str_replace($search_array, $replace_array, $number);
    }
}

if (!function_exists('ordinal')) {
    /**
     * Ordinal numbers refer to a position in a series.
     *
     * @param $number = any natural number
     * @return String
     */
    function ordinal($number)
    {
        $ends = [
            'th',
            'st',
            'nd',
            'rd',
            'th',
            'th',
            'th',
            'th',
            'th',
            'th'
        ];
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) return $number . 'th';

        return $number . $ends[$number % 10];
    }
}

if (!function_exists('floatValFormat')) {
    /**
     * @param $value
     * @return float
     */
    function floatValFormat($value)
    {
        return floatval(number_format($value, 2, '.', ''));
    }
}

if (!function_exists('convertNumbersToBangla')) {
    /**
     * @param float $number
     * @param bool $formatted
     * @param int $decimal
     * @return string
     */
    function convertNumbersToBangla(float $number, $formatted = true, $decimal = 2)
    {
        return en2bnNumber($formatted ? number_format($number, $decimal) : "$number");
    }
}

if (!function_exists('isEmailValid')) {
    /**
     * Email formatting check.
     *
     * @param  $email
     * @return bool
     */
    function isEmailValid($email)
    {
        $regex = '/^[_a-z0-9-+]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/';
        return preg_match($regex, $email);
    }
}

if (!function_exists('isEmailValidWithCustomExtLength')) {
    /**
     * Email formatting check.
     *
     * @param  $email
     * @param $min_length
     * @param $max_length
     * @return bool
     */
    function isEmailValidWithCustomExtLength($email, $min_length, $max_length)
    {
        $regex = '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{'. $min_length. ','. $max_length .'})$/';
        return preg_match($regex, $email);
    }
}
