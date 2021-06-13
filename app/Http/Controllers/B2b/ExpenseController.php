<?php namespace App\Http\Controllers\B2b;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Transformers\Business\ExpenseTransformer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use Sheba\Attachments\FilesAttachment;
use Sheba\Business\Expense\ExpenseExcel;
use Sheba\Employee\ExpensePdf;
use Sheba\ModificationFields;
use Sheba\Employee\ExpenseRepo;
use Throwable;
use DB;

class ExpenseController extends Controller
{
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
    public function index(Request $request, ExpenseExcel $excel)
    {
        $this->validate($request, [
            'status' => 'string|in:open,closed',
            'limit' => 'numeric', 'offset' => 'numeric',
            'date' => 'string'
        ]);
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

        $members_ids = $business_members->pluck('member_id')->toArray();

        $expenses = $this->expense_repo->getExpenseByMember($members_ids);

        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');

        if ($request->has('date')) {
            $dates = $this->getStartDateEndDate($request->date);
            $start_date = $dates['start_date'];
            $end_date = $dates['end_date'];
        }

        $expenses->whereBetween('created_at', [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $expenses = $expenses->get();
        if ($request->has('search')) $expenses = $this->searchWithEmployeeName($expenses, $request)->values();
        $fractal = new Manager();
        $resource = new Collection($expenses, new ExpenseTransformer());
        $expenses = $fractal->createData($resource)->toArray()['data'];

        $total_calculation = $this->getTotalCalculation($expenses);

        $total_expense_count = count($expenses);

        if ($request->has('limit')) $expenses = collect($expenses)->splice($offset, $limit);

        if ($request->file == 'excel') return $excel->setData(is_array($expenses) ? $expenses : $expenses->toArray())
            ->setName('Expense Report')
            ->get();

        return api_response($request, $expenses, 200, ['expenses' => $expenses, 'total_expenses_count' => $total_expense_count, 'total_calculation' => $total_calculation]);
    }

    private function getStartDateEndDate($date)
    {
        $splitDate = explode('-', $date);
        return [
            'start_date' => Carbon::createFromDate($splitDate[0], $splitDate[1])->startOfMonth()->format('Y-m-d'),
            'end_date' => Carbon::createFromDate($splitDate[0], $splitDate[1])->endOfMonth()->format('Y-m-d')
        ];
    }

    private function getTotalCalculation($expenses)
    {
        $total_employee = sizeof(array_unique(array_map(function ($expense) {
            return $expense['member_id'];
        }, $expenses)));

        $total_department = sizeof(array_unique(array_map(function ($expense) {
            return $expense['employee_department'];
        }, $expenses)));

        $total_amount = array_reduce($expenses, function($sum, $item) {
            return $sum += $item['amount'];
        });

        return [
            'employee' => $total_employee,
            'department' => $total_department,
            'amount' => $total_amount
        ];
    }

    private function searchWithEmployeeName($expenses, Request $request)
    {
        return $expenses->filter(function ($expense) use ($request) {
            $profile = $expense->member->profile;
            return str_contains(strtoupper($profile->name), strtoupper($request->search));
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
            $data = $this->expense_repo->update($request, $expense, $business_member);
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
        $business_member = BusinessMember::where('business_id', $request->business_member->business_id)->where('member_id', $request->member_id)->first();
        return $pdf->generate($business_member, $request->month, $request->year);
    }

    public function filterMonth(Request $request)
    {
        try {
            $this->validate($request, [
                'limit' => 'numeric', 'offset' => 'numeric', 'month' => 'numeric',
            ]);
            $business_member = $request->business_member;
            if (!$business_member) return api_response($request, null, 401);
            $month = $request->month;
            $expenses = $this->expense_repo->filterMonth($month, $request);
            $totalExpenseCount = $expenses->count();
            $totalExpenseSum = $expenses->sum('amount');
            return api_response($request, $expenses, 200, ['expenses' => $expenses, 'total_expenses_count' => $totalExpenseCount, 'total_expenses_sum' => $totalExpenseSum]);

        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }

    }
}
