<?php namespace Sheba\Business\CoWorker;

use Excel as EmployeeExcel;
use PHPExcel_Style_Fill;
use PHPExcel_Style_NumberFormat;

class Excel
{
    /**
     * @var array
     */
    private $employees;
    private $data = [];

    /**
     * @param array $employees
     * @return $this
     */
    public function setEmployee(array $employees)
    {
        $this->employees = $employees;
        return $this;
    }

    public function get()
    {
        $this->makeData();
        $file_name = 'Coworker Report';
        EmployeeExcel::create($file_name, function ($excel) {
            $excel->sheet('Sheet 1', function ($sheet) {
                $sheet->setColumnFormat(array(
                    'C' => '#0', 'P' => '#0',
                    'Q' => '#0', 'S' => '#0',
                    'T' => '#0',
                ));
                foreach ($this->data as $key => $data) {
                    $x = 'A'.($key + 1).':D'.($key + 1);
                    $y = 'F'.($key + 1).':V'.($key + 1);
                    if ($data['status'] == 'Invited') {
                        $sheet->cell($key + 1, function($row) { $row->setFontColor('#FF9900'); });
                        //$sheet->getStyle($y)->getFont()->getColor()->setRGB('#FFFFFF');
                        $sheet->cell($x, function ($cells) {
                            $cells->setFontColor('#060101');
                        });
                        $sheet->cell($y, function ($cells) {
                            $cells->setFontColor('#060101');
                        });
                    }
                    if ($data['status'] == 'Inactive') {
                        $sheet->cell($key + 1, function($row) { $row->setFontColor('#FF0000'); });
                        //$sheet->getStyle($y)->getFont()->getColor()->setRGB('#FFFFFF');
                        $sheet->cell($x, function ($cells) {
                            $cells->setFontColor('#060101');
                        });
                        $sheet->cell($y, function ($cells) {
                            $cells->setFontColor('#060101');
                        });
                    }
                }
                $sheet->fromArray($this->data, null, 'A1', false, false);
                $sheet->prependRow($this->getHeaders());
                $sheet->freezePane('C2');
                $sheet->cell('A1:W1', function ($cells) {
                    $cells->setFontWeight('bold');
                });
                $sheet->getDefaultStyle()->getAlignment()->applyFromArray(['horizontal' => 'left']);
                $sheet->setAutoSize(true);
            });
        })->export('xlsx');
    }
    
    private function makeData()
    {
        foreach ($this->employees as $employee) {
            $this->data[] = [
                'employee_id' => $employee['employee_id'] ?: 'N/A',
                'employee_name' => $employee['profile']['name'],
                'phone' => $employee['profile']['mobile'] ?: '-',
                'email' => $employee['profile']['email'] ?: '-',
                'status' => ucfirst($employee['status']) ?: '-',
                'department' => $employee['department'] ?: '-',
                'designation' => $employee['designation'] ?: '-',
                "manager_name" => $employee['manager_name'] ?: '-',
                "join_date" => $employee['join_date'] ?: '-',
                "inactived_at" => $employee['inactived_at'] ?: '-',
                "employee_grade" => $employee['employee_grade'] ?: '-',
                "employee_type" => $employee['employee_type'] ?: '-',
                "previous_institution" => $employee['previous_institution'] ?: '-',
                "date_of_birth" => $employee['date_of_birth'] ?: '-',
                "address" => $employee['address'] ?: '-',
                "nationality" => $employee['nationality'] ?: '-',
                "nid_no" => $employee['nid_no'] ?: '-',
                "tin_no" => $employee['tin_no'] ?: '-',
                "bank_name" => $employee['bank_name'] ?: '-',
                "bank_account_no" => $employee['bank_account_no'] ?: '-',
                "bkash_no" => $employee['bkash_no'] ?: '-',
                "emergency_contract_person_number" => $employee['emergency_contract_person_number'] ?: '-',
                "emergency_contract_person_name" => $employee['emergency_contract_person_name'] ?: '-',
                "emergency_contract_person_relationship" => $employee['emergency_contract_person_relationship'] ?: '-',
            ];
        }

    }

    /**
     * @return string[]
     */
    private function getHeaders()
    {
        return [
            'Employee ID', 'Employee Name', 'Phone', 'Email', 'Status',
            'Department', 'Designation', 'Manager', 'Joining Date', 'Deactivated Date', 'Employee Grade',
            'Employee Type', 'Previous Institution', 'DOB', 'Address',
            'Nationality', 'NID/Passport', 'TIN', 'Bank Name',
            'Bank Account No.', 'bKash Account No', 'Emergency Contact', 'Name Emergency Contact', 'Relationship Emergency Contact'
        ];
    }

}
