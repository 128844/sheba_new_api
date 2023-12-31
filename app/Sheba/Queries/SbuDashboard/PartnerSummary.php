<?php
/**
 * Created by PhpStorm.
 * User: Tech Land
 * Date: 10/27/2018
 * Time: 3:43 PM
 */

namespace App\Sheba\Queries\SbuDashboard;


use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PartnerSummary
{
    private $today, $table;
    private $logTable;
    private $orderTable;
    private $partnerTransactionTable;

    public function __construct()
    {
        $this->today = Carbon::today()->toDateString();
        $this->table = DB::table('partners');
        $this->logTable = DB::table('partner_status_change_logs');
        $this->orderTable = DB::table('orders');
        $this->partnerTransactionTable = DB::table('partner_transactions');
    }

    public function verifiedToday()
    {
        $date = $this->today;
        $data = $this->logTable
            ->selectRaw('count(distinct(partner_id)) as total')
            ->where('to', '=', constants('PARTNER_STATUSES')['Verified'])
            ->whereNotExists(function ($query) use ($date) {
                $query->from('partner_status_change_logs as p')
                    ->where('p.to', '=', constants('PARTNER_STATUSES')['Verified'])
                    ->whereRaw('p.partner_id=partner_status_change_logs.partner_id')
                    ->where('p.created_at', '<', $date);
            })
            ->where('created_at', '>=', $this->today)
            ->get();
        return $data;
    }

    public function onBoardToday()
    {
        $data = $this->table
            ->where('status', '=', constants('PARTNER_STATUSES')['Onboarded'])
            ->where('created_at', '>=', $this->today)
            ->selectRaw('count(*) as total')
            ->get();
        return $data;
    }

    public function active()
    {
        $data = $this->orderTable
            ->leftJoin('partner_orders', 'partner_orders.order_id', '=', 'orders.id')
            ->where('orders.created_at', '>=', $this->today)
            ->selectRaw('count(distinct(partner_orders.partner_id)) as total')
            ->get();
        return $data;
    }

    public function totalWalletRecharge()
    {
        $data = $this->partnerTransactionTable
            ->where('log', 'like', '%paid to SHEBA%')
            ->where('type', '=', 'Credit')
            ->where('transaction_details', 'LIKE', '%bkash%')
            ->where('created_at', '>=', $this->today)
            ->selectRaw('sum(amount) as total')
            ->get();
        return $data;
    }
}