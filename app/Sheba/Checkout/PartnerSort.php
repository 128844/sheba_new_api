<?php

namespace Sheba\Checkout;


class PartnerSort
{
    private $partners;
    private $goldPartners;
    private $goldPartnerCount;
    private $silverPartners;
    private $silverPartnerCount;
    private $bronzePartners;
    private $bronzePartnerCount;
    private $sortedPartners;
    private $weights;

    public function __construct($partners)
    {
        $this->sortedPartners = collect();
        $this->partners = $partners;
        $this->filterPartnersByPackage();
        $this->weights = config('sheba.weight_on_partner_list');
        $this->goldPartnerCount = config('sheba.partner_packages_on_partner_list')['ESP'];
        $this->silverPartnerCount = config('sheba.partner_packages_on_partner_list')['PSP'];
        $this->bronzePartnerCount = config('sheba.partner_packages_on_partner_list')['LSP'];
        $this->setPartners();
    }

    private function setPartners()
    {
        $remaining = 0;
        if ($this->goldPartners->count() < $this->goldPartnerCount) {
            $remaining = $this->goldPartnerCount - $this->goldPartners->count();
            $this->goldPartnerCount = $this->goldPartners->count();
            $this->silverPartnerCount += $remaining;
        }
        if ($this->silverPartners->count() < $this->silverPartnerCount) {
            $remaining = $this->silverPartnerCount - $this->silverPartners->count();
            $this->silverPartnerCount = $this->silverPartners->count();
            $this->bronzePartnerCount += $remaining;
        }
        if ($this->bronzePartners->count() < $this->bronzePartnerCount) {
            $this->bronzePartnerCount = $this->bronzePartners->count();
        }
    }

    public function get()
    {
        $this->goldPartners = $this->calculateTotalWeight($this->goldPartners)->splice(0, $this->goldPartnerCount);
        $this->silverPartners = $this->calculateTotalWeight($this->silverPartners)->splice(0, $this->silverPartnerCount);
        $this->bronzePartners = $this->calculateTotalWeight($this->bronzePartners)->splice(0, $this->bronzePartnerCount);
        return $this->sortedPartners->merge($this->goldPartners)->merge($this->silverPartners)->merge($this->bronzePartners);
    }

    private function calculateTotalWeight($partners)
    {
        $min_total_experts = $partners->min('total_experts');
        $max_total_experts = $partners->max('total_experts');

        $min_orders = $partners->min('total_completed_orders');
        $max_orders = $partners->max('total_completed_orders');

        $min_price = $partners->min('discounted_price');
        $max_price = $partners->max('discounted_price');

        foreach ($partners as $partner) {
            $avg_rating = $partner->avg_rating > 0 ? $this->weights['rating'] * (($partner->avg_rating - 1) / (5 - 1)) : 0;
            $total_experts = $partner->total_experts > 0 ? $this->weights['capacity'] * (($partner->total_experts - $min_total_experts) / ($max_total_experts - $min_total_experts)) : 0;
            $orders = $partner->total_completed_orders > 0 ? $this->weights['orders'] * (($partner->total_completed_orders - $min_orders) / ($max_orders - $min_orders)) : 0;
            $price = 1 - ($this->weights['price'] * (($partner->discounted_price - $min_price) / ($max_price - $min_price)));
            $partner['score'] = $price + $avg_rating + $orders + $total_experts;
        }
        return $partners->sortByDesc('score');
    }

    private function filterPartnersByPackage()
    {
        $group_by_packages = $this->partners->groupBy('package_id');
        $this->goldPartners = isset($group_by_packages[config('sheba.partner_packages')['ESP']]) ? $group_by_packages[config('sheba.partner_packages')['ESP']] : collect();;
        $this->silverPartners = isset($group_by_packages[config('sheba.partner_packages')['PSP']]) ? $group_by_packages[config('sheba.partner_packages')['PSP']] : collect();
        $this->bronzePartners = isset($group_by_packages[config('sheba.partner_packages')['LSP']]) ? $group_by_packages[config('sheba.partner_packages')['LSP']] : collect();
    }

}