<?php namespace App\Transformers\Business;

use App\Sheba\Business\BusinessBasicInformation;
use Carbon\Carbon;
use Sheba\Business\BusinessMemberStatusChangeLog\LogFormatter as EmployeeStatusChangeLogFormatter;
use App\Sheba\Business\CoWorker\ProfileInformation\SocialLink;
use App\Sheba\Business\PayrollComponent\Components\GrossSalaryBreakdownCalculate;
use App\Sheba\Business\SalaryLog\Formatter as SalaryLogFormatter;
use Sheba\Business\CoWorker\Statuses;
use Sheba\Dal\PayrollComponent\TargetType;
use Sheba\Dal\PayrollComponent\Type;
use Sheba\Dal\PayrollComponent\Components;
use League\Fractal\TransformerAbstract;
use App\Models\BusinessMember;
use App\Models\Business;
use App\Models\Profile;
use App\Models\Member;

class CoWorkerDetailTransformer extends TransformerAbstract
{
    use BusinessBasicInformation;

    const THRESHOLD = 17;

    /** @var Business $business */
    private $business;
    /** @var Member $member */
    private $member;
    /** @var Profile $profile */
    private $profile;


    public function transform(BusinessMember $business_member)
    {
        $this->business = $business_member->business;
        $this->member = $business_member->member;
        $this->profile = $this->member->profile;
        return [
            'basic_info' => $this->getBasicInfo($business_member),
            'official_info' => $this->getOfficialInfo($business_member),
            'personal_info' => $this->getPersonalInfo($business_member),
            'financial_info' => $this->getFinancialInfo($business_member),
            'emergency_info' => $this->getEmergencyInfo(),
            'salary_info' => $this->getSalaryInfo($business_member),
            'profile_completion' => $this->profileCompletion($business_member),
            're_invite_logs' => $this->reInviteLogs($business_member),
            'pdf_info' => $this->getPdfInfo($business_member)
        ];
    }

    private function getBasicInfo($business_member)
    {
        $role = $business_member ? $business_member->role : null;
        $department = $role ? $role->businessDepartment : null;
        $department_name = $department ? $department->name : null;
        $department_id = $department ? $department->id : null;
        $designation = $role ? $role->name : null;

        $count = 0;
        if ($this->profile->name) $count++;
        if ($this->profile->email) $count++;
        if ($department) $count++;
        if ($designation) $count++;
        $basic_info_completion = round((($count / 4) * self::THRESHOLD), 0);
        return [
            'id' => $this->member->id,
            'status' => $business_member->status,
            'profile' => [
                'id' => $this->profile->id,
                'name' => !$this->isNull($this->profile->name) ? $this->profile->name : null,
                'profile_picture_name' => $this->profile->pro_pic ? array_last(explode('/', $this->profile->pro_pic)) : null,
                'profile_picture' => $this->profile->pro_pic,
                'email' => $this->profile->email,
            ],
            'department' => $department_name,
            'department_id' => $department_id,
            'designation' => $designation,
            'manager_detail' => $business_member->manager_id ? $this->getManagerDetails($business_member->manager_id) : null,
            'basic_info_completion' => $basic_info_completion,
        ];
    }

    private function getOfficialInfo($business_member)
    {
        $count = 0;
        if ($business_member->employee_id ||
            $business_member->join_date ||
            $business_member->grade ||
            $business_member->employee_type ||
            $business_member->previous_institution) $count++;
        $official_info_completion = round((($count / 1) * self::THRESHOLD), 0);

        return [
            'employee_id' => $business_member->employee_id,
            'join_date' => $business_member->join_date,
            'grade' => $business_member->grade,
            'employee_type' => $business_member->employee_type ? [
                'key' => $business_member->employee_type,
                'title' => ucwords(str_replace('_', ' ', $business_member->employee_type))
            ] : null,
            'previous_institution' => $business_member->previous_institution,
            'official_info_completion' => $official_info_completion
        ];
    }

    private function getPersonalInfo($business_member)
    {
        $count = 0;
        if ($business_member->mobile ||
            $this->profile->dob ||
            $this->profile->address ||
            $this->profile->nationality ||
            $this->profile->nid_no ||
            $this->profile->nationality ||
            $this->profile->nid_image_front ||
            $this->profile->nid_image_back) $count++;

        $personal_info_completion = round((($count / 1) * self::THRESHOLD), 0);

        return [
            'mobile' => $business_member->mobile,
            'date_of_birth' => $this->profile->dob,
            'address' => $this->profile->address,
            'nationality' => $this->profile->nationality,
            'nid_no' => $this->profile->nid_no,
            'profile_id' => $this->profile->id,
            'nid_image_front_name' => $this->profile->nid_image_front ? array_last(explode('/', $this->profile->nid_image_front)) : null,
            'nid_image_front' => $this->profile->nid_image_front,
            'nid_image_back_name' => $this->profile->nid_image_back ? array_last(explode('/', $this->profile->nid_image_back)) : null,
            'nid_image_back' => $this->profile->nid_image_back,
            'gender' => $this->profile->gender,
            'blood_group' => $this->profile->blood_group,
            'passport_no' => $this->profile->passport_no,
            'passport_image_name' => $this->profile->passport_image ? array_last(explode('/', $this->profile->passport_image)) : null,
            'passport_image' => $this->profile->passport_image,
            'social_links' => (new SocialLink($this->member))->get(),

            'personal_info_completion' => $personal_info_completion
        ];
    }

    private function getFinancialInfo($business_member)
    {
        $bkash_info = $business_member->bkashInfos->last();
        $profile_bank_info = $this->profile->banks->last();

        $bank_name = $profile_bank_info && !$this->isNull($profile_bank_info->bank_name) ?
            ucwords(str_replace('_', ' ', $profile_bank_info->bank_name)) :
            null;
        $bank = $bank_name ? [
            'key' => snake_case($bank_name),
            'name' => $bank_name,
        ] : null;

        $account_no = $profile_bank_info ? $profile_bank_info->account_no : null;
        $count = 0;
        if ($this->profile->tin_no ||
            $this->profile->tin_certificate ||
            $bank_name ||
            $account_no) $count++;

        $financial_info_completion = round((($count / 1) * self::THRESHOLD), 0);

        return [
            'tin_no' => $this->profile->tin_no,
            'tin_certificate_name' => $this->profile->tin_certificate ? array_last(explode('/', $this->profile->tin_certificate)) : null,
            'tin_certificate' => $this->profile->tin_certificate,
            'bank' => $bank,
            'account_no' => $account_no,
            'bkash_no' => $bkash_info ? $bkash_info->account_no : null,
            'financial_info_completion' => $financial_info_completion
        ];
    }

    private function getEmergencyInfo()
    {
        $count = 0;
        if ($this->member->emergency_contract_person_name ||
            $this->member->emergency_contract_person_number ||
            $this->member->emergency_contract_person_relationship) $count++;

        $emergency_info_completion = round((($count / 1) * self::THRESHOLD), 0);

        return [
            'emergency_contract_person_name' => $this->member->emergency_contract_person_name,
            'emergency_contract_person_number' => $this->member->emergency_contract_person_number,
            'emergency_contract_person_relationship' => $this->member->emergency_contract_person_relationship,
            'emergency_info_completion' => $emergency_info_completion
        ];
    }

    private function getSalaryInfo($business_member)
    {
        $payroll_setting = $this->business->payrollSetting;
        $payroll_percentage_breakdown = (new GrossSalaryBreakdownCalculate())->componentPercentageBreakdown($payroll_setting, $business_member);

        $count = 0;
        $salary = $business_member->salary;
        if ($salary && $salary->gross_salary) $count++;
        $salary_completion = round((($count / 1) * self::THRESHOLD), 0);

        $gross_salary_breakdown['business_member_id'] = $business_member->id;
        $gross_salary_breakdown ['breakdown'] = $payroll_percentage_breakdown['breakdown'];
        $gross_salary_breakdown['gross_salary'] = $salary ? floatValFormat($salary->gross_salary) : null;
        $gross_salary_breakdown['gross_salary_percentage'] = $payroll_percentage_breakdown['total_percentage'];
        $gross_salary_breakdown['global_gross_salary_component'] = $this->getGlobalGrossSalaryComponent($payroll_setting);
        $gross_salary_breakdown['gross_salary_log'] = $this->getSalaryLog($business_member);
        $gross_salary_breakdown['gross_salary_completion'] = $salary_completion;
        return $gross_salary_breakdown;
    }

    private function profileCompletion($business_member)
    {
        $count = 0;
        $basic_info_completion = $this->getBasicInfo($business_member)['basic_info_completion'];
        $official_info_completion = $this->getOfficialInfo($business_member)['official_info_completion'];
        $personal_info_completion = $this->getPersonalInfo($business_member)['personal_info_completion'];
        $financial_info_completion = $this->getFinancialInfo($business_member)['financial_info_completion'];
        $emergency_info_completion = $this->getEmergencyInfo()['emergency_info_completion'];
        $gross_salary_info_completion = $this->getSalaryInfo($business_member)['gross_salary_completion'];

        if ($basic_info_completion) $count++;
        if ($official_info_completion) $count++;
        if ($personal_info_completion) $count++;
        if ($financial_info_completion) $count++;
        if ($emergency_info_completion) $count++;
        if ($gross_salary_info_completion) $count++;

        return round((($count / 6) * 100), 0);
    }

    private function getManagerDetails($manager_id)
    {
        $manager_business_member = BusinessMember::findOrFail($manager_id);
        $manager_member = $manager_business_member->member;
        $manager_profile = $manager_member->profile;
        $role = $manager_business_member->role;

        return [
            'id' => $manager_member->id,
            'business_member' => $manager_business_member->id,
            'name' => $manager_profile->name ?: null,
            'employee_id' => $manager_business_member->employee_id,
            'profile' => [
                'id' => $manager_profile->id,
                'name' => $manager_profile->name ?: null,
                'pro_pic' => $manager_profile->pro_pic,
                'mobile' => $manager_business_member->mobile,
                'email' => $manager_profile->email
            ],
            'status' => $manager_business_member->status,
            'department_id' => $role ? $role->businessDepartment->id : null,
            'department' => $role ? $role->businessDepartment->name : null,
            'designation' => $role ? $role->name : null
        ];
    }

    private function getSalaryLog($business_member)
    {
        $salary = $business_member->salary;
        if (!$salary) return [];
        $salary_logs = $salary->logs()->orderBy('created_at', 'DESC')->get();
        return (new SalaryLogFormatter())->setSalaryLogs($salary_logs)->format();
    }

    private function getGlobalGrossSalaryComponent($payroll_setting)
    {
        $global_gross_components = $payroll_setting->components()->where('type', Type::GROSS)->where(function ($query) {
            return $query->where('target_type', null)->orWhere('target_type', TargetType::GENERAL);
        })->where(function ($query) {
            return $query->where('is_default', 1)->orWhere('is_active', 1);
        })->orderBy('type')->get();
        $global_gross_component_data = [];
        foreach ($global_gross_components as $component) {
            $percentage = floatValFormat(json_decode($component->setting, 1)['percentage']);
            array_push($global_gross_component_data, [
                'id' => $component->id,
                'payroll_setting_id' => $component->payroll_setting_id,
                'name' => $component->name,
                'title' => $component->is_default ? Components::getComponents($component->name)['value'] : $component->value, // If it is Default Component Title will come from Class otherwise from DB
                'percentage' => $percentage,
                'type' => $component->type,
                'is_default' => $component->is_default,
                'is_active' => $component->is_active,
                'is_taxable' => $component->is_taxable,
            ]);
        }
        return $global_gross_component_data;
    }

    private function reInviteLogs($business_member)
    {
        if ($business_member->status != Statuses::INVITED) return [];
        if ($business_member->statusChangeLogs->isEmpty()) return [];

        $status_change_logs = $business_member->statusChangeLogs()->orderBy('created_at', 'DESC')->get();
        return (new EmployeeStatusChangeLogFormatter())->setEmployeeStatusChangeLogs($status_change_logs)->format();
    }

    /**
     * @param $business_member
     * @return array
     */
    private function getPdfInfo($business_member)
    {
        return [
            'company_name' => $this->business->name,
            'company_logo' => $this->isDefaultImageByUrl($this->business->logo) ? null : $this->business->logo,
            'joining_date' => $business_member->join_date ? Carbon::parse($business_member->join_date)->format('d.m.y') : 'N/A',
            'deactivated_date' => $business_member->inactived_at ? Carbon::parse($business_member->inactived_at)->format('d.m.y') : 'N/A',
            'date_of_birth' => $this->profile->dob ? Carbon::parse($this->profile->dob)->format('d.m.y') : 'N/A'
        ];
    }

    /**
     * @param $data
     * @return bool
     */
    private function isNull($data)
    {
        if ($data == " ") return true;
        if ($data == "") return true;
        return false;
    }
}
