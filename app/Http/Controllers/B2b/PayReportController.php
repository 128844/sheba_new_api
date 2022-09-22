<?php namespace App\Http\Controllers\B2b;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Sheba\Business\Payslip\Excel as PaySlipExcel;
use App\Sheba\Business\Payslip\PayReport\BkashSalaryReportExcel;
use App\Sheba\Business\Payslip\PayReportList;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Sheba\Business\Payslip\PayReport\PayReportDetails;
use Sheba\Dal\BusinessPayslip\BusinessPayslipRepository;
use Sheba\Dal\Payslip\PayslipRepository;
use Sheba\Dal\Payslip\Status;

class PayReportController extends Controller
{
    /** @var PayslipRepository */
    private $payslipRepo;
    /*** @var BusinessPayslipRepository $businessPayslipRepo*/
    private $businessPayslipRepo;

    /**
     * PayReportController constructor.
     * @param PayslipRepository $payslip_repo
     */
    public function __construct(PayslipRepository $payslip_repo)
    {
        $this->payslipRepo = $payslip_repo;
        $this->businessPayslipRepo = app(BusinessPayslipRepository::class);
    }

    /**
     * @param $business
     * @param $business_payslip_id
     * @param  Request  $request
     * @param  PayReportList  $pay_report_list
     * @param  PaySlipExcel  $pay_slip_excel
     * @return JsonResponse
     */
    public function index( $business, $business_payslip_id ,Request $request, PayReportList $pay_report_list, PaySlipExcel $pay_slip_excel)
    {
        /** @var Business $business */
        $business = $request->business;
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);
        $payroll_setting = $business->payrollSetting;
        list($offset, $limit) = calculatePagination($request);
        if ($request->has('month_year')) {
            $business_pay_slip = $this->businessPayslipRepo->where('business_id', $business->id)->where('schedule_date', 'LIKE', '%'.$request->month_year.'%')->where('status', Status::DISBURSED)->first();
            if (!$business_pay_slip) return api_response($request, null, 404);
            $business_payslip_id = $business_pay_slip->id;
        }
        $payslip = $pay_report_list->setBusiness($business)
            ->setBusinessPayslipId($business_payslip_id)
            ->setSearch($request->search)
            ->setSortKey($request->sort)
            ->setSortColumn($request->sort_column)
            ->setDepartmentID($request->department_id)
            ->setGrossSalaryProrated($request->gross_salary_prorated)
            ->get();
        $count = count($payslip);
        if ($request->file == 'excel') return $pay_slip_excel->setPayslipData($payslip->toArray())->setPayslipName('Pay_report')->get();
        if ($request->limit == 'all') $limit = $count;
        $payslip = collect($payslip)->splice($offset, $limit);
        return api_response($request, null, 200, [
            'payslip' => $payslip,
            'total_calculation' => $pay_report_list->getTotal(),
            'total' => $count,
            'is_prorated_filter_applicable' => $pay_report_list->getIsProratedFilterApplicable(),
            'is_enable' => $payroll_setting->is_enable,
            'salary_month' => $pay_report_list->getSalaryMonth()
        ]);
    }

    /**
     * @param $business
     * @param $payslip
     * @param Request $request
     * @param PayReportDetails $pay_report_details
     * @return JsonResponse
     */
    public function show($business, $payslip, Request $request, PayReportDetails $pay_report_details)
    {
        /** @var Business $business */
        $business = $request->business;
        $pay_slip = $this->payslipRepo->find($payslip);
        if (!$pay_slip) return api_response($request, null, 404);
        $business_member = $pay_slip->businessMember;
        if ($request->has('month_year')) {
            $business_pay_slip = $this->businessPayslipRepo->where('business_id', $business->id)->where('schedule_date', 'LIKE', '%'.$request->month_year.'%')->first();
            if (!$business_pay_slip) return api_response($request, null, 404);
            $pay_slip = $this->payslipRepo->where('business_payslip_id', $business_pay_slip->id)->where('business_member_id', $business_member->id)->first();
            if (!$pay_slip) return api_response($request, null, 404);
        }
        $pay_report_detail = $pay_report_details->setPayslip($pay_slip)->get();

        if ($request->file == 'pdf') return App::make('dompdf.wrapper')->loadView('pdfs.payslip.payroll_details', compact('pay_report_detail'))->download("payroll_details.pdf");

        return api_response($request, null, 200, ['pay_report_detail' => $pay_report_detail]);
    }

    public function lastDisbursedMonth(Request $request, PayReportList $pay_report_list)
    {
        /** @var Business $business */
        $business = $request->business;
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);
        $last_disbursed_month = $pay_report_list->setBusiness($business)->getDisbursedMonth();

        return api_response($request, null, 200, ['last_disbursed_month' => $last_disbursed_month]);
    }

    /**
     * @param Request $request
     * @param PayReportList $pay_report_list
     * @return JsonResponse
     */
    public function bkashSalaryReport($business, $id, Request $request, PayReportList $pay_report_list)
    {
        ini_set('memory_limit', '3072M');
        ini_set('max_execution_time', 480);

        /** @var Business $business */
        $business = $request->business;

        $url = public_path('uploads/bKash_sample/bkash_payable_file.xls');
        $file_path = storage_path('exports') . DIRECTORY_SEPARATOR . basename($url);
        file_put_contents($file_path, file_get_contents($url));

        $payslip = $pay_report_list->setBusiness($business)
            ->setBusinessPayslipId($id)
            ->getBkashSalaryData();
        $file_link = (new BkashSalaryReportExcel)->setFile($file_path)->setEmployeeData($payslip->toArray())->takeCompletedAction();

        return api_response($request, null, 200, ['file_url' => $file_link]);
    }
}
