<?php

use Sheba\Helpers\Converters\BanglaToEnglish;

if (!function_exists('randomString')) {
    /**
     * @param $len
     * @param int $num
     * @param int $alpha
     * @param int $spec_char
     * @return string
     * @throws Exception
     */
    function randomString($len, $num = 0, $alpha = 0, $spec_char = 0)
    {
        $alphabets = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $numbers = "0123456789";
        $special_characters = "!@#$%^&*()_-+=}{][|:;.,/?";

        $characters = "";
        if ($num) $characters .= $numbers;
        if ($alpha) $characters .= $alphabets;
        if ($spec_char) $characters .= $special_characters;
        if (!$num && !$alpha && !$spec_char) $characters .= $numbers . $alphabets . $special_characters;

        $rand_string = '';
        for ($i = 0; $i < $len; $i++) {
            $character = $characters[random_int(0, strlen($characters) - 1)];
            if ($i == 0 && $character === "0") $character = '1';
            $rand_string .= $character;
        }

        return $rand_string;
    }
}

if (!function_exists('clean')) {
    /**
     * @param $string
     * @param string $separator
     * @param array $keep
     * @return string|string[]|null
     */
    function clean($string, $separator = "-", $keep = [])
    {
        $string    = str_replace(' ', $separator, $string); // Replaces all spaces with hyphens.
        $keep_only = "/[^A-Za-z0-9";
        foreach ($keep as $item) {
            $keep_only .= "$item";
        }
        $keep_only .= (($separator == '-') ? '\-' : "_");
        $keep_only .= "]/";
        $string    = preg_replace($keep_only, '', $string);           // Removes special chars.
        return preg_replace("/$separator+/", $separator, $string);    // Replaces multiple hyphens with single one.
    }
}

if (!function_exists('pamelCase')) {
    /**
     * @param $string
     * @return string
     */
    function pamelCase($string)
    {
        return ucfirst(camel_case($string));
    }
}

if (!function_exists('scramble_string')) {
    /**
     * Returns scrambled string replaced by '*'
     *
     *
     * @param $str
     * @param int $scramble_ratio = The ratio (in percentage) by which the visible portion of the string is shown
     * @return String
     */
    function scramble_string($str, $scramble_ratio = 15)
    {
        $str                     = BanglaToEnglish::convert($str);
        $str                     = preg_replace('/[\x00-\x1F\x7F]/u', '', $str);
        $len                     = strlen($str);
        $number_of_words_visible = (int)ceil(($scramble_ratio * $len) / 100);
        $number_of_words_hidden  = $len - ($number_of_words_visible * 2);
        $number_of_words_hidden  = $number_of_words_hidden > 0 ? $number_of_words_hidden : 0;
        return substr($str, 0, $number_of_words_visible) . str_repeat('*', $number_of_words_hidden) . substr($str, $len - $number_of_words_visible, $len);
    }
}

if (!function_exists('scramble_string_by_count')) {
    /**
     * Returns scrambled string replaced by '*'
     *
     *
     * @param $str
     * @param int $from_start how many characters to leave visible from start
     * @param int $from_end how many characters to leave visible from end
     * @return String
     */
    function scramble_string_by_count($str, $from_start = 5, $from_end=2)
    {
        $str                     = BanglaToEnglish::convert($str);
        $str                     = preg_replace('/[\x00-\x1F\x7F]/u', '', $str);
        $len                     = strlen($str);
        $number_of_words_hidden  = $len - ($from_start + $from_end);
        $number_of_words_hidden  = $number_of_words_hidden > 0 ? $number_of_words_hidden : 0;
        return substr($str, 0, $from_start) . str_repeat('*', $number_of_words_hidden) . substr($str, $len - $from_end, $len);
    }
}

if (!function_exists('normalizeStringCases')) {
    /**
     * @param $value
     * @return string
     */
    function normalizeStringCases($value)
    {
        $value = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
        return ucwords(str_replace(['_','-'], ' ', $value));
    }
}

if (!function_exists('isNormalized')) {
    /**
     * @param string $value
     * @return string
     */
    function isNormalized($value)
    {
        return str_contains($value, ' ');
    }
}

if (!function_exists('strContainsAll')) {
    /**
     * @param $haystack
     * @param array $needles
     * @return bool
     */
    function strContainsAll($haystack, array $needles)
    {
        foreach ($needles as $needle) {
            if (!str_contains($haystack, $needle))
                return false;
        }
        return true;
    }
}


if (!function_exists('removeRestrictedCharacters')) {

    function removeRestrictedCharacters($string)
    {
        return str_replace(['/', '$', '#', ' ', '?', '%'], '', $string);
    }
}