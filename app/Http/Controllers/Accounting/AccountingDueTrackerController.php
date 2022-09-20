<?php namespace App\Http\Controllers\Accounting;


use App\Http\Controllers\Controller;
use App\Sheba\AccountingEntry\Repository\AccountingDueTrackerRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mpdf\MpdfException;
use Sheba\AccountingEntry\Exceptions\AccountingEntryServerError;
use Sheba\DueTracker\Exceptions\InvalidPartnerPosCustomer;
use Sheba\ExpenseTracker\Exceptions\ExpenseTrackingServerError;
use Sheba\ModificationFields;
use Sheba\Reports\Exceptions\NotAssociativeArray;
use Sheba\Reports\PdfHandler;
use Sheba\Usage\Usage;

class AccountingDueTrackerController extends Controller
{
    use ModificationFields;

    /** @var AccountingDueTrackerRepository */
    private $dueTrackerRepo;

    public function __construct(AccountingDueTrackerRepository $dueTrackerRepo)
    {
        $this->dueTrackerRepo = $dueTrackerRepo;
    }

    /**
     * @throws AccountingEntryServerError
     */
    public function store(Request $request): JsonResponse
    {
        $this->validate($request, [
            'amount'                => 'required',
            'entry_type'            => 'required|in:due,deposit',
            'account_key'           => 'sometimes|string',
            'customer_id'           => 'required',
            'date'                  => 'required|date_format:Y-m-d H:i:s',
            'partner_wise_order_id' => 'sometimes|numeric',
            'attachments'           => 'sometimes|array',
            'attachments.*'         => 'sometimes|mimes:jpg,jpeg,png,bmp|max:20000'
        ]);
        $response = $this->dueTrackerRepo->setPartner($request->partner)->storeEntry($request, $request->entry_type);
        (new Usage())->setUser($request->partner)->setType(Usage::Partner()::DUE_TRACKER_TRANSACTION)->create($request->auth_user);
        return api_response($request, $response, 200, ['data' => $response]);
    }

    /**
     * @throws AccountingEntryServerError
     */
    public function update(Request $request, $entry_id): JsonResponse
    {
        $this->validate($request, [
            'amount'                   => 'required',
            'entry_type'               => 'required|in:due,deposit',
            'account_key'              => 'required',
            'customer_id'              => 'required',
            'date'                     => 'required|date_format:Y-m-d H:i:s',
            'attachment_should_remove' => 'sometimes|array',
            'attachments'              => 'sometimes|array',
            'attachments.*'            => 'sometimes|mimes:jpg,jpeg,png,bmp|max:20000'
        ]);
        $request->merge(['entry_id' => $entry_id]);
        $response = $this->dueTrackerRepo->setPartner($request->partner)->storeEntry($request, $request->entry_type, true);
        (new Usage())->setUser($request->partner)->setType(Usage::Partner()::DUE_ENTRY_UPDATE)->create($request->auth_user);
        return api_response($request, $response, 200, ['data' => $response]);
    }

    /**
     * @throws MpdfException
     * @throws \Throwable
     * @throws AccountingEntryServerError
     * @throws NotAssociativeArray
     */
    public function dueList(Request $request): JsonResponse
    {
        $data = $this->dueTrackerRepo->setPartner($request->partner)->getDueList($request);
        if ((($request->has('download_pdf')) && ($request->download_pdf == 1)) ||
            (($request->has('share_pdf')) && ($request->share_pdf == 1))) {
            $data['start_date'] = $request->has("start_date") ? $request->start_date : null;
            $data['end_date']   = $request->has("end_date") ? $request->end_date : null;
            $balanceData        = $this->dueTrackerRepo->setPartner($request->partner)->getDuelistBalance($request);
            $data               = array_merge($data, $balanceData);
            $pdf_link           = (new PdfHandler())->setName("due tracker")->setData($data)->setViewFile(
                'due_tracker_due_list'
            )->save(true);
            if (($request->has('download_pdf')) && ($request->download_pdf == 1)) {
                return api_response(
                    $request,
                    null,
                    200,
                    ['message' => 'PDF download successful', 'pdf_link' => $pdf_link]
                );
            }
            $data['pdf_link'] = $pdf_link;
        }

        return api_response($request, null, 200, ['data' => $data]);
    }

    /**
     * @throws AccountingEntryServerError
     */
    public function dueListBalance(Request $request): JsonResponse
    {
        $cache_key = "PartnerDueListBalance::partner:".$request->partner->id;
        return Cache::store('redis')->remember($cache_key, 5, function () use ($request) {
            $data = $this->dueTrackerRepo->setPartner($request->partner)->getDuelistBalance($request);
            return api_response($request, null, 200, ['data' => $data]);
        });
    }

    /**
     * @param Request $request
     * @param $customerId
     * @return JsonResponse
     * @throws \Throwable
     */
    public function dueListByCustomerId(Request $request, $customerId): JsonResponse
    {
        $data = $this->dueTrackerRepo->setPartner($request->partner)->getDueListByCustomer($request, $customerId);

        if ((($request->has('download_pdf')) && ($request->download_pdf == 1)) ||
            (($request->has('share_pdf')) && ($request->share_pdf == 1))) {
            $data['start_date'] = $request->has("start_date") ? $request->start_date : null;
            $data['end_date']   = $request->has("end_date") ? $request->end_date : null;
            $balanceData        = $this->dueTrackerRepo->setPartner($request->partner)->dueListBalanceByCustomer($customerId,$request);
            $data               = array_merge($data, $balanceData);
            $pdf_link           = (new PdfHandler())->setName("due tracker by customer")->setData($data)->setViewFile('due_tracker_due_list_by_customer')->save(true);
            if (($request->has('download_pdf')) && ($request->download_pdf == 1)) {
                return api_response($request, null, 200, ['message' => 'PDF download successful', 'link' => $pdf_link]);
            }
            $data['pdf_link'] = $pdf_link;
        }
        return api_response($request, null, 200, ['data' => $data]);
    }

    /**
     * @throws InvalidPartnerPosCustomer
     * @throws AccountingEntryServerError
     */
    public function dueListBalanceByCustomer(Request $request, $customerId): JsonResponse
    {
        $data = $this->dueTrackerRepo->setPartner($request->partner)->dueListBalanceByCustomer($customerId,$request);
        return api_response($request, null, 200, ['data' => $data]);
    }
}