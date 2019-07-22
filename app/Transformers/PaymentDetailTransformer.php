<?php namespace App\Transformers;

use Carbon\Carbon;
use League\Fractal\TransformerAbstract;

class PaymentDetailTransformer extends TransformerAbstract
{
    public function transform($payment, $payment_detail, $payment_link_payment_details)
    {
        return [
            'customer_name' => $payment->payable->user->name,
            'customer_number' => $payment->payable->getMobile(),
            'payment_type' => $payment_detail->readableMethod,
            'id' => $payment->id,
            'payment_code' => '#' . $payment->id,
            'amount' => $payment->payable->amount,
            'created_at' => Carbon::parse($payment->created_at)->format('Y-m-d h:i a'),
            'link' => $payment_link_payment_details['link'],
            'link_code' => '#' . $payment_link_payment_details['linkId'],
            'purpose' => $payment_link_payment_details['reason'],
            'status' => $payment_link_payment_details['isActive'] == 1 ? 'active' : 'inactive'
        ];
    }
}