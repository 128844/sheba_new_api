<?php namespace Sheba\Survey;

use Sheba\Dal\Survey\Model as Survey;
use Sheba\Repositories\Interfaces\SurveyInterface;
use Sheba\ResellerPayment\Exceptions\InvalidKeyException;

class ResellerPaymentSurvey implements SurveyInterface
{
    private $partner;

    public function setUser($user)
    {
        $this->partner = $user;
        return $this;
    }

    public function getQuestions()
    {
        return config('survey.reseller_payment.basic_questions');
    }

    /**
     * @throws InvalidKeyException
     */
    public function storeResult($result)
    {
        $this->validateResult($result);
        $data = [
            'user_id' => $this->partner->id,
            'user_type' => get_class($this->partner),
            'key' => 'reseller_payment',
            'result' => $result,
        ];
        Survey::create($data);
    }

    /**
     * @throws InvalidKeyException
     */
    private function validateResult($result)
    {
        $result = json_decode($result);
        foreach ($result as $item) {
            if (!property_exists($item, 'question') || !property_exists($item, 'description')
                || !property_exists($item, 'option') || !property_exists($item, 'answer'))
                throw new InvalidKeyException("Incorrect Result Structure", 422);
        }
    }


}