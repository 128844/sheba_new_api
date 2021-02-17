<?php namespace Sheba\EMI;


class Banks
{
    public static function get($icons_folder = null)
    {
        $icons_folder = $icons_folder ?: getEmiBankIconsFolder(true);
        return [
            [
                "name"     => "Midland Bank Ltd",
                "logo"     => $icons_folder . "midland_bank.png",
                "asset"    => "midland_bank",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => "N/A",
                "emi 36 months %" => "N/A"

            ],
            [
                "name"  => "SBAC Bank",
                "logo"  => $icons_folder . "sbac_bank.jpg",
                "asset" => "sbac_bank",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => "N/A",
                "emi 36 months %" => "N/A"
            ],
            [
                "name"  => "Meghna Bank Limited",
                "logo"  => $icons_folder . "meghna_bank.png",
                "asset" => "meghna_bank",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => "N/A",
                "emi 36 months %" => "N/A"
            ],
            [
                "name"  => "NRB Bank Limited",
                "logo"  => $icons_folder . "nrb_bank.png",
                "asset" => "nrb_bank",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => "N/A",
                "emi 36 months %" => "N/A"
            ],
            [
                "name"  => "STANDARD CHARTERED BANK",
                "logo"  => $icons_folder . "standard_chartered.png",
                "asset" => "standard_chartered",
                "emi 3 months %" => 3.50,
                "emi 6 months %" => 5.50,
                "emi 9 months %" => 8.00,
                "emi 12 months %" => 10.50,
                "emi 18 months %" => 13.50,
                "emi 24 months %" => 17.50,
                "emi 30 months %" => "N/A",
                "emi 36 months %" => 22.5
            ],
            [
                "name"  => "STANDARD BANK",
                "logo"  => $icons_folder . "standard_bank.png",
                "asset" => "standard_bank",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => "N/A",
                "emi 36 months %" => "N/A"
            ],
            [
                "name"  => "SOUTHEAST BANK",
                "logo"  => $icons_folder . "sebl_bank.png",
                "asset" => "sebl_bank",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => 16.50,
                "emi 36 months %" => 19.50
            ],
            [
                "name"  => "NCC BANK",
                "logo"  => $icons_folder . "ncc_bank.png",
                "asset" => "ncc_bank",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => "N/A",
                "emi 36 months %" => 19.50
            ],
            [
                "name"  => "MUTUAL TRUST BANK",
                "logo"  => $icons_folder . "mtb_bank.png",
                "asset" => "mtb_bank",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => "N/A",
                "emi 36 months %" => 19.50
            ],
            [
                "name"  => "JAMUNA BANK",
                "logo"  => $icons_folder . "jamuna_bank.png",
                "asset" => "jamuna_bank",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => "N/A",
                "emi 36 months %" => 19.50
            ],
            [
                "name"  => "EASTERN BANK",
                "logo"  => $icons_folder . "ebl.png",
                "asset" => "ebl",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => 16.50,
                "emi 36 months %" => 19.50
            ],
            [
                "name"  => "DUTCH BANGLA BANK",
                "logo"  => $icons_folder . "dbbl_bank.png",
                "asset" => "dbbl_bank",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => "N/A",
                "emi 36 months %" => 19.50
            ],
            [
                "name"  => "DHAKA BANK LIMITED",
                "logo"  => $icons_folder . "dhaka_bank.png",
                "asset" => "dhaka_bank",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => "N/A",
                "emi 24 months %" => "N/A",
                "emi 30 months %" => "N/A",
                "emi 36 months %" => "N/A"
            ],
            [
                "name"  => "CITY BANK LIMITED",
                "logo"  => $icons_folder . "city_bank.png",
                "asset" => "city_bank",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => 16.50,
                "emi 36 months %" => 19.50
            ],
            [
                "name"  => "BRAC BANK LIMITED",
                "logo"  => $icons_folder . "brac_bank.png",
                "asset" => "brac_bank",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => "N/A",
                "emi 36 months %" => "N/A"
            ],
            [
                "name"  => "BANK ASIA LIMITED",
                "logo"  => $icons_folder . "bank_asia.png",
                "asset" => "bank_asia",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => "N/A",
                "emi 24 months %" => "N/A",
                "emi 30 months %" => "N/A",
                "emi 36 months %" => "N/A"
            ],
            [
                "name"  => "LankaBangla Finance Limited",
                "logo"  => "",
                "asset" => "",
                "emi 3 months %" => "N/A",
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => "N/A",
                "emi 36 months %" => 19.50

            ],
            [
                "name"  => "NRB Commercial Bank",
                "logo"  => "",
                "asset" => "",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => 11.50,
                "emi 24 months %" => 15.50,
                "emi 30 months %" => "N/A",
                "emi 36 months %" => 19.50
            ],
            [
                "name"  => "Shahjalal Islami Bank Limited (SJIBL)",
                "logo"  => "",
                "asset" => "",
                "emi 3 months %" => 3.00,
                "emi 6 months %" => 4.50,
                "emi 9 months %" => 6.50,
                "emi 12 months %" => 8.50,
                "emi 18 months %" => "N/A",
                "emi 24 months %" => "N/A",
                "emi 30 months %" => "N/A",
                "emi 36 months %" => "N/A"
            ],
        ];
    }
}
