<?php namespace Sheba\Reports\Accounting;

use App\Sheba\AccountingEntry\Constants\UserType;
use Sheba\AccountingEntry\Exceptions\AccountingEntryServerError;
use Sheba\AccountingEntry\Repository\AccountingEntryClient;
use App\Sheba\AccountingEntry\Repository\BaseRepository;

class AccountingReportRepository extends BaseRepository
{
    private $api;

    /**
     * AccountingReportRepository constructor.
     * @param AccountingEntryClient $client
     */
    public function __construct(AccountingEntryClient $client)
    {
        parent::__construct($client);
        $this->api = 'api/reports/';
    }

    public function getAccountingReport($reportType, $userId, $startDate, $endDate, $userType = UserType::PARTNER)
    {
        try {
            return $this->client->setUserType($userType)->setUserId($userId)->setReportType($reportType)
                ->get($this->api . 'details_ledger_report?start_date=' . strtotime($startDate) . "&end_date=" . strtotime($endDate) );
        } catch (AccountingEntryServerError $e) {
            throw new AccountingEntryServerError($e->getMessage(), $e->getCode());
        }
    }
}