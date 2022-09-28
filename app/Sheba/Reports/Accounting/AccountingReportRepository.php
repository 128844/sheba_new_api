<?php namespace Sheba\Reports\Accounting;

use App\Sheba\AccountingEntry\Constants\UserType;
use Sheba\AccountingEntry\Exceptions\AccountingEntryServerError;
use Sheba\AccountingEntry\Repository\AccountingEntryClient;
use App\Sheba\AccountingEntry\Repository\BaseRepository;
use App\Sheba\AccountingEntry\Constants\AccountingReport;

class AccountingReportRepository extends BaseRepository
{
    private $api;
    private $q;
    private $userId;
    private $limit;
    private $offset;
    private $startDate;
    private $endDate;
    private $transactionType;
    private $reconcile;
    private $gateway;

    /**
     * AccountingReportRepository constructor.
     * @param AccountingEntryClient $client
     */
    public function __construct(AccountingEntryClient $client)
    {
        parent::__construct($client);
        $this->api = 'api/reports/';
    }

    /**
     * @param $userId
     * @return $this
     */
    public function setUserId($userId): AccountingReportRepository
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @param $limit
     * @return $this
     */
    public function setLimit($limit): AccountingReportRepository
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @param $offset
     * @return $this
     */
    public function setOffset($offset): AccountingReportRepository
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @param $startDate
     * @return $this
     */
    public function setStartDate($startDate): AccountingReportRepository
    {
        $this->startDate = $startDate;
        return $this;
    }

    /**
     * @param $endDate
     * @return $this
     */
    public function setEndDate($endDate): AccountingReportRepository
    {
        $this->endDate = $endDate;
        return $this;
    }

    /**
     * @param $transactionType
     * @return $this
     */
    public function setTransactionType($transactionType): AccountingReportRepository
    {
        $this->transactionType = $transactionType;
        return $this;
    }

    /**
     * @param $reconcile
     * @return $this
     */
    public function setReconcile($reconcile): AccountingReportRepository
    {
        $this->reconcile = $reconcile;
        return $this;
    }

    /**
     * @param $gateway
     * @return $this
     */
    public function setGateway($gateway): AccountingReportRepository
    {
        $this->gateway = $gateway;
        return $this;
    }

    /**
     * @param $q
     * @return $this
     */
    public function setQ($q): AccountingReportRepository
    {
        $this->q = $q;
        return $this;
    }

    /**
     * @param $reportType
     * @param $userId
     * @param $startDate
     * @param $endDate
     * @param $accountId
     * @param $accountType
     * @param string $userType
     * @return array
     * @throws AccountingEntryServerError
     */
    public function getAccountingReport($reportType, $userId, $startDate, $endDate, $accountId, $accountType, $userType = UserType::PARTNER): array
    {
        try {
            $data = $this->client->setUserType($userType)->setUserId($userId)
                ->get($this->api . "accounting-report/$reportType?start_date=$startDate&end_date=$endDate&account_id=$accountId&account_type=$accountType");
            if ($reportType === AccountingReport::JOURNAL_REPORT) return (new JournalReportData())->format_data($data);
            return $reportType === AccountingReport::PROFIT_LOSS_REPORT ? (new ProfitLossReportData())->format_data($data) : $data;
        } catch (AccountingEntryServerError $e) {
            throw new AccountingEntryServerError($e->getMessage(), $e->getCode());
        }
    }


    public function getAccountingReportsList($version_code): array
    {
        $list = [
            [
                'key' => AccountingReport::PRODUCT_WISE_SALES_REPORT,
                'report_bangla_name' => 'পণ্য অনুযায়ী বিক্রয় রিপোর্ট',
                'url' => config('sheba.api_url') . '/v2/accounting/reports/pos/product-wise',
                'icon' => config('accounting_entry.icon_url') . '/' . 'item_wise_sales_report.png'
            ],
            [
                'key' => AccountingReport::CUSTOMER_WISE_SALES_REPORT,
                'report_bangla_name' => 'কাস্টমার আনুযায়ী বিক্রির রিপোর্ট',
                'url' => config('sheba.api_url') . '/v2/accounting/reports/pos/customer-wise',
                'icon' => config('accounting_entry.icon_url') . '/' . 'customer_wise_sales_report.png'
            ],
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
        ];
        if (intval($version_code) < 300600) {
            return $list;
        } else {
            $qr_list = [[
                'key' => AccountingReport::DIGITAL_TRANSACTION_LIST,
                'report_bangla_name' => 'ডিজিটাল ট্রানজেকশন লিস্ট',
                'url' => config('sheba.api_url') . '/v2/accounting/reports/online-transactions',
                'icon' => config('accounting_entry.icon_url') . '/' . 'digital_transaction_list_report.png'
            ]];
            return array_merge($qr_list, $list);
        }
    }

    public function transactionList($userId, $userType = UserType::PARTNER)
    {
        return $this->client->setUserType($userType)->setUserId($userId)->get($this->api . "accounting-report/payments_report?" . ($this->limit ? "limit={$this->limit}" : "") . ($this->offset ? "&offset={$this->offset}" : "&offset=0")
            . ($this->startDate ? "&start_date={$this->startDate}" : "") . ($this->endDate ? "&end_date={$this->endDate}" : "") . ($this->transactionType ? "&transaction_type={$this->transactionType}" : "")
            . ($this->reconcile ? "&reconcile={$this->reconcile}" : "") . ($this->gateway ? "&gateway={$this->gateway}" : "") . ($this->q ? "&q={$this->q}" : ""));
    }

    public function tranactionDetails($transactionId, $userId, $userType = UserType::PARTNER)
    {
        return $this->client->setUserType($userType)->setUserId($userId)->get('api/entries/online-transactions/' . $transactionId);
    }
}
