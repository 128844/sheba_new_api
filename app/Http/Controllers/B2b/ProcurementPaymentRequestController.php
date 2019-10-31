<?php namespace App\Http\Controllers\B2b;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Sheba\Business\ProcurementPaymentRequest\Creator;
use Sheba\Business\ProcurementPaymentRequest\Updater;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use Sheba\ModificationFields;
use Illuminate\Http\Request;

class ProcurementPaymentRequestController extends Controller
{
    use ModificationFields;

    public function updatePaymentRequest($business, $procurement, $bid, $payment_request, Request $request, Updater $updater)
    {
        try {
            $this->validate($request, [
                'note' => 'sometimes|string',
                'status' => 'sometimes|string'
            ]);
            $this->setModifier($request->manager_member);
            $updater->setProcurement($procurement)->setBid($bid);
            $updater = $updater->setPaymentRequest($payment_request)->setNote($request->note)
                ->setStatus($request->status);
            $payment_request = $updater->paymentRequestUpdate();
            return api_response($request, $payment_request, 200);
        } catch (ModelNotFoundException $e) {
            return api_response($request, null, 404, ["message" => "Model Not found."]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function show($partner, $procurement, $bid, $payment_request, Request $request, Creator $creator)
    {
        try {
            $creator->setProcurement($procurement)->setBid($bid)->setPaymentRequest($payment_request);
            $payment_request_details = $creator->getPaymentRequestData();
            return api_response($request, $payment_request_details, 200, ['payment_request_details' => $payment_request_details]);
        } catch (ModelNotFoundException $e) {
            return api_response($request, null, 404, ["message" => "Model Not found."]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }
}