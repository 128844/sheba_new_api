<?php namespace App\Jobs\Business;

use App\Sheba\Business\BusinessEmailQueue;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Sheba\Business\Attendance\Monthly\Excel;
use Sheba\Business\Attendance\Monthly\MonthlyAttendanceCalculator;
use Sheba\Mail\BusinessMail;

class SendMonthlyAttendanceReportEmail extends BusinessEmailQueue
{
    use InteractsWithQueue, SerializesModels;

    private $business;
    private $request;

    public function __construct($business, $request)
    {
        $this->business = $business;
        $this->request = $request;
        parent::__construct();
    }

    public function handle(Excel $monthly_excel, MonthlyAttendanceCalculator $calculator)
    {
        if ($this->attempts() > 2) return;

        $request = new Request($this->request);
        list($all_employee_attendance, , $start_date, $end_date) = $calculator->calculate($this->business, $request);

        $monthly_excel->setMonthlyData($all_employee_attendance->toArray())->setStartDate($start_date)->setEndDate($end_date)->save("email");
        $file_path = storage_path('exports') . '/Custom_attendance_report.xls';

        $subject = 'Monthly Attendance Report from ' . $start_date . ' to ' . $end_date;
        $profile = $request->business_member->member->profile;
        BusinessMail::send('emails.custom-attendance-report', [
            'employee_name' => $profile->name,
            'start_date' => $start_date,
            'end_date' => $end_date
        ], function ($m) use ($subject, $profile, $file_path) {
            $m->from('b2b@sheba.xyz', 'sBusiness.xyz');
            $m->to($profile->email)->subject($subject);
            $m->attach($file_path);
        });

        unlink($file_path);
    }

}
