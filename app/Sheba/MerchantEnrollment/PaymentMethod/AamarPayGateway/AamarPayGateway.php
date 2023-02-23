<?php

namespace Sheba\MerchantEnrollment\PaymentMethod\AamarPayGateway;

use App\Exceptions\NotFoundAndDoNotReportException;
use App\Sheba\DynamicForm\CompletionCalculation;
use App\Sheba\DynamicForm\DynamicForm;
use App\Sheba\MerchantEnrollment\PaymentMethod\ApplicationSubmit;
use App\Sheba\ResellerPayment\Exceptions\MORServiceServerError;
use Sheba\Dal\MefForm\Model as MefForm;
use Sheba\MerchantEnrollment\Exceptions\IncompleteSubmitData;
use Sheba\MerchantEnrollment\Exceptions\InvalidListInsertionException;
use Sheba\MerchantEnrollment\MEFFormCategory\CategoryGetter;
use Sheba\MerchantEnrollment\MEFFormCategory\MEFFormCategory;
use Sheba\MerchantEnrollment\PaymentMethod\PaymentMethod;
use Sheba\MerchantEnrollment\PaymentMethod\PaymentMethodCompletion;
use Sheba\MerchantEnrollment\Statics\MEFGeneralStatics;
use Sheba\MerchantEnrollment\Statics\PaymentMethodStatics;
use Sheba\PaymentLink\PaymentLinkStatics;
use Sheba\ResellerPayment\Exceptions\InvalidKeyException;

class AamarPayGateway extends PaymentMethod
{
    const KEY = "aamarpay";

    private $form;

    private $overall_completion;

    public function categoryDetails(MEFFormCategory $category): CategoryGetter
    {
        return $category->get();
    }

    public function getStaticCategoryFormData(MEFFormCategory $category)
    {
        return $category->getFormFields();
    }

    public function completion(): PaymentMethodCompletion
    {
        $completion = [];
        $this->setForm();
        foreach ($this->form->sections as $section) {
            /** @var DynamicForm $dynamicForm */
            $dynamicForm = app()->make(DynamicForm::class);
            $dynamicForm->setSection($section->id)->setPartner($this->partner);
            $fields = $dynamicForm->getSectionFields();
            $completion[] = (new CompletionCalculation())->setFields($fields)->calculate();
        }

        $this->overallCompletionPercentage($completion);

        return (new PaymentMethodCompletion())
            ->setCanApply($this->overall_completion == 100 ? 1 : 0)
            ->setOverallCompletion($this->overall_completion);
    }

    public function overallCompletionPercentage($completion)
    {
        $sum = 0;
        foreach ($completion as $c) {
            $sum += $c;
        }

        $this->overall_completion = round(($sum / count($completion)), 2);
    }

    public function requiredDocuments(): array
    {
        return [
            'required_documents'  => MEFGeneralStatics::required_documents()['ssl'],
            'terms_and_condition' => PaymentLinkStatics::paymentTermsAndConditionWebview()
        ];
    }

    /**
     * @throws IncompleteSubmitData
     * @throws InvalidKeyException
     * @throws InvalidListInsertionException
     * @throws MORServiceServerError
     * @throws NotFoundAndDoNotReportException
     */
    public function applicationApply(): array
    {
        (new ApplicationSubmit())->setPartner($this->partner)->setPaymentGateway($this->payment_method)->submit();
        return PaymentMethodStatics::APPLY_SUCCESS_MESSAGE;
    }

    /**
     * @param $paymentGatewayKey
     * @return mixed
     * @throws InvalidKeyException
     */
    public function documentServices($paymentGatewayKey)
    {
        return $this->documentServiceList($paymentGatewayKey);
    }

    /**
     * @return AamarPayGateway
     */
    public function setForm(): AamarPayGateway
    {
        $this->form = MefForm::where('key', self::KEY)->first();
        return $this;
    }
}