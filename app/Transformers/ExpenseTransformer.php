<?php namespace App\Transformers;

use Carbon\Carbon;
use League\Fractal\TransformerAbstract;

class ExpenseTransformer extends TransformerAbstract
{
    /**
     * @param array $expense
     * @return array
     */
    public function transform(array $expense)
    {
        return [
            'id' => $expense['id'],
            "amount" => (double)$expense['amount'],
            "due" => (double)($expense['amount'] - $expense['amount_cleared']),
            "type" => $expense['type'],
            "created_at" => Carbon::parse($expense['created_at'])->format('Y-m-d h:s:i A'),
            "head" => [
                "id" => $expense['head']['id'],
                "name" => [
                    'en' => $expense['head']['name'],
                    'bn' => $expense['head']['name_bn']
                ]
            ],
            "note" => $expense['note']
        ];
    }
}
