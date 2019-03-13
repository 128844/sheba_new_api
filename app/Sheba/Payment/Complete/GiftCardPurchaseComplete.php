<?php

namespace Sheba\Payment\Complete;

use App\Models\GiftCard;
use App\Models\GiftCardPurchase;
use Illuminate\Database\QueryException;
use DB;

class GiftCardPurchaseComplete extends PaymentComplete
{
    public function complete()
    {
        try {
            $this->paymentRepository->setPayment($this->payment);
            $model = $this->payment->payable->getPayableModel();
            $payable_model = $model::find((int)$this->payment->payable->type_id);
            DB::transaction(function () use ($payable_model) {
                $gift_card_purchase = GiftCardPurchase::find($this->payment->payable->type_id);
                $gift_card_purchase->status = 'successful';
                $gift_card_purchase->update();
                $this->payment->payable->user->rechargeWallet($payable_model->credit, [
                    'amount' => $payable_model->getPayableModel()->credit, 'transaction_details' => $this->payment->transaction_details,
                    'type' => 'Credit', 'log' => 'Credit Purchase'
                ]);
                $this->paymentRepository->changeStatus(['to' => 'completed', 'from' => $this->payment->status,
                    'transaction_details' => $this->payment->transaction_details]);
                $this->payment->status = 'completed';
                $this->payment->update();
            });
        } catch (QueryException $e) {

            $gift_card_purchase = GiftCardPurchase::find($this->payment->payable->type_id);
            $gift_card_purchase->status = 'failed';
            $gift_card_purchase->update();

            $this->paymentRepository->changeStatus(['to' => 'failed', 'from' => $this->payment->status,
                'transaction_details' => $this->payment->transaction_details]);
            $this->payment->status = 'failed';
            $this->payment->update();
            throw $e;
        }
        return $this->payment;
    }
}