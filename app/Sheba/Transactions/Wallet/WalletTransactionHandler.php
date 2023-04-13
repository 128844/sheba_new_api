<?php namespace Sheba\Transactions\Wallet;

use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\PartnerTransactionCategory;
use App\Models\Resource;
use App\Models\WithdrawalRequest;
use App\Sheba\DueTracker\Exceptions\InsufficientBalance;
use App\Sheba\Transactions\Wallet\WalletUnexpectedException;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Sheba\FraudDetection\Exceptions\FraudDetectionServerError;
use Sheba\FraudDetection\Repository\TransactionRepository;
use Sheba\ModificationFields;
use Sheba\RequestIdentification;
use Sheba\Transactions\Types;
use Sheba\Transactions\UserThirdPartyTransaction;
use Sheba\Transactions\Wallet\Jobs\FraudTransactionJob;
use Sheba\Wallet\WalletUpdateEvent;
use Throwable;

class WalletTransactionHandler extends WalletTransaction
{
    use ModificationFields;
    use UserThirdPartyTransaction;

    protected $amount;
    protected $log;
    protected $type;
    /** @var TransactionDetails $transaction_details */
    protected $transaction_details;
    private   $source;
    private   $isNegativeDebitAllowed = false;
    protected $category;

    /**
     * @param bool $isNegativeDebitAllowed
     * @return WalletTransactionHandler
     */
    public function setIsNegativeDebitAllowed(bool $isNegativeDebitAllowed): WalletTransactionHandler
    {
        $this->isNegativeDebitAllowed = $isNegativeDebitAllowed;
        return $this;
    }

    /**
     * @param array $extras
     * @param bool $isJob
     * @return Model
     * @throws WalletDebitForbiddenException
     * @throws WalletUnexpectedException
     */
    public function store($extras = [], $isJob = false)
    {
        try {
            if (empty($this->type) || empty($this->amount) || empty($this->model)) {
                throw new InvalidWalletTransaction();
            }
            if (!$isJob) {
                $extras = $this->withCreateModificationField((new RequestIdentification())->set($extras));
            }
            if ($this->type == Types::debit() && !$this->isNegativeDebitAllowed && $this->model instanceof Partner) {
                self::isDebitTransactionAllowed($this->model, $this->amount);
            }

            return $this->storeTransaction($extras);
        } catch (WalletDebitForbiddenException $e) {
            throw new WalletDebitForbiddenException($e->getMessage(), $e->getCode());
        } catch (Throwable $e) {
            // WalletTransaction::throwException($e);
            throw new WalletUnexpectedException($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param array $data
     * @return Model
     */
    private function storeTransaction($data = [])
    {
        /** @var Model $transaction */
        $transaction = null;
        /** @noinspection PhpUndefinedMethodInspection */
        DB::transaction(function () use ($data, &$transaction) {
            $typeMethod = sprintf("%sWallet", $this->type);
            $wallet     = $this->$typeMethod();
            $data       = array_merge($data, [
                'type'                       => ucfirst($this->type),
                'amount'                     => $this->amount,
                'balance'                    => $wallet,
                'log'                        => $this->log,
                'created_at'                 => Carbon::now(),
                'third_party_transaction_id' => $this->transaction_details ? $this->thirdPartyTransactionId($this->transaction_details->toString()) : null,
                'transaction_details'        => $this->transaction_details ? $this->transaction_details->toString() : null
            ]);

            $transaction_data = $this->getTransactionClass()->fill($data);
            $transaction      = $this->model->transactions()->save($transaction_data);
            if ($this->model instanceof Partner && $transaction instanceof PartnerTransaction) {
                $transaction->category()->save(new PartnerTransactionCategory(["category" => $this->getTransactionCategory($transaction)]));
            }

        });
        event(new WalletUpdateEvent([
            'amount'    => $this->model->fresh()->wallet,
            'user_type' => strtolower(class_basename($this->model)),
            'user_id'   => $this->model->id
        ]));

        return $transaction;
    }

    /**
     * @param mixed $category
     * @return WalletTransactionHandler
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    private function getTransactionCategory(PartnerTransaction $transaction)
    {
        if (!empty($this->category)) return $this->category;
        if (str_contains($transaction->log, "topped up") || strContainsAll($transaction->log, ["recharge", "failed", "refunded"])) return "TopUP";
        if (strContainsAny($transaction->log, ["Credit Purchase", "gateway charge"])) return "Purchase";
        if ((strContainsAny($transaction->log, ["Partner collect", "reward #","Credited for Order ID"]))) return "sheba.xyz";
        if (str_contains($transaction->log, "subscription package")) return "Subscription";
        return "other";
    }

    /**
     * @return Model
     */
    private function getTransactionClass()
    {
        if ($this->model instanceof Resource) return new \Sheba\Dal\ResourceTransaction\Model();
        $name = class_basename($this->model);
        return app('App\\Models\\' . $name . 'Transaction');
    }

    /**
     * @param bool $isJob
     * @throws FraudDetectionServerError
     */
    private function storeFraudDetectionTransaction($isJob = true)
    {
        /**
         * TEMPORARY OFF FRAUD TRANSACTION
         */
        return;

        /** @noinspection PhpUndefinedFieldInspection */
        $data = [
            'user_type'      => strtolower(class_basename($this->model)),
            'user_id'        => $this->model->id,
            'user_name'      => $this->getName(),
            'source'         => $this->source,
            'type'           => $this->type,
            'log'            => $this->log,
            'detail'         => $this->transaction_details ? $this->transaction_details->toString() : null,
            'gateway'        => $this->transaction_details ? $this->transaction_details->getGateway() : null,
            'gateway_trx_id' => $this->transaction_details ? $this->transaction_details->getTransactionID() : null,
            'amount'         => $this->amount,
            'created_at'     => Carbon::now()->format('Y-m-d H:i:s')
        ];
        if ($isJob) {
            dispatch((new FraudTransactionJob())->setData($data));
        } else {
            $fraudTransactionRepo = new TransactionRepository();
            $fraudTransactionRepo->store($data);
        }
    }

    /**
     * @return string|null
     */
    private function getName()
    {
        return !is_null($this->model->name) ? $this->model->name : (!is_null($this->model->profile) ? $this->model->profile->name : null);
    }

    public function storeFraudOnly()
    {
        try {
            if (empty($this->type) || empty($this->amount) || empty($this->model)) {
                throw new InvalidWalletTransaction();
            }
            $this->storeFraudDetectionTransaction(true);
        } catch (Exception $e) {
            WalletTransaction::throwException($e);
        }
    }

    /**
     * @param mixed $source
     * @return WalletTransactionHandler
     */
    public function setSource($source)
    {
        $this->source = $source;
        return $this;
    }

    /**
     * @param array $data
     * @param bool $isJob
     * @return Model
     */
    public function recharge($data = [], $isJob = false)
    {
        try {
            if (empty($this->amount) || empty($this->model)) {
                throw new InvalidWalletTransaction();
            }
            $transaction = $this->setType(Types::credit())->storeTransaction($data);
            $this->storeFraudDetectionTransaction(!$isJob);
            return $transaction;
        } catch (Exception $e) {
            WalletTransaction::throwException($e);
        }

        return null;
    }

    /**
     * @param mixed $type
     * @return WalletTransactionHandler
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @param mixed $log
     * @return WalletTransactionHandler
     */
    public function setLog($log)
    {
        $this->log = $log;
        return $this;
    }

    /**
     * @param mixed $amount
     * @return WalletTransactionHandler
     */
    public function setAmount($amount)
    {
        $this->amount = round($amount, 2);
        return $this;
    }

    /**
     * @param mixed $transaction_details
     * @return WalletTransactionHandler
     */
    public function setTransactionDetails(array $transaction_details)
    {
        $this->transaction_details = TransactionDetails::generateDetails($transaction_details);
        return $this;
    }

    /**
     * DISPATCH TRANSACTION STORE JOB
     * @param array $extras
     * @throws WalletDebitForbiddenException
     * @throws WalletUnexpectedException
     */
    public function dispatch($extras = [])
    {
        /*$extras = $this->withCreateModificationField((new RequestIdentification())->set($extras));*/
        /*dispatch((new WalletTransactionJob($this))->setExtras($extras));*/
        $this->store($extras);
    }

    /**
     * @return float
     */
    private function getCalculatedBalance()
    {
        $last_inserted_transaction = $this->model->transactions()->orderBy('id', 'desc')->first();
        $last_inserted_balance     = $last_inserted_transaction ? $last_inserted_transaction->balance : 0.00;
        return strtolower($this->type) == 'credit' ? $last_inserted_balance + $this->amount : $last_inserted_balance - $this->amount;
    }

    /**
     * @param Partner $partner
     * @param $amount
     * @param null $reason
     * @throws WalletDebitForbiddenException|InsufficientBalance
     */
    public static function isDebitTransactionAllowed(Partner $partner, $amount, $reason = null)
    {
        if ((double)$partner->wallet < $amount) {
            throw new InsufficientBalance();
        }
        $withdrawalRequests   = $partner->walletSetting->pending_withdrawal_amount;
        $remainingAmount      = $partner->wallet - (float)$withdrawalRequests;
        $withdrawalRequestsBn = convertNumbersToBangla($withdrawalRequests, true, 0);
        if ($withdrawalRequests > 0 && $amount > $remainingAmount) {
            $message = sprintf("<center>আপনি <b> %s </b> টাকা উত্তোলনের জন্য আবেদন করেছেন, একারনে %s জন্য পর্যাপ্ত ব্যালেন্স নেই। অনুগ্রহ করে সেবা ক্রেডিট রিচার্জ করে পুনরায় চেষ্টা করুন।</center>", $withdrawalRequestsBn, $reason);
            throw new WalletDebitForbiddenException($message, 406);
        }
    }
}
