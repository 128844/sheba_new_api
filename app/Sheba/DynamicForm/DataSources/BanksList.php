<?php

namespace App\Sheba\DynamicForm\DataSources;

use Sheba\Helpers\ConstGetter;

class BanksList
{
    use ConstGetter;

    const ab_bank_limited = "AB Bank Limited";
    const agrani_bank_bangladesh = "Agrani Bank Bangladesh";
    const al_arafah_islami_bank_ltd = "Al-Arafah Islami Bank Ltd";
    const ansar_vdp_unnayan_bank = "Ansar-VDP Unnayan Bank";
    const bangladesh_commerce_bank_ltd = "Bangladesh Commerce Bank Ltd";
    const bangladesh_development_bank_limited = "Bangladesh Development Bank Limited";
    const bangladesh_krishi_bank = "Bangladesh Krishi Bank";
    const bank_alfalah_limited = "Bank Alfalah Limited";
    const bank_asia_limited = "Bank Asia Limited";
    const basic_bank_bangladesh = "BASIC Bank Bangladesh";
    const brac_bank_limited = "BRAC Bank Limited";
    const citibank = "Citibank NA";
    const commercial_bank_of_ceylon_ltd = "Commercial Bank of Ceylon Ltd";
    const dhaka_bank_limited = "Dhaka Bank Limited";
    const dutch_bangla_bank_limited = "Dutch Bangla Bank Limited";
    const eastern_bank_limited = "Eastern Bank Limited";
    const exim_bank_bangladesh = "Exim Bank Bangladesh";
    const first_security_islami_bank_limited = "First Security Islami Bank Limited";
    const habib_bank_ltd = "Habib Bank Ltd";
    const hsbc_bank_in_bangladesh = "HSBC Bank in Bangladesh";
    const icb_islamic_bank_limited = "ICB Islamic Bank Limited";
    const ific_bank_limited = "IFIC Bank Limited";
    const islami_bank_bangladesh = "Islami Bank Bangladesh";
    const jamuna_bank_limited = "Jamuna Bank Limited";
    const meghna_bank_limited = "Meghna Bank Limited";
    const mercantile_bank_bangladesh = "Mercantile Bank Bangladesh";
    const midland_bank_limited = "Midland Bank Limited";
    const modhumoti_bank_limited = "Modhumoti Bank Limited";
    const mutual_trust_bank_limited = "Mutual Trust Bank Limited";
    const national_bank_limited = "National Bank Limited";
    const national_bank_of_pakistan = "National Bank of Pakistan";
    const ncc_bank_limited = "NCC Bank Limited";
    const nrb_bank_limited = "NRB Bank Limited";
    const nrb_commercial_bank_limited = "NRB Commercial Bank Limited";
    const nrb_global_bank_limited = "NRB Global Bank Limited";
    const one_bank_bangladesh = "One Bank Bangladesh";
    const palli_sanchay_bank = "Palli Sanchay Bank";
    const prime_bank_limited = "Prime Bank Limited";
    const pubali_bank_bangladesh = "Pubali Bank Bangladesh";
    const rajshahi_krishi_unnayan_bank = "Rajshahi Krishi Unnayan Bank (RAKUB)";
    const rajshahi_krishi_unnayan_bank_bangladesh = "Rajshahi Krishi Unnayan Bank Bangladesh";
    const rupali_bank_bangladesh = "Rupali Bank Bangladesh";
    const sbac_bank_ltd = "SBAC Bank Ltd";
    const shahjalal_islami_bank_limited = "Shahjalal Islami Bank Limited";
    const shilpa_bank_bangladesh = "Shilpa Bank Bangladesh";
    const shimanto_bank = "Shimanto bank";
    const social_investment_bank_bangladesh = "Social Investment Bank Bangladesh";
    const social_islami_bank_ltd = "Social Islami Bank Ltd";
    const southeast_bank_ltd = "Southeast Bank Ltd";
    const standard_bank_ltd = "Standard Bank Ltd";
    const standard_chartered_bank_in_bangladesh = "Standard Chartered Bank in Bangladesh";
    const state_bank_of_india_in_bangladesh_sbi = "State Bank of India in Bangladesh SBI";
    const the_city_bank_limited = "The City Bank Limited";
    const the_farmers_bank_limited = "The Farmers Bank Limited";
    const the_krishi_bank_bangladesh = "The Krishi Bank Bangladesh";
    const the_premier_bank_bangladesh = "The Premier Bank Bangladesh";
    const the_trust_bank_bangladesh = "The Trust Bank Bangladesh";
    const union_bank_ltd = "Union Bank Ltd";
    const united_commercial_bank_limited = "United Commercial Bank Limited";
    const united_commercial_ucbl_bank_bangladesh = "United Commercial UCBL Bank Bangladesh";
    const uttara_bank_bangladesh = "Uttara Bank Bangladesh";
    const woori_bank_bangladesh = "Woori Bank Bangladesh";

    public $bankName;

    public function __construct()
    {
        $this->bankName = $this->getWithKeys();
    }
}