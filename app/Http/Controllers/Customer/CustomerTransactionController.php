<?php

namespace App\Http\Controllers\Customer;


use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PartnerOrder;
use Illuminate\Http\Request;

class CustomerTransactionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $this->validate($request, [
                'type' => 'sometimes|required|in:credit,debit'
            ]);
            list($offset, $limit) = calculatePagination($request);
            /** @var Customer $customer */
            $customer = $request->customer;
            $transactions = $customer->transactions();
            $bonuses = $customer->bonuses()->whereIn('status', ['used', 'valid'])->get();
            $valid_bonuses = $bonuses->where('status', 'valid');
            $used_bonus_group_by_partner_order_id = $bonuses->where('status', 'used')->groupBy('spent_on_id');
            if ($request->has('type')) $transactions->where('type', ucwords($request->type));
            $transactions = $transactions->select('id', 'customer_id', 'type', 'amount', 'log', 'created_at', 'partner_order_id', 'created_at')->orderBy('id', 'desc')->skip($offset)->take($limit)->get();
            $transactions->each(function ($transaction) use ($customer, $bonuses, $used_bonus_group_by_partner_order_id) {
                $transaction['valid_till'] = null;
                if ($transaction->partnerOrder) {
                    $bonuses_used_on_this_order = $used_bonus_group_by_partner_order_id->get($transaction->partnerOrder->id);
                    if ($bonuses_used_on_this_order) {
                        $transaction['amount'] += $bonuses_used_on_this_order->sum('amount');
                        $used_bonus_group_by_partner_order_id->forget($transaction->partnerOrder->id);
                    }
                    $transaction['category_name'] = $transaction->partnerOrder->jobs->first()->category->name;
                    $transaction['log'] = $transaction['category_name'];
                    $transaction['transaction_type'] = "Service Purchase";
                    $transaction['order_code'] = $transaction->partnerOrder->order->code();
                } else {
                    $transaction['category_name'] = $transaction['transaction_type'] = $transaction['order_code'] = "";
                    $transaction['transaction_type'] = $transaction['log'];
                }
                removeRelationsAndFields($transaction);
            });
            foreach ($used_bonus_group_by_partner_order_id as $bonuses) {
                $transactions = $this->formatDebitBonusTransaction($bonuses, $transactions);
            }
            foreach ($valid_bonuses as $valid_bonus) {
                $transactions = $this->formatCreditBonusTransaction($valid_bonus, $transactions);
            }
            $transactions = $transactions->sortByDesc('created_at')->values()->all();
            return api_response($request, $transactions, 200, [
                'transactions' => $transactions, 'balance' => $customer->shebaCredit(),
                'credit' => round($customer->wallet, 2), 'bonus' => round($customer->shebaBonusCredit(), 2)]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    private function formatDebitBonusTransaction($bonuses, $transactions)
    {
        $bonus = $bonuses[0];
        $category = $bonus->spentOn->jobs->first()->category;
        $transactions->push(array(
            'id' => $bonus->id,
            'customer_id' => $bonus->user_id,
            'type' => 'Debit',
            'amount' => $bonuses->sum('amount'),
            'log' => $category->name,
            'created_at' => $bonus->created_at->toDateTimeString(),
            'partner_order_id' => $bonus->spent_on_id,
            'valid_till' => null,
            'order_code' => $bonus->spentOn->order->code(),
            'transaction_type' => 'Service Purchase',
            'category_name' => $category->name,
        ));
        return $transactions;
    }

    private function formatCreditBonusTransaction($bonus, $transactions)
    {
        $transactions->push(array(
            'id' => $bonus->id,
            'customer_id' => $bonus->user_id,
            'type' => 'Credit',
            'amount' => $bonus->amount,
            'log' => $bonus->log,
            'created_at' => $bonus->created_at->toDateTimeString(),
            'partner_order_id' => null,
            'valid_till' => $bonus->valid_till->format('d/m/Y'),
            'order_code' => '',
            'transaction_type' => $bonus->log,
            'category_name' => '',
        ));
        return $transactions;
    }
}