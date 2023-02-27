<?php

namespace App\Http\Controllers\ResellerPayment;

use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use Sheba\NeoBanking\Exceptions\NeoBankingException;
use Sheba\NeoBanking\Exceptions\UnauthorizedRequestFromSBSException;
use Sheba\Payment\Exceptions\InvalidConfigurationException;
use Sheba\ResellerPayment\Exceptions\ResellerPaymentException;
use Sheba\ResellerPayment\Exceptions\StoreValidationException;
use Sheba\ResellerPayment\Statics\StoreConfigurationStatic;
use Sheba\ResellerPayment\StoreConfiguration;

class StoreConfigurationController extends Controller
{
    private $storeConfiguration;

    public function __construct(StoreConfiguration $configuration)
    {
        $this->storeConfiguration = $configuration;
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function get(Request $request): JsonResponse
    {
        try {
            $this->validate($request, ["key" => "required"]);
            $configuration = $this->storeConfiguration->setPartner($request->partner)->setKey($request->key)->getConfiguration();

            return api_response($request, $configuration, 200, ['data' => $configuration]);
        } catch (ResellerPaymentException $e) {
            logError($e);
            return api_response($request, null, $e->getCode(), ['message' => $e->getMessage()]);
        }
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getV2(Request $request): JsonResponse
    {
        try {
            $this->validate($request, ["key" => "required"]);
            $configuration = $this->storeConfiguration->setPartner($request->partner)->setKey($request->key)->getConfiguration();
            $data = StoreConfigurationStatic::storeConfigurationGetResponse($configuration);
            return api_response($request, $configuration, 200, ['data' => $data]);
        } catch (ResellerPaymentException $e) {
            logError($e);
            return api_response($request, null, $e->getCode(), ['message' => $e->getMessage()]);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $this->validate($request, StoreConfigurationStatic::validateStoreConfigurationPost());
            $this->storeConfiguration->setPartner($request->partner)->setKey($request->key)
                ->setGatewayId($request->gateway_id)->setRequestData($request->configuration_data)->storeConfiguration();
            return api_response($request, null, 200);
        } catch (InvalidConfigurationException $e) {
            return api_response($request, null, $e->getCode(), ['message' => $e->getMessage()]);
        } catch (ResellerPaymentException $e) {
            return api_response($request, null, $e->getCode(), ['message' => $e->getMessage()]);
        } catch (ValidationException $e) {
            $msg = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, null, 400, ['message' => $msg]);
        }
    }

    public function statusUpdate(Request $request): JsonResponse
    {
        try {
            $this->validate($request, StoreConfigurationStatic::statusUpdateValidation());
            $this->storeConfiguration->setGatewayId($request->gateway_id)
                ->setPartner($request->partner)
                ->setKey($request->key)
                ->updatePaymentGatewayStatus($request->status);

            return api_response($request, null, 200);
        } catch (ResellerPaymentException $e) {
            logError($e);
            return api_response($request, null, $e->getCode(), ['message' => $e->getMessage()]);
        }
    }


    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function storeByMor(Request $request): JsonResponse
    {
        try {
            if (($request->header('access-key')) !== config('neo_banking.sbs_access_token')) {
                throw new UnauthorizedRequestFromSBSException();
            }

            $this->validate($request, StoreConfigurationStatic::validateStoreConfigurationPost());

            $partner = Partner::find($request->partner_id);
            $this->storeConfiguration
                ->setPartner($partner)
                ->setKey($request->key)
                ->setGatewayId($request->gateway_id)
                ->setRequestData($request->configuration_data)
                ->storeConfiguration();

            return api_response($request, null, 200);
        } catch (UnauthorizedRequestFromSBSException $exception) {
            return api_response($request, null, 403);
        } catch (NeoBankingException $exception) {
            logError($exception);
            return api_response($request, null, $exception->getCode());
        } catch (InvalidConfigurationException $e) {
            return api_response($request, null, $e->getCode(), ['message' => $e->getMessage()]);
        } catch (ResellerPaymentException $e) {
            return api_response($request, null, $e->getCode(), ['message' => $e->getMessage()]);
        } catch (ValidationException $e) {
            $msg = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, null, 400, ['message' => $msg]);
        }
    }
}
