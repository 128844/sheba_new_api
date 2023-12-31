<?php namespace Sheba\Pos\Setting;

use Sheba\Pos\Repositories\PosSettingRepository;

class Creator
{
    /** @var PosSettingRepository $settingRepo */
    private $settingRepo;
    /** @var array $data */
    private $data;

    public function __construct(PosSettingRepository $setting_repo)
    {
        $this->settingRepo = $setting_repo;
    }

    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    public function create()
    {
        $setting_data = ['partner_id' => $this->data['partner_id']];
        return $this->settingRepo->save($setting_data);
    }
}