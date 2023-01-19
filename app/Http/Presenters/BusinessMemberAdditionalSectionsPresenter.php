<?php

namespace App\Http\Presenters;

use Illuminate\Support\Collection;
use Sheba\Dal\BusinessMemberAdditionalSection\BusinessMemberAdditionalSection as Section;
use App\Http\Presenters\BusinessMemberAdditionalFieldsPresenter as FieldsPresenter;

class BusinessMemberAdditionalSectionsPresenter extends Presenter
{
    private $sections;

    public function __construct(Collection $sections)
    {
        $this->sections = $sections;
    }

    public function toArray()
    {
        return $this->sections->map(function (Section $section) {
            $data = [
                'id' => $section->id,
                'key' => $section->name,
                'label' => $section->label
            ];
            if (property_exists($section, 'fieldsWithData')) {
                $data['fields'] = (new FieldsPresenter($section->fieldsWithData))->toArray();
            }
            return $data;
        })->toArray();
    }
}
