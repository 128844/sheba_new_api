<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerTransactionCategory extends Model
{
    protected $table = "partner_transaction_category";
    protected $fillable = ["category"];
    public $timestamps = false;
    public function transaction()
    {
        return $this->belongsTo(PartnerTransaction::class, "partner_transaction_id");
    }
}