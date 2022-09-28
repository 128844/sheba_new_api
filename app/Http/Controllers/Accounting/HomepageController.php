<?php namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Sheba\AccountingEntry\Constants\AccountingReport;
use App\Sheba\AccountingEntry\Repository\HomepageRepository;
use App\Sheba\UserMigration\Modules;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sheba\AccountingEntry\Exceptions\AccountingEntryServerError;
use Sheba\AccountingEntry\Statics\AccountingStatics;
use Illuminate\Support\Facades\Cache;

class HomepageController extends Controller
{
    private $homepageRepo;

    public function __construct(HomepageRepository $homepageRepo)
    {
        $this->homepageRepo = $homepageRepo;
    }

    /**
     * @throws AccountingEntryServerError
     */
    public function getAssetAccountBalance(Request $request): JsonResponse
    {
        $response = $this->homepageRepo->getAssetBalance($request->partner->id);
        return api_response($request, $response, 200, ['data' => $response]);

    }

    /**
     * @throws AccountingEntryServerError
     */
    public function getIncomeExpenseBalance(Request $request): JsonResponse
    {
        $start_date = $this->convertStartDate($request->start_date);
        $end_date = $this->convertEndDate($request->end_date);
        $cache_key = "PartnerIncomeExpenseBalance::partner:".$request->partner->id."_".$start_date."_".$end_date;

        if ($end_date < $start_date) {
            return api_response($request, null, 400, ['message' => 'End date can not smaller than start date']);
        }

        return Cache::store('redis')->remember($cache_key, 10, function () use ($request, $start_date, $end_date) {
            $response = $this->homepageRepo->getIncomeExpenseBalance($request->partner->id, $start_date, $end_date);
            return api_response($request, $response, 200, ['data' => $response]);
        });
    }

    /**
     * @throws AccountingEntryServerError
     */
    public function getIncomeExpenseEntries(Request $request): JsonResponse
    {
        $limit = $request->limit ?? 10;
        $nextCursor = $request->next_cursor ?? null;
        $startDate = $request->has('start_date') ? $this->convertStartDate($request->start_date) : null;
        $endDate = $request->has('start_date') ? $this->convertEndDate($request->end_date) : null;
        $sourceType = $request->has('source_type') ? $request->source_type : null;
        $response = $this->homepageRepo->getIncomeExpenseEntries($request->partner->id, $limit, $nextCursor, $startDate, $endDate, $sourceType);
        return api_response($request, $response, 200, ['data' => $response]);
    }

    /**
     * @param $accountKey
     * @param Request $request
     * @return JsonResponse
     * @throws AccountingEntryServerError
     */
    public function getEntriesByAccountKey($accountKey, Request $request): JsonResponse
    {
        $limit = $request->limit ?? 15;
        $nextCursor = $request->next_cursor ?? null;

        $response = $this->homepageRepo->getEntriesByAccountKey($accountKey, $request->partner->id, $limit, $nextCursor);
        return api_response($request, $response, 200, ['data' => $response]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws AccountingEntryServerError
     */
    public function getDueCollectionBalance(Request $request): JsonResponse
    {
        $startDate = $this->convertStartDate($request->start_date);
        $endDate = $this->convertEndDate($request->end_date);
        if ($endDate < $startDate) {
            return api_response($request, null, 400, ['message' => 'End date can not smaller than start date']);
        }

        $response = $this->homepageRepo->getDueCollectionBalance($request->partner->id, $startDate, $endDate);
        return api_response($request, $response, 200, ['data' => $response]);
    }


    /**
     * @throws AccountingEntryServerError
     */
    public function getAccountListBalance(Request $request): JsonResponse
    {
        $startDate = $request->has('start_date') ? $this->convertStartDate($request->start_date) : null;
        $endDate = $request->has('start_date') ? $this->convertEndDate($request->end_date) : null;
        $limit = $request->has('limit') ? $request->limit : null;
        $offset = $request->has('offset') ? $request->offset : null;
        $rootAccount = $request->has('root_account') ? $request->root_account : null;
        if ($endDate < $startDate) {
            return api_response($request, null, 400, ['message' => 'End date can not smaller than start date']);
        }

        $response = $this->homepageRepo->getAccountListBalance($request->partner->id, $startDate, $endDate, $limit, $offset, $rootAccount);
        return api_response($request, $response, 200, ['data' => $response]);

    }

    public function getTrainingVideo(Request $request): JsonResponse
    {
        $response = AccountingStatics::getFaqAndTrainingVideoKey();
        return api_response($request, $response, 200, ['data' => $response]);
    }

    public function getHomepageReportList(Request $request): JsonResponse
    {
        $data = [
            [
                'key' => AccountingReport::JOURNAL_REPORT,
                'report_bangla_name' => 'জার্নাল রিপোর্ট',
                'url' => config('sheba.api_url') . '/v2/accounting/reports/journal_report',
                'icon' => config('accounting_entry.icon_url') . '/' . 'journal_report.png'
            ],
            [
                'key' => AccountingReport::GENERAL_LEDGER_REPORT,
                'report_bangla_name' => 'জেনারেল লেজার রিপোর্ট',
                'url' => config('sheba.api_url') . '/v2/accounting/reports/general_ledger_report',
                'icon' => config('accounting_entry.icon_url') . '/' . 'general_ledger_report.png'
            ],
            [
                'key' => AccountingReport::PROFIT_LOSS_REPORT,
                'report_bangla_name' => 'লাভ-ক্ষতি রিপোর্ট',
                'url' => config('sheba.api_url') . '/v2/accounting/reports/profit_loss_report',
                'icon' => config('accounting_entry.icon_url') . '/' . 'loss_profit_report.png'
            ],
            [
                'key' => 'other_report',
                'report_bangla_name' => 'অন্যান্য রিপোর্ট',
                'url' => "",
                'icon' => config('accounting_entry.icon_url') . '/' . 'investments.png'
            ],
        ];
        return api_response($request, $data, 200, ['data' => $data]);
    }

    public function getTimeFilters(Request $request): JsonResponse
    {
        Carbon::setWeekStartsAt(Carbon::SATURDAY);
        Carbon::setWeekEndsAt(Carbon::FRIDAY);
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $startOfQuarter = Carbon::now()->startOfQuarter();
        $endOfQuarter = Carbon::now()->endOfQuarter();
        $startOfYear = Carbon::now()->startOfYear();
        $endOfYear = Carbon::now()->endOfYear();
        $response = [
            [
                'title' => 'আজ (' . convertNumbersToBangla(Carbon::now()->day, false) . ' ' . banglaMonth(Carbon::now()->month) . ')',
                'start_date' => Carbon::now()->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
            ],
            [
                'title' => 'এই সপ্তাহ (' .
                    convertNumbersToBangla($startOfWeek->day, false) .
                    ($startOfWeek->month === $endOfWeek->month ? '' : banglaMonth($startOfWeek->month)) . ' - ' .
                    convertNumbersToBangla($endOfWeek->day, false) . ' ' .
                    banglaMonth($endOfWeek->month) . ')',
                'start_date' => $startOfWeek->format('Y-m-d'),
                'end_date' => $endOfWeek->format('Y-m-d'),
            ],
            [
                'title' => 'এই মাস (' . banglaMonth($startOfMonth->month) . ' মাস)',
                'start_date' => $startOfMonth->format('Y-m-d'),
                'end_date' => $endOfMonth->format('Y-m-d'),
            ],
            [
                'title' => 'এই কোয়ার্টার (' . banglaMonth($startOfQuarter->month) . ' - ' . banglaMonth($endOfQuarter->month) . ')',
                'start_date' => $startOfQuarter->format('Y-m-d'),
                'end_date' => $endOfQuarter->format('Y-m-d'),
            ],
            [
                'title' => 'এই বছর (' . convertNumbersToBangla($startOfYear->year, false) . ' সাল)',
                'start_date' => $startOfYear->format('Y-m-d'),
                'end_date' => $endOfYear->format('Y-m-d'),
            ],
        ];
        return api_response($request, null, 200, ['data' => $response]);
    }

    private function convertStartDate($date = null)
    {
        return $date ?
            Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 0:00:00')->timestamp :
            strtotime('1 January 1971');
    }

    private function convertEndDate($date = null)
    {
        return $date ?
            Carbon::createFromFormat('Y-m-d H:i:s', $date . ' 23:59:59')->timestamp :
            strtotime('tomorrow midnight') - 1;
    }

    /**
     * @throws AccountingEntryServerError
     */
    public function homePageStat(Request $request): JsonResponse
    {
        if (!$request->partner->isMigrated(Modules::EXPENSE)) {
            return api_response($request, null, 200, ['data' => null]);
        }

        $cache_key = "PartnerHomeStat::partner:".$request->partner->id;
        $data_from_cache = Cache::store('redis')->get($cache_key);
        if ($data_from_cache) {
            return api_response($request, $data_from_cache, 200, ['data' => $data_from_cache]);
        }

        $dateTime = Carbon::now();
        $today = $dateTime->format('Y-m-d');
        $month = $dateTime->month;
        $year = $dateTime->year;
        $firstDayOfMonth = $year .'-'.$month.'-'.'01';
        $lastDayOfMonth = $year .'-'.$month.'-'. $dateTime->daysInMonth;

        $dailyStartDate = $this->convertStartDate($today);
        $dailyEndDate = $this->convertEndDate($today);
        $dailyIncome = $this->homepageRepo->getIncomeExpenseBalance($request->partner->id, $dailyStartDate, $dailyEndDate);

        $monthlyStartDate = $this->convertStartDate($firstDayOfMonth);
        $monthlyEndDate = $this->convertEndDate($lastDayOfMonth);
        $monthlyIncome = $this->homepageRepo->getIncomeExpenseBalance($request->partner->id, $monthlyStartDate, $monthlyEndDate);

        $dueTrackerBalance = $this->homepageRepo->getDueCollectionBalance($request->partner->id, $this->convertStartDate(), $this->convertEndDate());
        $data = [
            "daily_income"   => $dailyIncome['total_income_balance'],
            "monthly_income" => $monthlyIncome['total_income_balance'],
            "receivable"     => $dueTrackerBalance['account_payable'],
            "payable"        => $dueTrackerBalance['account_receivable'],
            "date"           => en2bnNumber($dateTime->day),
            "month"          => banglaMonth($month),
            "api_time"       => Carbon::now()->toDateTimeString()
        ];
        Cache::store('redis')->put($cache_key, $data, Carbon::now()->addMinutes(10));

        return api_response($request, $data, 200, ['data' => $data]);
    }
}