<?php namespace Sheba\PartnerList;


class Director
{
    /** @var Builder */
    private $builder;

    public function setBuilder(Builder $builder)
    {
        $this->builder = $builder;
        return $this;
    }

    public function buildPartnerList()
    {
        $this->buildBaseQuery();
        $this->builder->runQuery();
        $this->filterBaseConditions();
    }

    public function buildPartnerListForOrderPlacement()
    {
        $this->buildQueryForOrderPlace();
        $this->builder->runQuery();
        $this->filterForOrderPlace();
    }

    private function buildBaseQuery()
    {
        $this->builder->checkCategory();
        $this->builder->checkService();
        $this->builder->checkLeave();
        $this->builder->checkPartnerVerification();
        $this->builder->checkPartner();
        $this->builder->checkCanAccessMarketPlace();
        $this->builder->withResource();
        $this->builder->withAvgReview();
    }

    private function filterBaseConditions()
    {
        $this->builder->checkOption();
        $this->builder->checkGeoWithinPartnerRadius();
        $this->builder->checkPartnerCreditLimit();
        $this->builder->checkPartnerDailyOrderLimit();
        $this->builder->checkPartnerHasResource();
        $this->builder->removeShebaHelpDesk();
    }

    private function buildQueryForOrderPlace()
    {
        $this->buildBaseQuery();
        $this->builder->withService();
        $this->builder->withTotalCompletedOrder();
    }

    private function filterForOrderPlace()
    {
        $this->filterBaseConditions();
        $this->builder->checkPartnerAvailability();
        $this->builder->removeUnavailablePartners();
    }

}
