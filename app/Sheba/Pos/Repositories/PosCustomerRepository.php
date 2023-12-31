<?php namespace Sheba\Pos\Repositories;

use App\Models\Partner;
use App\Models\PosCustomer;
use App\Sheba\AccountingEntry\Repository\AccountingDueTrackerRepository;
use Sheba\AccountingEntry\Exceptions\AccountingEntryServerError;
use Sheba\DueTracker\DueTrackerRepository;
use Sheba\DueTracker\Exceptions\InvalidPartnerPosCustomer;
use Sheba\Repositories\BaseRepository;
use Throwable;

class PosCustomerRepository extends BaseRepository
{
    /**
     * @param array $data
     * @return PosCustomer
     */
    public function save(array $data)
    {
        return PosCustomer::create($this->withCreateModificationField($data));
    }

    /**
     * @param Partner $partner
     * @param $customerId
     * @param $request
     * @return int[]
     * @throws AccountingEntryServerError
     * @throws InvalidPartnerPosCustomer
     * @throws \Sheba\ExpenseTracker\Exceptions\ExpenseTrackingServerError
     */
    public function getDueAmountFromDueTracker(Partner $partner, $customerId): array
    {
        $response = [
            'due' => null,
            'payable' => null
        ];
        try {
            /** @var AccountingDueTrackerRepository $accDueTrackerRepository */
            $accDueTrackerRepository = app(AccountingDueTrackerRepository::class);
            // checking the partner is migrated to accounting
            if ($accDueTrackerRepository->isMigratedToAccounting($partner->id)) {
                $data = $accDueTrackerRepository->setPartner($partner)->dueListBalanceByCustomer($customerId);
            } else {
                /** @var DueTrackerRepository $dueTrackerRepo */
                $dueTrackerRepo = app(DueTrackerRepository::class);
                $data = $dueTrackerRepo->setPartner($partner)->getDueListByProfile($partner, $customerId);
            }
            if ($data['balance']['type'] === 'receivable') {
                $response['due'] = $data['balance']['amount'];
            }
            else {
                $response['payable'] = $data['balance']['amount'];
            }
            return $response;
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return $response;
        }
    }

    public function deleteCustomerFromDueTracker(Partner $partner, $customerId)
    {
        /** @var AccountingDueTrackerRepository $accDueTrackerRepository */
        $accDueTrackerRepository = app(AccountingDueTrackerRepository::class);
        $accDueTrackerRepository->setPartner($partner)->deleteCustomer($customerId);

    }
}
