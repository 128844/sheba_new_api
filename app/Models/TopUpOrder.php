<?php namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Sheba\Dal\BaseModel;
use Sheba\Dal\ShebaPayTopupTransactions\ShebaPayTopupTransaction;
use Sheba\Dal\TopupOrder\Events\Saved;
use Sheba\Dal\TopupOrder\FailedReason;
use Sheba\Dal\TopupOrder\Statuses;
use Sheba\Dal\TopUpOrderStatusLog\TopUpOrderStatusLog;
use Sheba\Elasticsearch\ElasticsearchTrait;
use Sheba\Payment\PayableType;
use Sheba\TopUp\Gateway\Names;

class TopUpOrder extends BaseModel implements PayableType
{
    use ElasticsearchTrait;

    protected $guarded = ['id'];
    protected $table = 'topup_orders';
    protected $dates = ['created_at', 'updated_at'];

    public static $savedEventClass = Saved::class;

    /**
     * The elasticsearch settings.
     *
     * @var array
     */
    protected $indexSettings = [
        'analysis' => [
            'char_filter' => [
                'replace' => [
                    'type' => 'mapping', 'mappings' => [
                        '&=> and '
                    ],
                ],
            ],
            'filter' => [
                'word_delimiter' => [
                    'type' => 'word_delimiter', 'split_on_numerics' => false, 'split_on_case_change' => true, 'generate_word_parts' => true, 'generate_number_parts' => true, 'catenate_all' => true, 'preserve_original' => true, 'catenate_numbers' => true,
                ]
            ],
            'analyzer' => [
                'default' => [
                    'type' => 'custom',
                    'char_filter' => [
                        'html_strip', 'replace'
                    ],
                    'tokenizer' => 'whitespace',
                    'filter' => [
                        'lowercase', 'word_delimiter'
                    ]
                ],
                'custom_like_query_analyzer' => [
                    'tokenizer' => 'custom_like_query_tokenizer'
                ]
            ],
            'tokenizer' => [
                'custom_like_query_tokenizer' => [
                    "type" => "ngram",
                    "min_gram" => 2,
                    "max_gram" => 14,
                    "token_chars" => ["letter", "digit"]
                ]
            ]
        ],
        'max_ngram_diff' => 12
    ];

    protected $mappingProperties = [
        'id' => ['type' => 'integer'],
        'payee_mobile_type' => ['type' => 'keyword'],
        'gateway' => ['type' => 'keyword'],
        'sheba_commission' => ['type' => 'double'],
        'agent_commission' => ['type' => 'double'],
        'ambassador_commission' => ['type' => 'double'],
        'vendor_id' => ['type' => 'integer'],
        'status' => ['type' => 'keyword'],
        'transaction_id' => ['type' => 'text'],
        'agent_type' => ['type' => 'keyword'],
        'agent_id' => ['type' => 'integer'],
        'amount' => ['type' => 'double'],
        'payee_mobile' => ['type' => 'text', "analyzer" => "custom_like_query_analyzer", "search_analyzer" => "standard"],
        'payee_name' => ['type' => 'text', "analyzer" => "custom_like_query_analyzer", "search_analyzer" => "standard"],
        'created_at' => ['type' => 'date', "format" => "yyyy-MM-dd HH:mm:ss"],
        'updated_at' => ['type' => 'date', "format" => "yyyy-MM-dd HH:mm:ss"]
    ];

    public function getIndexName(): string
    {
        return $this->getTable();
    }

    public function agent()
    {
        return $this->morphTo();
    }

    public function vendor()
    {
        return $this->belongsTo(TopUpVendor::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(TopUpOrderStatusLog::class, 'topup_order_id');
    }

    public function scopeProcessed($query)
    {
        return $query->statuses(Statuses::getProcessed());
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeStatuses($query, $statuses)
    {
        return $query->whereIn('status', $statuses);
    }

    public function scopeSuccessful($query)
    {
        return $query->status(Statuses::SUCCESSFUL);
    }

    public function scopeCanRefreshQuery($query)
    {
        return $query->statuses([Statuses::PENDING, Statuses::ATTEMPTED]);
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function scopeOperator($query, $vendor_id)
    {
        return $query->where('vendor_id', $vendor_id);
    }

    public function scopeGateway($query, $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    public function getAgentNameAttribute()
    {
        if ($this->isAgentPartner()) return $this->agent->name;
        elseif ($this->isAgentAffiliate()) return $this->agent->profile->name;
    }

    public function getAgentMobileAttribute()
    {
        if ($this->isAgentPartner()) return $this->agent->contact_no;
        elseif ($this->isAgentAffiliate()) return $this->agent->profile->mobile;
    }

    public function isAgentPartner(): bool
    {
        return $this->agent_type == Partner::class;
    }

    public function isAgentAffiliate(): bool
    {
        return $this->agent_type == Affiliate::class;
    }

    public function isFailed(): bool
    {
        return $this->status == Statuses::FAILED;
    }

    public function isFailedDueToGatewayTimeout(): bool
    {
        return $this->isFailed() && $this->failed_reason == FailedReason::GATEWAY_TIMEOUT;
    }

    public function isSuccess(): bool
    {
        return $this->status == Statuses::SUCCESSFUL;
    }

    public function isPending(): bool
    {
        return $this->status == Statuses::PENDING;
    }

    public function isAttempted(): bool
    {
        return $this->status == Statuses::ATTEMPTED;
    }

    public function isProcessed(): bool
    {
        return $this->isFailed() || $this->isSuccess();
    }

    public function canRefresh(): bool
    {
        return $this->isPending() || $this->isAttempted();
    }

    public function getStatusForAgent(): string
    {
        return Statuses::getForAgent($this->status);
    }

    public function getOriginalMobile(): string
    {
        return getOriginalMobileNumber($this->payee_mobile);
    }

    public function isRobiWalletTopUp(): bool
    {
        return !!$this->is_robi_topup_wallet;
    }

    public function isAgentDebited(): bool
    {
        return (boolean)$this->is_agent_debited;
    }

    public function isViaPaywell(): bool
    {
        return $this->gateway == Names::PAYWELL;
    }

    public function isViaSsl(): bool
    {
        return $this->gateway == Names::SSL;
    }

    public function isViaPretups(): bool
    {
        return in_array($this->gateway, [Names::ROBI, Names::AIRTEL, Names::BANGLALINK]);
    }

    public function isViaBdRecharge(): bool
    {
        return $this->gateway == Names::BD_RECHARGE;
    }

    public function isViaPayStation(): bool
    {
        return $this->gateway == Names::PAY_STATION;
    }

    public function getTransactionDetailsObject()
    {
        return json_decode($this->transaction_details);
    }

    private function isUsingUniformGatewayRef(): bool
    {
        return $this->id > config('topup.non_uniform_gateway_ref_last_id');
    }

    public function getGatewayRefId()
    {
        if ($this->isUsingUniformGatewayRef()) return dechex($this->id);

        if ($this->isViaPaywell()) return $this->id;

        if ($this->isViaPretups()) return "";

        if ($this->isViaSsl()) return $this->getTransactionDetailsObject()->guid;

        if ($this->isViaBdRecharge()) return $this->id;

        return "";
    }

    public static function getIdFromUniformGatewayRefId($ref_id): int
    {
        return hexdec($ref_id);
    }

    public function isShebaPayOrder(): bool
    {
        return $this->originated_from === 'sheba_pay';
    }

    public function shebaPayTransaction(): HasOne
    {
        return $this->hasOne(ShebaPayTopupTransaction::class, 'topup_order_id');
    }
}
