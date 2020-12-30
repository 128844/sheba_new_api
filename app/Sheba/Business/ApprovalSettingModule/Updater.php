<?php namespace Sheba\Business\ApprovalSettingModule;


use Sheba\Dal\ApprovalSetting\ApprovalSetting;
use Sheba\Dal\ApprovalSettingModule\ApprovalSettingModuleRepository;

class Updater
{
    /**
     * @var ApprovalSetting
     */
    private $approvalSetting;
    private $approvalSettingModuleData = [];
    /**
     * @var ModuleRequester
     */
    private $moduleRequester;

    /**
     * @var ApprovalSettingModuleRepository
     */
    private $approvalSettingModuleRepo;

    public function __construct(ApprovalSettingModuleRepository $approval_setting_module_repo)
    {
        $this->approvalSettingModuleRepo = $approval_setting_module_repo;
    }

    public function setApprovalSetting(ApprovalSetting $approval_setting)
    {
        $this->approvalSetting = $approval_setting;
        return $this;
    }

    public function setModuleRequester(ModuleRequester $module_requester)
    {
        $this->moduleRequester = $module_requester;
        return $this;
    }

    public function update()
    {
        $this->makeData();
        foreach ($this->approvalSettingModuleData as $approval_setting_module) {
            $this->approvalSettingModuleRepo->update($module, $approval_setting_module);
        }
        return $this;
    }

    private function makeData()
    {
        foreach ($this->moduleRequester->getModules() as $module) {
            $this->approvalSettingModuleData[] = [
                'approval_setting_id' => $this->approvalSetting->id, 'modules' => $module,
            ];
        }
    }

}
