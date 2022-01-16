<?php namespace App\Sheba\ResellerPayment;

use Sheba\Dal\PgwStoreAccount\Model as PgwStoreAccount;
use Sheba\Dal\Survey\Model as Survey;
use Sheba\MerchantEnrollment\MerchantEnrollment;

class PaymentService
{
    private $partner;
    private $status;
    private $pgwStatus;
    private $key;
    private $rejectReason;

    /**
     * @param mixed $partner
     * @return PaymentService
     */
    public function setPartner($partner)
    {
        $this->partner = $partner;
        return $this;
    }


    /**
     * @param mixed $key
     */
    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }

    public function getPGWDetails()
    {
        $this->getResellerPaymentStatus();
        $this->getPgwStatus();
        return [
            'banner' =>'https://cdn-shebadev.s3.ap-south-1.amazonaws.com/reseller_payment/payment_gateway_banner/app-banner+(1)+2.png',
            'faq' => [
                'আপনার ব্যবসার প্রোফাইল সম্পন্ন করুন',
                'পেমেন্ট সার্ভিসের জন্য আবেদন করুন',
                'পেমেন্ট সার্ভিস কনফিগার করুন'
            ],
            'status' => $this->status ?? null,
            'mor_status_wise_text' => config('reseller_payment.mor_status_wise_text')[$this->key][$this->status],
            'pgw_status' =>  $this->pgwStatus ?? null,
            'pgw_inactive_text' => 'নিষ্ক্রিয় থাকা অবস্থায় SSL কমার্সের গেটওয় থেকে ডিজিটাল উপায়ে টাকা গ্রহণ করা যাবে না।',
            'how_to_use_link' => ''
        ];

    }

    public function getStatusAndBanner()
    {
        $this->getResellerPaymentStatus();
        $this->getPgwStatus();

        return [
            'status' => $this->status ?? null,
            'pgw_status' => $this->pgwStatus ?? null,
            'banner' => $this->getBanner()
        ];
    }

    private function getBanner()
    {
        if($this->pgwStatus == 0)
            $banner = config('reseller_payment.status_wise_home_banner')['pgw_inactive'];
        elseif ($this->status == 'verified')
            $banner = config('reseller_payment.status_wise_home_banner')['verified'];
       elseif($this->status == 'rejected')
           $banner = config('reseller_payment.status_wise_home_banner')['rejected'];
       elseif ($this->status == 'rejected')
      return  config('reseller_payment.status_wise_home_banner')[$this->status];
    }

    private function getResellerPaymentStatus()
    {
       $this->getMORStatus();
       if(isset($this->status))
           return;
       $this->getSurveyStatus();
       if(isset($this->status))
           return;
       $this->getEkycStatus();

    }

    private function getMORStatus()
    {
        /** @var MORServiceClient $morClient */
        $morClient = app(MORServiceClient::class);
        $morResponse = $morClient->get('client/applications/status?user_id=1'.'&user_type=partner');
        $morStatus = $morResponse['application_status'];
        if($morStatus){
            $this->status = $morStatus;
            if($morStatus == 'rejected')
                $this->rejectReason = $morResponse['reject_reason'];
            return true;
        }

       return $this->checkMefCompletion();

    }

    private function checkMefCompletion()
    {
        /** @var MerchantEnrollment $merchantEnrollment */
        $merchantEnrollment = app(MerchantEnrollment::class);
        $completion = $merchantEnrollment->setPartner($this->partner)->setKey($this->key)->getCompletion()->toArray();
        if($completion['can_apply'] == 1)
            $this->status = 'mef_completed';
       return true;
    }

    private function getSurveyStatus()
    {
        $survey =  Survey::where('user_type',get_class($this->partner))->where('user_id', $this->partner->id)->first();
        if($survey)
            $this->status = 'survey_completed';
    }

    private function getEkycStatus()
    {
        if($this->partner->isNIDVerified())
            $this->status = 'ekyc_completed';
    }

    private function getPgwStatus()
    {
        $pgw_store_accounts = PgwStoreAccount::where('user_type',get_class($this->partner))->where('user_id', $this->partner->id)->first();
        if($pgw_store_accounts)
            $this->pgwStatus = $pgw_store_accounts->staus;
    }

}