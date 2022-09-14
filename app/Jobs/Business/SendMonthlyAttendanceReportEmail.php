<?php namespace App\Jobs\Business;

use App\Models\BusinessMember;
use App\Sheba\Business\BusinessEmailQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Sheba\Mail\BusinessMail;

class SendMonthlyAttendanceReportEmail extends BusinessEmailQueue
{
    use InteractsWithQueue, SerializesModels;

    private $attachment;
    /*** @var BusinessMember */
    private $businessMember;
    private $startDate;
    private $endDate;

    public function __construct($attachment, BusinessMember $business_member, $start_date, $end_date)
    {
        $this->attachment = $attachment;
        $this->businessMember = $business_member;
        $this->startDate = $start_date;
        $this->endDate = $end_date;
        parent::__construct();
    }

    public function handle()
    {
        if ($this->attempts() <= 1) {
            $subject = 'Monthly Attendance Report from ' . $this->startDate . ' to ' . $this->endDate;
            $profile = $this->businessMember->member->profile;
            BusinessMail::send(['emails.custom-attendance-report',], [
                'employee_name' => $profile->name,
                'start_date' => $this->startDate,
                'end_date' => $this->endDate
            ], function ($m) use ($subject, $profile) {
                $m->from('b2b@sheba.xyz', 'sBusiness.xyz');
                //$m->to($profile->email)->subject($subject);
                $m->to('asadrabbi@gmail.com')->subject($subject);
                $m->attach($this->attachment);
            });
        }
    }

}
