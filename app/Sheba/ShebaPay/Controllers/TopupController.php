<?php namespace Sheba\ShebaPay\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Sheba\ShebaPay\Requests\ShebaPayTopupRequest;
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
        $agent=$request->getAgent();
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

        return api_response($request, null, 200, ['message' => "Recharge Request Successful", 'id' => $topup_order->id]);
    }

}