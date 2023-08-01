<?php namespace Sheba\Partner\HomePageSettingV3;

class DefaultSettingV3
{
    public static function get()
    {
        $pos = [
            "key" => "pos",
            "name_en" => "Sales Point",
            "name_bn" => "বেচা-বিক্রি",
            "is_on_homepage" => 1
        ];
        $pos_due = [
            "key" => "pos_due",
            "name_en" => "Due Tracker",
            "name_bn" => "বাকির খাতা",
            "is_on_homepage" => 1
        ];
        $payment_link = [
            "key" => "payment_link",
            "name_en" => "Digital Collection",
            "name_bn" => "ডিজিটাল কালেকশন",
            "is_on_homepage" => 1
        ];
        $online_sheba = [
            "key" => "online_sheba",
            "name_en" => "Online Store",
            "name_bn" => "অনলাইন স্টোর",
            "is_on_homepage" => 1
        ];

        $extra_income = [
            "key" => "extra_income",
            "name_en" => "Extra Income",
            "name_bn" => "বাড়তি আয়",
            "is_on_homepage" => 0
        ];

        $earnings = [
            "key" => "earnings",
            "name_en" => "Earnings",
            "name_bn" => "ড্যাশবোর্ড",
            "is_on_homepage" => 1
        ];

        $pos_history = [
            "key" => "pos_history",
            "name_en" => "Pos History",
            "name_bn" => "বিক্রির খাতা",
            "is_on_homepage" => 0
        ];

        $customer_list = [
            "key" => "customer_list",
            "name_en" => "Contact List",
            "name_bn" => "কন্টাক্ট লিস্ট",
            "is_on_homepage" => 0
        ];

        $marketing = [
            "key" => "marketing",
            "name_en" => "Marketing & Promo",
            "name_bn" => "মার্কেটিং ও প্রোমো",
            "is_on_homepage" => 0
        ];

        $report = [
            "key" => "report",
            "name_en" => "Report",
            "name_bn" => "রিপোর্ট",
            "is_on_homepage" => 0
        ];

        $stock = [
            "key" => "stock",
            "name_en" => "Stock",
            "name_bn" => "স্টক",
            "is_on_homepage" => 0
        ];

        $e_shop = [
            "key" => "e-shop",
            "name_en" => "E-Shop",
            "name_bn" => "পাইকারি বাজার",
            "is_on_homepage" => 0
        ];

        $expense = [
            "key" => "expense",
            "name_en" => "Expense Track",
            "name_bn" => "হিসাব খাতা",
            "is_on_homepage" => 0
        ];

        $emi= [
            "key" => "emi",
            "name_en" => "EMI",
            "name_bn" => "কিস্তি",
            "is_on_homepage" => 1
        ];

        $digital_banking= [
            "key" => "digital_banking",
            "name_en" => "Digital Banking",
            "name_bn" => "ব্যাংক একাউন্ট",
            "is_on_homepage" => 0
        ];

        $topup = [
            "key" => "topup",
            "name_en" => "Top Up",
            "name_bn" => "টপ-আপ",
            "is_on_homepage" => 1
        ];
        return [$pos, $pos_due, $payment_link, $online_sheba, $extra_income, $earnings, $pos_history, $customer_list, $marketing, $report, $stock, $e_shop, $expense, $emi,$topup, $digital_banking];
    }
    public static function getLastUpdatedAt()
    {
        return '2021-10-12';
    }
}