<?php namespace App\Http\Controllers\B2b;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Sheba\Business\Expense\ExpenseReportDetailsExcel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sheba\Attachments\FilesAttachment;
use Sheba\Business\Expense\ExpenseExcel;
use Sheba\Employee\ExpensePdf;
use Sheba\ModificationFields;
use Sheba\Employee\ExpenseRepo;
use Sheba\Business\Expense\ExpenseList as ExpenseList;
use Throwable;
use DB;

class ExpenseController extends Controller
{
    const FROM_WEB_PORTAL = 1;
    private $expense_repo;
    use ModificationFields;
    use FilesAttachment;

    /**
     * ExpenseController constructor.
     * @param ExpenseRepo $expense_repo
     */
    public function __construct(ExpenseRepo $expense_repo)
    {
        $this->expense_repo = $expense_repo;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request, ExpenseExcel $excel, ExpenseList $expenseList)
    {
        $this->validate($request, ['date' => 'string']);
        list($offset, $limit) = calculatePagination($request);
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);
        /** @var Business $business */
        $business = $request->business;
        $business_members = $business->getAccessibleBusinessMember();
        if ($request->has('department_id')) {
            $business_members = $business_members->whereHas('role', function ($q) use ($request) {
                $q->whereHas('businessDepartment', function ($q) use ($request) {
                    $q->where('business_departments.id', $request->department_id);
                });
            });
        }
        $business_members_ids = $business_members->pluck('id')->toArray();
        $expenses = $this->expense_repo->getExpenseByBusinessMember($business_members_ids);
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        if ($request->has('date')) {
            $dates = $this->getStartDateEndDate($request->date);
            $start_date = $dates['start_date'];
            $end_date = $dates['end_date'];
        }
        $expenses->whereBetween('created_at', [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $expenses_data = $expenseList->setData($expenses->get())->get();
        $expenses = $expenses_data['expense_breakdown'];
        if ($request->has('search')) $expenses = $this->searchExpenseList($expenses, $request);
        $total_expense_count = count($expenses);
        if ($request->has('limit')) $expenses = collect($expenses)->splice($offset, $limit);
        if ($request->has('sort_employee_name')) {
            $expenses = $this->sortByName($expenses, $request->sort_employee_name)->values();
        }
        if ($request->has('sort_amount')) {
            $expenses = $this->sortByAmount($expenses, $request->sort_amount)->values();
        }
        if ($request->file == 'excel') return $excel->setData(is_array($expenses) ? $expenses : $expenses->toArray())
            ->setName('Expense Report')
            ->get();
        return api_response($request, $expenses, 200, ['expenses' => $expenses, 'total_expenses_count' => $total_expense_count, 'total_calculation' => $expenses_data['expense_summary']]);
    }

    /**
     * @param $date
     * @return array
     */
    private function getStartDateEndDate($date)
    {
        $splitDate = explode('-', $date);
        return [
            'start_date' => Carbon::createFromDate($splitDate[0], $splitDate[1])->startOfMonth()->format('Y-m-d'),
            'end_date' => Carbon::createFromDate($splitDate[0], $splitDate[1])->endOfMonth()->format('Y-m-d')
        ];
    }

    /**
     * @param $expenses
     * @param Request $request
     * @return array
     */
    private function searchExpenseList($expenses, Request $request)
    {
        $employee_ids = array_filter($expenses, function ($expense) use ($request) {
            return str_contains($expense['employee_id'], $request->search);
        });
        $employee_names = array_filter($expenses, function ($expense) use ($request) {
            return str_contains(strtoupper($expense['employee_name']), strtoupper($request->search));
        });
        $amounts = array_filter($expenses, function ($expense) use ($request) {
            return str_contains($expense['amount'], strtoupper($request->search));
        });
        $searched_expenses = collect(array_merge($employee_ids, $employee_names, $amounts));
        $searched_expenses = $searched_expenses->unique(function ($expense) {
            return $expense['member_id'];
        });
        return $searched_expenses->values()->all();
    }

    /**
     * @param $expenses
     * @param string $sort
     * @return mixed
     */
    private function sortByName($expenses, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return collect($expenses)->$sort_by(function ($expense) {
            return strtoupper($expense['employee_name']);
        });
    }

    /**
     * @param $expenses
     * @param string $sort
     * @return mixed
     */
    private function sortByAmount($expenses, $sort = 'asc')
    {
        $sort_by = ($sort === 'asc') ? 'sortBy' : 'sortByDesc';
        return collect($expenses)->$sort_by(function ($expense) {
            return strtoupper($expense['amount']);
        });
    }


    public function show($business, $expense, Request $request)
    {
        try {
            $business_member = $request->business_member;
            if (!$business_member) return api_response($request, null, 401);
            $data = $this->expense_repo->show($request, $expense);
            return $data ? api_response($request, $expense, 200, $data) : api_response($request, null, 404);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function update($business, $expense, Request $request)
    {
        try {
            $this->validate($request, [
                'amount' => 'required|numeric', 'remarks' => 'string', 'type' => 'string',
            ]);
            $business_member = $request->business_member;
            if (!$business_member) return api_response($request, null, 401);
            $data = $this->expense_repo->update($request, $expense, $business_member, self::FROM_WEB_PORTAL);
            return $data ? api_response($request, $expense, 200, $data) : api_response($request, null, 404);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function delete($business, $expense, Request $request)
    {
        try {
            $business_member = $request->business_member;
            if (!$business_member) return api_response($request, null, 401);
            $data = $this->expense_repo->delete($request, $expense);
            return $data ? api_response($request, $expense, 200) : api_response($request, null, 404);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function downloadPdf(Request $request, ExpensePdf $pdf)
    {
        $business_member = BusinessMember::findOrFail($request->business_member_id);
        return $pdf->generate($business_member, $request->month, $request->year);
    }

    public function filterMonth($member_id, Request $request)
    {
        try {
            $this->validate($request, [
                'limit' => 'numeric', 'offset' => 'numeric', 'month' => 'numeric',
            ]);
            $business_member = $request->business_member;
            if (!$business_member) return api_response($request, null, 401);
            $month = $request->month;
            $year = $request->year;
            $expenses = $this->expense_repo->filterMonth($year, $month, $request);
            $totalExpenseCount = $expenses->count();
            $totalExpenseSum = $expenses->sum('amount');
            return api_response($request, $expenses, 200, ['expenses' => $expenses, 'total_expenses_count' => $totalExpenseCount, 'total_expenses_sum' => $totalExpenseSum]);

        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }

    }

    public function downloadExpenseReport(Request $request, ExpenseReportDetailsExcel $expense_report_details_excel)
    {
        $business_member = $request->business_member;
        if (!$business_member) return api_response($request, null, 401);
        /** @var Business $business */
        $business = $request->business;
        $business_members = $business->getAccessibleBusinessMember();
        if ($request->has('department_id')) {
            $business_members = $business_members->whereHas('role', function ($q) use ($request) {
                $q->whereHas('businessDepartment', function ($q) use ($request) {
                    $q->where('business_departments.id', $request->department_id);
                });
            });
        }
        $business_members_ids = $business_members->pluck('id')->toArray();
        $expenses = $this->expense_repo->getDetailExpenseByBusinessMember($business_members_ids);
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        if ($request->has('date')) {
            $dates = $this->getStartDateEndDate($request->date);
            $start_date = $dates['start_date'];
            $end_date = $dates['end_date'];
        }
        $expenses->whereBetween('created_at', [$start_date . ' 00:00:00', $end_date . ' 23:59:59'])->orderBy('created_at', 'DESC');
        $expense_report_details_excel->setData($expenses->get())->download();
        return api_response($request, null, 200);
    }
}
