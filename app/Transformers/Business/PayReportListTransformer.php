<?php namespace App\Transformers\Business;

use App\Models\BusinessMember;
use Carbon\Carbon;
use DateTimeZone;
use League\Fractal\TransformerAbstract;
use Sheba\Dal\PayrollComponent\Components;
use Sheba\Dal\PayrollComponent\Type;
use Sheba\Dal\Payslip\Payslip;

class PayReportListTransformer extends TransformerAbstract
{
    const NET_PAYABLE = 'net_payable';
    const GROSS_SALARY = 'gross_salary';
    private $grossSalary;

    /**
     * @param Payslip $payslip
     * @return array
     */
    public function transform(Payslip $payslip)
    {
        $business_member = $payslip->businessMember;
        $department = $business_member->department();
        $salary_breakdown = $payslip->salaryBreakdown();
        $gross_salary_breakdown = $this->getGrossBreakdown($salary_breakdown);
        return [
            'id' => $payslip->id,
            'business_member_id' => $payslip->business_member_id,
            'employee_id' => $business_member->employee_id ? $business_member->employee_id : 'N/A',
            'employee_name' => $business_member->profile()->name,
            'department' => $department ? $department->name : 'N/A',
            'schedule_date' => Carbon::parse($payslip->schedule_date)->format('Y-m-d'),
            'gross_salary' => $this->grossSalary,
            'addition' => $this->getTotal($salary_breakdown, Type::ADDITION),
            'deduction' => $this->getTotal($salary_breakdown, Type::DEDUCTION),
            'net_payable' => $this->getTotal($salary_breakdown, self::NET_PAYABLE),
            'gross_salary_breakdown' => $gross_salary_breakdown,
            'addition_breakdown' => $this->getPayrollComponentBreakdown($salary_breakdown, Type::ADDITION),
            'deduction_breakdown' => $this->getPayrollComponentBreakdown($salary_breakdown, Type::DEDUCTION)
        ];
    }

    /**
     * @param $salary_breakdown
     * @param $type
     * @return float|int|mixed
     */
    private function getTotal($salary_breakdown, $type)
    {
        $addition = 0;
        $deduction = 0;
        if (array_key_exists('payroll_component', $salary_breakdown)) {
            foreach ($salary_breakdown['payroll_component'] as $component_type => $component_breakdown) {
                if ($component_type == Type::ADDITION) {
                    foreach ($component_breakdown as $component_value) {
                        $addition += $component_value;
                    }
                }

                if ($component_type == Type::DEDUCTION) {
                    foreach ($component_breakdown as $component_value) {
                        $deduction += $component_value;
                    }
                }
            }
        }
        $net_payable = floatValFormat(($this->grossSalary + $addition) - $deduction);

        if ($type == self::NET_PAYABLE) return $net_payable;
        if ($type == Type::ADDITION) return $addition;
        if ($type == Type::DEDUCTION) return $deduction;
    }

    /**
     * @param $salary_breakdown
     * @return array
     */
    private function getGrossBreakdown($salary_breakdown)
    {
        $gross_salary_breakdown = $salary_breakdown['gross_salary_breakdown'];

        $final_data = [];
        foreach ($gross_salary_breakdown as $component => $component_value) {
            if ($component == self::GROSS_SALARY) {
                $this->grossSalary = floatValFormat($component_value);
                continue;
            }
            $final_data[] = $this->componentBreakdown($component, $component_value);
        }

        return $final_data;
    }

    /**
     * @param $salary_breakdown
     * @param $type
     * @return array
     */
    private function getPayrollComponentBreakdown($salary_breakdown, $type)
    {
        $final_data = [];
        if (array_key_exists('payroll_component', $salary_breakdown)) {
            $components_salary_breakdown = $salary_breakdown['payroll_component'];
            foreach ($components_salary_breakdown as $component_type => $component_breakdown) {
                if ($component_type == $type) {
                    foreach ($component_breakdown as $component => $component_value) {
                        $final_data[] = $this->componentBreakdown($component, $component_value);
                    }
                }
            }
        }
        return $final_data;
    }

    /**
     * @param $component
     * @param $component_value
     * @return array
     */
    private function componentBreakdown($component, $component_value)
    {
        $component_title = Components::getComponents($component)['value'];
        return [
            'key' => $component,
            'name' => $component_title ? $component_title : ucwords(implode(" ", explode("_", $component))),
            'value' => $component_value
        ];
    }
}
