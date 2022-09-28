<?php

namespace App\Sheba\DynamicForm;

use Illuminate\Contracts\Support\Arrayable;

class SectionListResponse implements Arrayable
{
    private $categories;
    private $can_apply;
    private $overall_completion;
    private $message = '';
    private $partner;

    public function setPartner($partner): SectionListResponse
    {
        $this->partner = $partner;
        return $this;
    }

    private function overAllCompletion()
    {
        $categories_length = count($this->categories);
        $nid_status = $this->partner->getFirstAdminResource()->profile->nid_verified ?? 0;

        if ($nid_status) {
            return $this->overall_completion;
        }

        if ($this->overall_completion['en'] == 100.0) {
            $completion = ($this->overall_completion['en'] / $categories_length) * ($categories_length - 1);
            return [
                "en" => $completion,
                "bn" => convertNumbersToBangla($completion, false)
            ];

        }
        return $this->overall_completion;
    }

    public function toArray(): array
    {
        $completetion = $this->overAllCompletion();
        return [
            "categories" => $this->categories,
            "can_apply" => $this->can_apply,
            "overall_completion" => $completetion,
            "nid_status" => $this->partner->getFirstAdminResource()->profile->nid_verified ?? 0,
            "message" => $this->message,
        ];
    }

    /**
     * @param mixed $categories
     * @return SectionListResponse
     */
    public function setCategories($categories): SectionListResponse
    {
        $this->categories = $categories;
        return $this;
    }


    /**
     * @param mixed $completion
     * @return SectionListResponse
     */
    public function setCanApply($completion): SectionListResponse
    {
        $this->can_apply = $this->overAllCompletion()['en'] == 100.0 ? 1 : 0;
        return $this;
    }

    /**
     * @param mixed $overall_completion
     * @return SectionListResponse
     */
    public function setOverallCompletion($overall_completion): SectionListResponse
    {
        $this->overall_completion = [
            "en" => $overall_completion,
            "bn" => convertNumbersToBangla($overall_completion, false)
        ];
        return $this;
    }

    /**
     * @param $message
     * @return SectionListResponse
     */
    public function setMessage($message): SectionListResponse
    {
        $this->message = $message;
        return $this;
    }
}
