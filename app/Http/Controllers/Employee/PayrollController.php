<?php namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Jobs\Business\SendPayslipEmailToBusinessMember;
use App\Sheba\Business\Payslip\PayReport\PayReportPdfHandler;
use Illuminate\Http\Request;
use App\Sheba\Business\BusinessBasicInformation;
use Illuminate\Support\Facades\Storage;
use Sheba\Business\Payslip\PayReport\PayReportDetails;
use Sheba\Dal\BusinessPayslip\BusinessPayslipRepository;
use Sheba\Dal\Payslip\PayslipRepository;
use Sheba\Dal\Payslip\Status;
use Sheba\Helpers\TimeFrame;

class PayrollController extends Controller
{
    use BusinessBasicInformation;

    /*** @var PayslipRepository */
    private $payslipRepository;
    /*** @var BusinessPayslipRepository $businessPayslipRepo*/
    private $businessPayslipRepo;

    public function __construct(PayslipRepository $payslip_repository)
    {
        $this->payslipRepository = $payslip_repository;
        $this->businessPayslipRepo = app(BusinessPayslipRepository::class);
    }

    public function downloadPayslip(Request $request, PayReportDetails $pay_report_details, TimeFrame $time_frame, PayReportPdfHandler $pay_report_pdf_handler)
    {
        $business = $this->getBusiness($request);
        $business_member = $this->getBusinessMember($request);
        if (!$business_member) return api_response($request, null, 404);
        $time_period = $time_frame->forAMonth($request->month, $request->year);
        $business_payslip = $this->businessPayslipRepo->where('business_id', $business->id)->where('status', Status::DISBURSED)->whereBetween('schedule_date', [$time_period->start->toDateString(), $time_period->end->toDateString()])->first();
        if (!$business_payslip) return api_response($request, null, 404);
        $payslip = $this->payslipRepository->where('business_payslip_id', $business_payslip->id)->where('business_member_id', $business_member->id)->first();
        if (!$payslip) return api_response($request, null, 404);
        if ($request->send_email) {
            $pdf_link = $request->pdf_link;
            $profile = $business_member->member->profile;
            $employee_email = $profile->email;
            $employee_name = $profile->name;
            dispatch(new SendPayslipEmailToBusinessMember($business_member->business, $employee_email, $employee_name, $time_period, $pdf_link));
            return api_response($request, null, 200, ['employee_email' => $employee_email]);
        }
        $filename = 'Payslip_' . $request->month.'_' .$request->year.'_business_'.$business_member->business->id.'_business_member_'. $business_member->id. '.pdf';
        if (Storage::disk('s3')->exists('payslips/'.$filename)) return api_response($request, null, 200, ['payslip_pdf_link' => env('S3_URL').'payslips/'.$filename]);
        $pay_report_detail = $pay_report_details->setPayslip($payslip)->get();
        $pay_report_pdf = $pay_report_pdf_handler->setBusinessMember($business_member)->setPayReportDetails($pay_report_detail)->setTimePeriod($time_period)->setFileName($filename)->generate();
        return api_response($request, null, 200, ['payslip_pdf_link' => $pay_report_pdf]);
    }

    public function disbursedMonth(Request $request)
    {
        $business_member = $this->getBusinessMember($request);
        if (!$business_member) return api_response($request, null, 404);
        $disbursed_payslips = $this->payslipRepository->where('business_member_id', $business_member->id)->where('status', Status::DISBURSED)->orderBy('schedule_date', 'DESC')->get();
        if (!$disbursed_payslips) return api_response($request, null, 404);
        $disbursed_months_data = [];
        foreach ($disbursed_payslips as $disbursed_payslip) {
            $schedule_date = $disbursed_payslip->schedule_date;
            array_push($disbursed_months_data, [
                'id' => $disbursed_payslip->id,
                'year' => $schedule_date->format('Y'),
                'month' => $schedule_date->format('m'),
                'day' => $schedule_date->format('d'),
                'month_name' => $schedule_date->format('M'),
            ]);
        }
        return api_response($request, null, 200, ['disbursed_months' => $disbursed_months_data]);
    }
}
