<?php

namespace Sheba\MerchantEnrollment;

use Sheba\Dal\PgwStore\Contract as PgwStoreRepository;
use Sheba\Dal\PgwStore\Model as PgwStore;
use Sheba\MerchantEnrollment\Exceptions\InvalidMEFFormCategoryCodeException;
use Sheba\MerchantEnrollment\PaymentMethod\PaymentMethodFactory;
use Sheba\MerchantEnrollment\Statics\MEFGeneralStatics;
use Sheba\PaymentLink\PaymentLinkStatics;
use Sheba\ResellerPayment\Exceptions\InvalidKeyException;

class MerchantEnrollment
{
    private $partner;
    /*** @var PgwStore */
    private $payment_gateway;
    private $key;
    private $post_data;
    private $category_code;

    /**
     * @param mixed $partner
     * @return MerchantEnrollment
     */
    public function setPartner($partner): MerchantEnrollment
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * @param mixed $key
     * @return MerchantEnrollment
     */
    public function setKey($key): MerchantEnrollment
    {
        $this->key = $key;
        $this->setPaymentGatewayAccount();
        return $this;
    }

    /**
     * @param mixed $post_data
     * @return MerchantEnrollment
     */
    public function setPostData($post_data): MerchantEnrollment
    {
        $this->post_data = $post_data;
        return $this;
    }

    /**
     * @param null $payment_gateway
     * @return MerchantEnrollment
     */
    public function setPaymentGatewayAccount($payment_gateway = null): MerchantEnrollment
    {
        if (!($payment_gateway instanceof PgwStoreRepository)) {
            $store = app()->make(PgwStoreRepository::class);
            $payment_gateway = $store->where('key', $this->key)->first();
        }
        $this->payment_gateway = $payment_gateway;
        return $this;
    }

    /**
     * @return PaymentMethod\PaymentMethod
     * @throws InvalidKeyException
     */
    private function getPaymentMethod(): PaymentMethod\PaymentMethod
    {
        return (new PaymentMethodFactory())->setPartner($this->partner)->setPaymentGateway($this->payment_gateway)->get();
    }

    /**
     * @param mixed $category_code
     * @return MerchantEnrollment
     */
    public function setCategoryCode($category_code): MerchantEnrollment
    {
        $this->category_code = $category_code;
        return $this;
    }

    /**
     * @param $category_code
     * @return array
     * @throws Exceptions\InvalidMEFFormCategoryCodeException
     * @throws InvalidKeyException
     */
    public function getCategoryDetails($category_code): array
    {
        $payment_method = $this->getPaymentMethod();
        $category = (new MEFFormCategoryFactory())->setPaymentGateway($this->payment_gateway)->setPartner($this->partner)->getCategoryByCode($category_code);
        return $payment_method->categoryDetails($category)->toArray();
    }

    /**
     * @param $category_code
     * @return void
     * @throws Exceptions\InvalidCategoryPostDataException
     * @throws Exceptions\InvalidMEFFormCategoryCodeException
     * @throws InvalidKeyException
     */
    public function postCategoryDetails($category_code)
    {
        $payment_method = $this->getPaymentMethod();
        $category = (new MEFFormCategoryFactory())->setPaymentGateway($this->payment_gateway)->setPartner($this->partner)->getCategoryByCode($category_code);
        $payment_method->validateCategoryDetail($category, $this->post_data)->postCategoryDetail($category, $this->post_data);
    }

    /**
     * @return PaymentMethod\PaymentMethodCompletion
     * @throws InvalidKeyException
     */
    public function getCompletion(): PaymentMethod\PaymentMethodCompletion
    {
        $payment_method = $this->getPaymentMethod();
        return $payment_method->completion();
    }

    /**
     * @param $file
     * @param $key
     * @return $this
     * @throws InvalidMEFFormCategoryCodeException
     */
    public function uploadDocument($file, $key): MerchantEnrollment
    {
        $form_field = (new MEFFormCategoryFactory())->setPaymentGateway($this->payment_gateway)->getFormField($this->category_code, $key);
        $url = (new MerchantEnrollmentFileHandler())->setPartner($this->partner)->uploadDocument($file, $form_field)->getUploadedUrl();
        $this->setPostData(json_encode([$key => $url]));
        return $this;
    }

    /**
     * @return array
     */
    public function getRequiredDocuments(): array
    {
        return [
            'required_documents' => MEFGeneralStatics::required_documents()[$this->key],
            'terms_and_condition' => PaymentLinkStatics::paymentTermsAndConditionWebview($this->key)
        ];
//        $payment_method = $this->getPaymentMethod();
//        return $payment_method->requiredDocuments();
    }

    /**
     * @return mixed
     * @throws InvalidKeyException
     */
    public function apply()
    {
        $payment_method = $this->getPaymentMethod();
        return $payment_method->applicationApply();
    }

    /**
     * @return mixed
     * @throws InvalidKeyException
     */
    public function getDocumentService()
    {
        $payment_method = $this->getPaymentMethod();
        return $payment_method->documentServices($this->key);
    }

    public function dropDownList($type_id): array
    {
        return MEFGeneralStatics::types($type_id);
    }
}
