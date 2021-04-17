<?php namespace App\Http\Controllers\B2b;

use App\Sheba\Business\PayrollComponent\Components\GrossComponents\Creator;
use App\Sheba\Business\PayrollComponent\Components\GrossComponents\Updater;
use Sheba\Business\PayrollSetting\Requester as PayrollSettingRequester;
use Sheba\Business\PayrollSetting\Updater as PayrollSettingUpdater;
use Sheba\Business\PayrollComponent\Updater as PayrollComponentUpdater;
use Sheba\Business\PayrollComponent\Requester as PayrollComponentRequester;
use App\Sheba\Business\PayrollComponent\Components\Additions\Creator as AdditionCreator;
use App\Sheba\Business\PayrollComponent\Components\Deductions\Creator as DeductionsCreator;
use App\Transformers\Business\PayrollSettingsTransformer;
use Sheba\Dal\PayrollComponent\PayrollComponentRepository;
use Sheba\Dal\PayrollSetting\PayDayType;
use Sheba\Dal\PayrollSetting\PayrollSettingRepository;
use Sheba\Dal\PayrollSetting\PayrollSetting;
use App\Transformers\CustomSerializer;
use App\Http\Controllers\Controller;
use League\Fractal\Resource\Item;
use Illuminate\Http\JsonResponse;
use App\Models\BusinessMember;
use Sheba\ModificationFields;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use App\Models\Business;

class PayrollController extends Controller
{
    use ModificationFields;

    private $payrollSettingRepository;
    private $payrollSettingRequester;
    private $payrollSettingUpdater;
    private $payrollComponentUpdater;
    /*** @var PayrollComponentRequester */
    private $payrollComponentRequester;
    /*** @var PayrollComponentRepository */
    private $payrollComponentRepository;

    /**
     * PayrollController constructor.
     * @param PayrollSettingRepository $payroll_setting_repository
     * @param PayrollSettingRequester $payroll_setting_requester
     * @param PayrollSettingUpdater $payroll_setting_updater
     * @param PayrollComponentUpdater $payroll_component_updater
     * @param PayrollComponentRequester $payroll_component_requester
     * @param PayrollComponentRepository $payroll_component_repository
     */
    public function __construct(PayrollSettingRepository $payroll_setting_repository,
                                PayrollSettingRequester $payroll_setting_requester,
                                PayrollSettingUpdater $payroll_setting_updater,
                                PayrollComponentUpdater $payroll_component_updater,
                                PayrollComponentRequester $payroll_component_requester,
                                PayrollComponentRepository $payroll_component_repository)
    {
        $this->payrollSettingRepository = $payroll_setting_repository;
        $this->payrollSettingRequester = $payroll_setting_requester;
        $this->payrollSettingUpdater = $payroll_setting_updater;
        $this->payrollComponentUpdater = $payroll_component_updater;
        $this->payrollComponentRequester = $payroll_component_requester;
        $this->payrollComponentRepository = $payroll_component_repository;
    }


    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getPayrollSettings(Request $request)
    {
        /** @var Business $business */
        $business = $request->business;
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        /** @var PayrollSetting $payroll_setting */
        $payroll_setting = $business->payrollSetting;

        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $member = new Item($payroll_setting, new PayrollSettingsTransformer());
        $payroll_setting = $manager->createData($member)->toArray()['data'];

        return api_response($request, null, 200, ['payroll_setting' => $payroll_setting]);
    }

    /**
     * @param $business
     * @param $payroll_setting
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePaySchedule($business, $payroll_setting, Request $request)
    {
        $this->validate($request, [
            'is_enable' => 'required|integer',
            'pay_day_type' => 'required|in:' . implode(',', PayDayType::get()),
            'pay_day' => 'required_if:pay_day_type,fixed_date'
        ]);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        $this->setModifier($business_member->member);

        $payroll_setting = $this->payrollSettingRepository->find((int)$payroll_setting);
        if (!$payroll_setting) return api_response($request, null, 404);
        $this->payrollSettingRequester->setIsEnable($request->is_enable)->setPayDayType($request->pay_day_type)->setPayDay($request->pay_day);
        $this->payrollSettingUpdater->setPayrollSetting($payroll_setting)->setPayrollSettingRequest($this->payrollSettingRequester)->update();
        return api_response($request, null, 200);
    }

    /**
     * @param $business
     * @param $payroll_setting
     * @param Request $request
     * @return JsonResponse
     */
    public function updateSalaryBreakdown($business, $payroll_setting, Request $request)
    {
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        $this->setModifier($business_member->member);
        $payroll_setting = $this->payrollSettingRepository->find((int)$payroll_setting);
        if (!$payroll_setting) return api_response($request, null, 404);
        $this->payrollComponentUpdater->setPayrollSetting($payroll_setting)->setGrossComponents($request->gross_components)->updateGrossComponents();
        return api_response($request, null, 200);
    }

    public function addComponent($business, $payroll_setting, Request $request, AdditionCreator $addition_creator, DeductionsCreator $deduction_creator)
    {
        $this->validate($request, [
            'addition' => 'required',
            'deduction' => 'required',
        ]);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $this->setModifier($business_member->member);

        $payroll_setting = $this->payrollSettingRepository->find((int)$payroll_setting);
        if (!$payroll_setting) return api_response($request, null, 404);

        $this->payrollComponentRequester->setSetting($payroll_setting)->setAddition($request->addition)->setDeduction($request->deduction);
        if ($this->payrollComponentRequester->checkError()) return api_response($request, null, 404, ['message' => 'Duplicate components found!']);

        $addition_creator->setPayrollComponentRequester($this->payrollComponentRequester)->createOrUpdate();
        $deduction_creator->setPayrollComponentRequester($this->payrollComponentRequester)->createOrUpdate();

        return api_response($request, null, 200);
    }

    public function deleteComponent($business, $payroll_setting, $component, Request $request)
    {
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $this->setModifier($business_member->member);

        $payroll_setting = $this->payrollSettingRepository->find((int)$payroll_setting);
        if (!$payroll_setting) return api_response($request, null, 404);
        $gross_component = $this->payrollComponentRepository->find((int)$component);
        if (!$gross_component) return api_response($request, null, 404);
        if ($gross_component->is_default) return api_response($request, null, 420);
        $gross_component->delete();

        return api_response($request, null, 200);
    }

    public function grossComponentAddUpdate($business, $payroll_setting, Request $request, Creator $creator, Updater $updater)
    {
        $this->validate($request, [
            'added_data' => 'required',
            'updated_data' => 'required',
        ]);
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);

        $this->setModifier($business_member->member);

        $payroll_setting = $this->payrollSettingRepository->find((int)$payroll_setting);
        if (!$payroll_setting) return api_response($request, null, 404);

        $this->payrollComponentRequester->setSetting($payroll_setting)->setGrossComponentAdd($request->added_data)->setGrossComponentUpdate($request->updated_data);
        $creator->setPayrollComponentRequester($this->payrollComponentRequester)->create();
        $updater->setPayrollComponentRequester($this->payrollComponentRequester)->Update();

        return api_response($request, null, 200);
    }

}
