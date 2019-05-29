<?php namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\ModificationFields;
use Sheba\Voucher\DTO\Params\CheckParamsForOrder;
use Sheba\Voucher\PromotionList;
use Sheba\Voucher\VoucherDiscount;

class CustomerJobController extends Controller
{
    use ModificationFields;

    public function addPromotion($customer, $job, Request $request, CheckParamsForOrder $order_params)
    {
        try {
            $this->validate($request, [
                'code' => 'required|string',
                'sales_channel' => 'required|string',
            ]);
            $job = $request->job;
            $order = $job->partnerOrder->order;
            $customer = $request->customer;
            $order_params->setCategory($order->category)->setCustomer($customer)->setLocation($order->location)->setOrder($order);
            $result = voucher(strtoupper($request->code))->checkForOrder($order_params)->reveal();
            if ($result['is_valid']) {
                $voucher = $result['voucher'];
                $promo = (new PromotionList($customer))->add($voucher);
                if (!$promo[0]) return api_response($request, null, 403, ['message' => $promo[1]]);
                try {
                    DB::transaction(function () use ($order, $voucher, $job) {
                        $order->update($this->withUpdateModificationField(['voucher_id' => $voucher->id]));
                        $voucherDiscount = new VoucherDiscount();
                        $amount = $voucherDiscount->setVoucher($voucher)->calculate($order_amount);
                        $discount_percentage = $voucher->is_amount_percentage ? $voucher->amount : null;
                        $total_price = $job->partnerOrder->calculate(true)->totalPrice;
                        $voucher_data = [
                            'discount' => ($amount > $total_price) ? $total_price : $amount,
                            'discount_percentage' => $discount_percentage,
                            'sheba_contribution' => $voucher->sheba_contribution,
                            'partner_contribution' => $voucher->partner_contribution
                        ];
                        $job->update($this->withUpdateModificationField($voucher_data));
                    });
                } catch (QueryException $e) {
                    throw $e;
                }
                return api_response($request, 1, 200, ['voucher' => $voucher]);
            } else {
                return api_response($request, null, 403, ['message' => 'Invalid Promo']);
            }
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all()]);
            $sentry->captureException($e);
            return api_response($request, null, 500);
        }


    }
}