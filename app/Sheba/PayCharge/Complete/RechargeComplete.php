<?php

namespace Sheba\PayCharge\Complete;


use App\Sheba\PayCharge\Rechargable;
use Illuminate\Database\QueryException;
use Sheba\PayCharge\PayChargable;
use DB;

class RechargeComplete extends PayChargeComplete
{

    public function complete(PayChargable $pay_chargable, $method_response)
    {
        try {
            $class_name = $pay_chargable->userType;
            /** @var Rechargable $user */
            $user = $class_name::find($pay_chargable->userId);
            DB::transaction(function () use ($pay_chargable, $method_response, $user) {
                $user->rechargeWallet($pay_chargable->amount, [
                    'amount' => $pay_chargable->amount, 'transaction_details' => $method_response,
                    'type' => 'Credit', 'log' => "$pay_chargable->amount BDT has been recharged to your Sheba Credit."
                ]);
            });
        } catch (QueryException $e) {
            app('sentry')->captureException($e);
            return null;
        }
        return true;
    }
}