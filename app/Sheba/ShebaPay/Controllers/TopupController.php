<?php namespace Sheba\ShebaPay\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Request;
use App\Models\TopUpOrder;
use Exception;
use Illuminate\Http\JsonResponse;
use Sheba\Dal\TopupOrder\TopUpOrderRepository;
use Sheba\ShebaPay\Helpers\OrderData;
use Sheba\ShebaPay\Requests\ShebaPayTopupRequest;
use Sheba\ShebaPay\Requests\ShebaPayTopupStatusRequest;
use Sheba\TopUp\Creator;
use Sheba\TopUp\Jobs\TopUpJob;
use Sheba\TopUp\TopUpAgentBlocker;
use Sheba\TopUp\TopUpRequest;
use Sheba\UserAgentInformation;

class TopupController extends Controller
{
    /**
     * @throws Exception
     */
    public function topup(ShebaPayTopupRequest $request, TopUpRequest $top_up_request, Creator $creator, UserAgentInformation $userAgentInformation, TopUpAgentBlocker $agent_blocker): JsonResponse
    {
        $agent = $request->getAgent();
        $userAgentInformation->setRequest($request);
        $top_up_request->setAmount($request->amount)
            ->setMobile($request->mobile)
            ->setType($request->connection_type)
            ->setAgent($agent)
            ->setVendorId($request->vendor_id)
            ->setLat($request->lat ?: null)
            ->setLong($request->long ?: null)
            ->setUserAgent($userAgentInformation->getUserAgent())
            ->setIsOtfAllow(!$request->is_otf_allow)
            ->setShebaPayRequest(true)
            ->setMsiddn($request->msisdn)
            ->setCallbackUrl($request->callback_url)
            ->setShebaPayTransactionId($request->transaction_id);

        if ($top_up_request->hasError()) {
            return api_response($request, null, $top_up_request->getErrorCode(), ['message' => $top_up_request->getErrorMessage()]);
        }

        $topup_order = $creator->setTopUpRequest($top_up_request)->create();

        if (!$topup_order) return api_response($request, null, 500);

        $agent_blocker->setAgent($agent)->checkAndBlock();

        dispatch((new TopUpJob($topup_order)));
        return api_response($request, null, 200, ['message' => "Recharge Request Successful", 'data' => (new OrderData($topup_order))->get()]);
    }

    public function status(ShebaPayTopupStatusRequest $request, TopUpOrderRepository $repository): JsonResponse
    {
        /** @var TopUpOrder $order */
        $order = $repository->find($request->get('topup_order_id'));
        if ($order->agent->id != $request->getAgent()->id) return api_response($request, null, 403, ['message' => 'You don not have access to this order']);
        $data = (new OrderData($order))->get();
        return api_response($request, $data, 200, ['data' => $data]);
    }
}