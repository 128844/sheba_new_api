<?php namespace Sheba\Reward\Event\Partner\Action\Rating\Parameter;

use App\Models\Review;
use Sheba\Reward\Event\ActionEventParameter;
use Sheba\Reward\Exception\ParameterTypeMismatchException;

class Rate extends ActionEventParameter
{
    public function validate()
    {
        // TODO: Implement validate() method.
    }

    public function check(array $params)
    {
        $review = $params[0];
        if (!$review instanceof Review) {
            throw new ParameterTypeMismatchException("First parameter is must be an instance of Review");
        }

        return $review->rating >= $this->value;
    }
}