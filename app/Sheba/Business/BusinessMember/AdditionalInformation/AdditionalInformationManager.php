<?php

namespace Sheba\Business\BusinessMember\AdditionalInformation;

use App\Models\Business;
use App\Models\BusinessMember;
use Sheba\Dal\BusinessMemberAdditionalSection\BusinessMemberAdditionalSectionRepository;
use Sheba\Dal\BusinessMemberAdditionalField\BusinessMemberAdditionalFieldRepository;
use Sheba\Dal\BusinessMemberAdditionalSection\BusinessMemberAdditionalSection as Section;

class AdditionalInformationManager
{
    /** @var BusinessMemberAdditionalSectionRepository $sectionRepo */
    private $sectionRepo;

    /** @var BusinessMemberAdditionalFieldRepository $fieldRepo */
    private $fieldRepo;

    /** @var DataUpdater $dataUpdater */
    private $dataUpdater;

    /** @var FieldUpdater $fieldUpdater */
    private $fieldUpdater;

    public function __construct(BusinessMemberAdditionalSectionRepository $sectionRepo, BusinessMemberAdditionalFieldRepository $fieldRepo, DataUpdater $dataUpdater, FieldUpdater $fieldUpdater)
    {
        $this->sectionRepo = $sectionRepo;
        $this->fieldRepo = $fieldRepo;
        $this->dataUpdater = $dataUpdater;
        $this->fieldUpdater = $fieldUpdater;
    }

    public function getAdditionalSectionFieldsOfBusiness(Business $business)
    {
        $section = $this->getFirstSectionOfBusiness($business);
        if (!$section) return collect([]);

        return $this->fieldRepo->getBySection($section);
    }

    private function getFirstSectionOfBusiness(Business $business)
    {
        $availableSection = $this->sectionRepo->getByBusiness($business);
        if (count($availableSection) == 0) return null;
        return $availableSection[0];
    }

    private function getOrCreateAdditionalSectionOfBusiness(Business $business)
    {
        $section = $this->getFirstSectionOfBusiness($business);
        if ($section) return $section;

        return $this->sectionRepo->saveForBusiness($business, [
            "name" => "additional",
            "label" => "Additional Information"
        ]);
    }

    public function upsertAdditionalSectionOfBusiness(Business $business, $data)
    {
        $section = $this->getOrCreateAdditionalSectionOfBusiness($business);
        $this->fieldUpdater->upsert($section, $data);
    }

    public function getFieldsForBusinessMember(Section $section, BusinessMember $businessMember)
    {
        return $this->fieldRepo->getBySectionWithValuesOfBusinessMember($section, $businessMember);
    }

    public function updateDataForBusinessMember(Section $section, BusinessMember $businessMember, $data)
    {
        $this->dataUpdater->update($section, $businessMember, $data);
    }
}
